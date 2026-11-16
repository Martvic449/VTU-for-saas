<?php
namespace App\Services\Accounting;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class WalletAdjustmentService
{
    public function __construct(private readonly LedgerService $ledger, private readonly WalletBalanceService $balances) {}

    public function request(int $walletId, int $amountMinor, string $direction, string $reason, ?string $evidence, int $actorId): int
    {
        if ($amountMinor <= 0 || ! in_array($direction, ['credit','debit'], true) || strlen(trim($reason)) < 8) {
            throw new RuntimeException('Adjustment amount, direction or reason is invalid.');
        }
        return DB::transaction(function () use ($walletId,$amountMinor,$direction,$reason,$evidence,$actorId): int {
            $wallet=DB::table('wallets')->where('id',$walletId)->lockForUpdate()->first();
            if (!$wallet || $wallet->status!=='active') throw new RuntimeException('Wallet is unavailable.');
            if ($wallet->currency!=='NGN') throw new RuntimeException('Only NGN adjustments are currently supported.');
            if ($direction==='debit' && $this->balances->availableMinor($walletId)<$amountMinor) throw new RuntimeException('Adjustment exceeds the available wallet balance.');
            return DB::table('wallet_adjustments')->insertGetId([
                'wallet_id'=>$walletId,'amount_minor'=>$amountMinor,'direction'=>$direction,'status'=>'pending',
                'reason'=>trim($reason),'evidence_reference'=>$evidence,'requested_by'=>$actorId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
    }

    public function approve(int $adjustmentId, int $approverId, string $decisionReason): int
    {
        return DB::transaction(function () use ($adjustmentId,$approverId,$decisionReason): int {
            $adjustment=DB::table('wallet_adjustments')->where('id',$adjustmentId)->lockForUpdate()->first();
            if (!$adjustment || $adjustment->status!=='pending') throw new RuntimeException('Adjustment is not pending approval.');
            if ((int)$adjustment->requested_by===$approverId) throw new RuntimeException('The requester cannot approve their own wallet adjustment.');
            $wallet=DB::table('wallets')->where('id',$adjustment->wallet_id)->lockForUpdate()->first();
            if (!$wallet || $wallet->status!=='active') throw new RuntimeException('Wallet is unavailable.');
            $mapping=DB::table('wallet_ledger_accounts')->where('wallet_id',$wallet->id)->first();
            $walletAccount=$mapping ? DB::table('ledger_accounts')->where('id',$mapping->ledger_account_id)->first() : null;
            if (!$walletAccount) throw new RuntimeException('Wallet ledger account is missing.');
            if ($adjustment->direction==='debit' && $this->balances->availableMinor((int)$wallet->id)<(int)$adjustment->amount_minor) throw new RuntimeException('Wallet no longer has sufficient available credit.');
            $counterCode=$adjustment->direction==='credit'?'WALLET_ADJUSTMENT_EXPENSE_NGN':'WALLET_ADJUSTMENT_RECOVERY_NGN';
            $counter=DB::table('ledger_accounts')->where('code',$counterCode)->first();
            if (!$counter) throw new RuntimeException('Wallet adjustment control account is not configured.');
            $amount=(int)$adjustment->amount_minor;
            $lines=$adjustment->direction==='credit'
                ? [['account_id'=>(int)$counter->id,'side'=>'debit','amount_minor'=>$amount],['account_id'=>(int)$walletAccount->id,'side'=>'credit','amount_minor'=>$amount]]
                : [['account_id'=>(int)$walletAccount->id,'side'=>'debit','amount_minor'=>$amount],['account_id'=>(int)$counter->id,'side'=>'credit','amount_minor'=>$amount]];
            $journal=$this->ledger->post('wallet.manual_adjustment','NGN',$lines,'ADJ-'.$adjustment->id,$approverId,$decisionReason);
            DB::table('wallet_adjustments')->where('id',$adjustmentId)->update([
                'status'=>'approved','approved_by'=>$approverId,'ledger_journal_id'=>$journal,
                'decision_reason'=>trim($decisionReason),'approved_at'=>now(),'updated_at'=>now(),
            ]);
            return $journal;
        },3);
    }

    public function reject(int $adjustmentId, int $approverId, string $decisionReason): void
    {
        DB::transaction(function () use ($adjustmentId,$approverId,$decisionReason): void {
            $adjustment=DB::table('wallet_adjustments')->where('id',$adjustmentId)->lockForUpdate()->first();
            if (!$adjustment || $adjustment->status!=='pending') throw new RuntimeException('Adjustment is not pending.');
            if ((int)$adjustment->requested_by===$approverId) throw new RuntimeException('The requester cannot reject their own wallet adjustment.');
            DB::table('wallet_adjustments')->where('id',$adjustmentId)->update([
                'status'=>'rejected','approved_by'=>$approverId,'decision_reason'=>trim($decisionReason),'approved_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
    }
}
