<?php
namespace App\Http\Controllers;
use App\Services\Accounting\WalletAdjustmentService;
use App\Services\Accounting\WalletBalanceService;
use App\Services\AuditLogger;
use App\Services\Reconciliation\LedgerReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class PlatformFinanceController
{
    private function authorizeFinance(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['super-admin','finance-admin']),403);
    }
    public function index(Request $request, WalletBalanceService $balances)
    {
        $this->authorizeFinance($request);
        $wallets=DB::table('wallets')->leftJoin('users',function($join){$join->on('users.id','=','wallets.user_id');})
            ->leftJoin('tenants',function($join){$join->on('tenants.id','=','wallets.tenant_id');})
            ->select('wallets.*','users.name as user_name','users.email as user_email','tenants.name as tenant_name')
            ->where('wallets.status','active')->orderByDesc('wallets.id')->limit(200)->get();
        foreach($wallets as $wallet) $wallet->available_minor=$balances->availableMinor((int)$wallet->id);
        $adjustments=DB::table('wallet_adjustments')->join('wallets','wallets.id','=','wallet_adjustments.wallet_id')
            ->leftJoin('users as requesters','requesters.id','=','wallet_adjustments.requested_by')
            ->leftJoin('users as approvers','approvers.id','=','wallet_adjustments.approved_by')
            ->select('wallet_adjustments.*','wallets.owner_type','wallets.owner_id','requesters.name as requester_name','approvers.name as approver_name')
            ->orderByDesc('wallet_adjustments.created_at')->paginate(30);
                $deposits = DB::table('deposits')->join('wallets','wallets.id','=','deposits.wallet_id')->leftJoin('users','users.id','=','wallets.user_id')->leftJoin('tenants','tenants.id','=','wallets.tenant_id')->select('deposits.*','users.name as customer_name','users.email as customer_email','tenants.name as tenant_name')->orderByRaw("CASE deposits.status WHEN 'under_review' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")->latest('deposits.created_at')->limit(100)->get();
        $reconciliationRuns = DB::table('reconciliation_runs')->where('scope','ledger')->latest()->limit(10)->get();
        $recentJournals = DB::table('ledger_journals')->latest()->limit(20)->get();
        return view('platform.finance',compact('wallets','adjustments','deposits','reconciliationRuns','recentJournals'));
    }
    public function reconcile(Request $request, LedgerReconciliationService $reconciliation, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($request);
        abort_unless($request->user()->hasRole('super-admin'), 403, 'Only the platform super administrator can initiate a ledger reconciliation.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        $result = $reconciliation->run();
        $audit->record('ledger.reconciliation_run', null, 'reconciliation_runs', (string) $result['run_id'], $data['reason'], ['journals_checked' => $result['journals_checked'], 'imbalanced_journal_ids' => $result['imbalanced_journal_ids']]);
        return back()->with('status', $result['imbalanced_journal_ids'] ? 'Reconciliation completed with exceptions. Investigate the listed journal IDs.' : 'Ledger reconciliation completed: all posted journals are balanced.');
    }

    public function requestAdjustment(Request $request, WalletAdjustmentService $adjustments, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($request);
        $data=$request->validate(['wallet_id'=>['required','integer','exists:wallets,id'],'amount_minor'=>['required','integer','min:1'],'direction'=>['required','in:credit,debit'],'reason'=>['required','string','min:8','max:500'],'evidence_reference'=>['nullable','string','max:180']]);
        try { $id=$adjustments->request((int)$data['wallet_id'],(int)$data['amount_minor'],$data['direction'],$data['reason'],$data['evidence_reference']??null,(int)$request->user()->id); }
        catch(RuntimeException $exception){return back()->withErrors(['adjustment'=>$exception->getMessage()])->withInput();}
        $audit->record('wallet.adjustment_requested',null,'wallet_adjustments',(string)$id,$data['reason'],['wallet_id'=>$data['wallet_id'],'amount_minor'=>$data['amount_minor'],'direction'=>$data['direction']]);
        return back()->with('status','Wallet adjustment request submitted for independent approval. No balance has changed yet.');
    }
    public function approve(Request $request, int $adjustment, WalletAdjustmentService $adjustments, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($request);
        $data=$request->validate(['decision_reason'=>['required','string','min:8','max:500']]);
        try { $journal=$adjustments->approve($adjustment,(int)$request->user()->id,$data['decision_reason']); }
        catch(RuntimeException $exception){return back()->withErrors(['adjustment'=>$exception->getMessage()]);}
        $audit->record('wallet.adjustment_approved',null,'wallet_adjustments',(string)$adjustment,$data['decision_reason'],['ledger_journal_id'=>$journal]);
        return back()->with('status','Adjustment approved and posted to the immutable ledger.');
    }
    public function reject(Request $request, int $adjustment, WalletAdjustmentService $adjustments, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($request);
        $data=$request->validate(['decision_reason'=>['required','string','min:8','max:500']]);
        try { $adjustments->reject($adjustment,(int)$request->user()->id,$data['decision_reason']); }
        catch(RuntimeException $exception){return back()->withErrors(['adjustment'=>$exception->getMessage()]);}
        $audit->record('wallet.adjustment_rejected',null,'wallet_adjustments',(string)$adjustment,$data['decision_reason']);
        return back()->with('status','Adjustment request rejected. No wallet balance was changed.');
    }
}
