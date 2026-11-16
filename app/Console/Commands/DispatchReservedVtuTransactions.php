<?php
namespace App\Console\Commands;

use App\Jobs\SubmitVtuTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DispatchReservedVtuTransactions extends Command
{
    protected $signature = 'vtu:dispatch-reserved {--limit=100}';
    protected $description = 'Dispatch reserved VTU transactions and resume safe fallback routing after confirmed supplier failures.';
    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $ids = DB::table('transactions')->where('status', 'reserved')->orderBy('created_at')->limit($limit)->pluck('id');
        foreach ($ids as $id) SubmitVtuTransaction::dispatch((int) $id);
        $this->info('Dispatched '.$ids->count().' reserved transactions.');
        return self::SUCCESS;
    }
}
