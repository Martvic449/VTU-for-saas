<?php

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Posts immutable, balanced journals. Amounts are integer minor units (kobo for NGN). */
final class LedgerService
{
    /**
     * @param array<int, array{account_id:int, side:'debit'|'credit', amount_minor:int, memo?:string}> $lines
     */
    public function post(string $eventType, string $currency, array $lines, string $reference, ?int $actorId = null, ?string $description = null, ?int $reversalOfId = null): int
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['ledger' => 'A journal requires at least two entries.']);
        }

        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $amount = $line['amount_minor'] ?? 0;
            if (! is_int($amount) || $amount <= 0 || ! in_array($line['side'] ?? null, ['debit', 'credit'], true)) {
                throw ValidationException::withMessages(['ledger' => 'Journal entries must have a valid side and positive integer amount.']);
            }
            $line['side'] === 'debit' ? $debits += $amount : $credits += $amount;
        }
        if ($debits !== $credits) {
            throw ValidationException::withMessages(['ledger' => 'Journal debits and credits must balance.']);
        }

        return DB::transaction(function () use ($eventType, $currency, $lines, $reference, $actorId, $description, $reversalOfId): int {
            if (DB::table('ledger_journals')->where('reference', $reference)->exists()) {
                throw new RuntimeException('Ledger journal reference already exists.');
            }

            $accountIds = collect($lines)->pluck('account_id')->unique()->sort()->values();
            $accounts = DB::table('ledger_accounts')->whereIn('id', $accountIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($accounts->count() !== $accountIds->count()) {
                throw new RuntimeException('One or more ledger accounts do not exist.');
            }
            foreach ($accounts as $account) {
                if ($account->currency !== $currency) {
                    throw new RuntimeException('Journal currency must match every ledger account.');
                }
            }

            $journalId = DB::table('ledger_journals')->insertGetId([
                'reference' => $reference,
                'event_type' => $eventType,
                'currency' => $currency,
                'status' => 'posted',
                'description' => $description,
                'reversal_of_id' => $reversalOfId,
                'created_by' => $actorId,
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lines as $line) {
                DB::table('ledger_entries')->insert([
                    'ledger_journal_id' => $journalId,
                    'ledger_account_id' => $line['account_id'],
                    'side' => $line['side'],
                    'amount_minor' => $line['amount_minor'],
                    'currency' => $currency,
                    'memo' => $line['memo'] ?? null,
                    'created_at' => now(),
                ]);
            }

            return $journalId;
        }, 3);
    }

    public function reverse(int $journalId, string $reference, ?int $actorId = null, ?string $reason = null): int
    {
        return DB::transaction(function () use ($journalId, $reference, $actorId, $reason): int {
            $original = DB::table('ledger_journals')->where('id', $journalId)->lockForUpdate()->first();
            if (! $original || $original->status !== 'posted' || $original->reversal_of_id !== null) {
                throw new RuntimeException('Only a posted journal can be reversed.');
            }
            if (DB::table('ledger_journals')->where('reversal_of_id', $journalId)->exists()) {
                throw new RuntimeException('This journal has already been reversed.');
            }
            $entries = DB::table('ledger_entries')->where('ledger_journal_id', $journalId)->get();
            $lines = $entries->map(fn ($entry) => [
                'account_id' => (int) $entry->ledger_account_id,
                'side' => $entry->side === 'debit' ? 'credit' : 'debit',
                'amount_minor' => (int) $entry->amount_minor,
                'memo' => 'Reversal: '.$reason,
            ])->all();
            return $this->post('journal.reversal', $original->currency, $lines, $reference, $actorId, $reason, $journalId);
        }, 3);
    }
}
