<?php

namespace App\Services\Reconciliation;

use Illuminate\Support\Facades\DB;

final class LedgerReconciliationService
{
    public function run(): array
    {
        $runId = DB::table('reconciliation_runs')->insertGetId([
            'scope' => 'ledger', 'status' => 'running', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $imbalanced = DB::table('ledger_journals')->join('ledger_entries', 'ledger_entries.ledger_journal_id', '=', 'ledger_journals.id')
            ->where('ledger_journals.status', 'posted')->groupBy('ledger_journals.id')
            ->havingRaw("SUM(CASE WHEN ledger_entries.side = 'debit' THEN ledger_entries.amount_minor ELSE 0 END) <> SUM(CASE WHEN ledger_entries.side = 'credit' THEN ledger_entries.amount_minor ELSE 0 END)")
            ->pluck('ledger_journals.id')->all();
        $result = ['journals_checked' => DB::table('ledger_journals')->where('status', 'posted')->count(), 'imbalanced_journal_ids' => $imbalanced];
        DB::table('reconciliation_runs')->where('id', $runId)->update([
            'status' => $imbalanced ? 'exceptions' : 'completed', 'summary' => json_encode($result),
            'exception_summary' => $imbalanced ? 'One or more posted journals are unbalanced.' : null,
            'finished_at' => now(), 'updated_at' => now(),
        ]);
        return ['run_id' => $runId] + $result;
    }
}
