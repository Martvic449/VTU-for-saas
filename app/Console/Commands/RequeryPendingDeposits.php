<?php
namespace App\Console\Commands;

use App\Services\Payments\DepositPostingService;
use App\Services\Payments\PaymentGatewayRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RequeryPendingDeposits extends Command
{
    protected $signature = 'payments:requery-pending {--limit=100}';
    protected $description = 'Verify pending wallet deposits directly with their payment gateway.';
    public function handle(PaymentGatewayRegistry $gateways, DepositPostingService $posting): int
    {
        $enabled = $gateways->enabled();
        if ($enabled === []) return self::SUCCESS;
        $limit = max(1, min(500, (int) $this->option('limit')));
        $deposits = DB::table('deposits')->where('status', 'pending')->whereIn('gateway', $enabled)
            ->where('created_at', '<=', now()->subMinutes(2))->where('created_at', '>=', now()->subDays(2))
            ->orderBy('created_at')->limit($limit)->get();
        foreach ($deposits as $deposit) {
            try {
                $verified = $gateways->for($deposit->gateway)->verify($deposit->reference);
                if (($verified['status'] ?? '') === 'under_review') { $posting->flagUnderReview($deposit->gateway, $deposit->reference, 'Scheduled gateway requery returned a review status.'); continue; }
                if (! in_array(($verified['status'] ?? ''), ['successful', 'failed'], true)) continue;
                if ((($verified['status'] ?? '') === 'successful' && (int) ($verified['amount_minor'] ?? -1) !== (int) $deposit->amount_minor)
                    || strtoupper((string) ($verified['currency'] ?? '')) !== $deposit->currency
                    || (string) ($verified['reference'] ?? '') !== $deposit->reference) {
                    $posting->flagUnderReview($deposit->gateway, $deposit->reference, 'Scheduled payment requery found a verification mismatch.');
                    continue;
                }
                $eventId = 'requery-'.hash('sha256', $deposit->gateway.'|'.$deposit->reference.'|'.json_encode($verified));
                if ($verified['status'] === 'successful') $posting->recordVerifiedSuccess($deposit->gateway, $eventId, $deposit->reference, $verified, false);
                else $posting->recordVerifiedFailure($deposit->gateway, $eventId, $deposit->reference, $verified, false);
            } catch (Throwable $exception) { report($exception); }
        }
        $this->info('Checked '.$deposits->count().' pending deposits.');
        return self::SUCCESS;
    }
}
