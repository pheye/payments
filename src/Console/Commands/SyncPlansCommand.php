<?php

namespace Pheye\Payments\Console\Commands;

use Illuminate\Console\Command;
use Pheye\Payments\Models\Plan;
use Payum\Stripe\Request\Api\CreatePlan;
use Stripe\Error;
use Stripe\Plan as StripePlan;
use Stripe\Stripe;
use Pheye\Payments\Contracts\PaymentService;
use Pheye\Payments\Models\GatewayConfig;

class SyncPlansCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:sync-plans {--gateway= : 指定网关}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '与Paypal,Stripe同步计划';

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
     * 对Payum分析，只提供了CreatePlan用于创建Plan；而其他方面并未提供，考虑过对Payum封装，但细想并无必要，这部分差异大，同时封装只是对接口做一层转接，反而觉得过度设计。
     *
     * @return mixed
     */
    public function handle()
    {
        $service = app(\Pheye\Payments\Contracts\PaymentService::class);
        /* $isAll = true; */
        $gatewayName = $this->option('gateway');
        $gateway = GatewayConfig::where('gateway_name', $gatewayName)->first();
        $gateways = [$gateway];
        $this->info("start syncing plans...");
        $service->setLogger($this);
        $service->syncPlans($gateways);
    }
}
