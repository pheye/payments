<?php

namespace Pheye\Payments\Console\Commands;

use Illuminate\Console\Command;
use Pheye\Payments\Contracts\PaymentService;
use Pheye\Payments\Models\Subscription;
use Carbon\Carbon;

class SyncPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:sync-payments  {agreement-id?} {--f|force : 强制与远程同步}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步指定订阅的支付订单;默认忽略被取消的订阅，除非指定强制参数';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $service = app(PaymentService::class);
        $force = $this->option('force');
        $agreeId = $this->argument('agreement-id');
        $gateways = [];
        $this->info("start syncing payments...");
        if ($force) {
            $service->setParameter(PaymentService::PARAMETER_FORCE, true);
            $this->info("force flag set");
        } 
        $sub = null;
        if ($agreeId) {
            $sub = Subscription::where('agreement_id', $agreeId)->first(); 
            if (!$sub) {
                $this->error("$agreeId not found");
                return;
            }
        }
        $service->setLogger($this);
        $service->syncPayments($sub);
    }
}
