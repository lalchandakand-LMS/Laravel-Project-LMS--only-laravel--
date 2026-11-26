<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature = 'orders:expire-unpaid {--hours=24 : Expire unpaid orders older than this many hours}';

    protected $description = 'Cancel stale unpaid orders and release their reserved physical inventory';

    public function handle(): int
    {
        $hours = max(1, min(720, (int) $this->option('hours')));
        $expired = OrderService::expireUnpaidOrders($hours);

        $this->info("Expired {$expired} unpaid order(s) older than {$hours} hour(s); their reserved inventory was released.");

        return self::SUCCESS;
    }
}
