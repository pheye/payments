<?php

namespace Pheye\Payments\Console\Commands;

use Illuminate\Console\Command;
use App\Contracts\PaymentService;
use App\Subscription;
use Payum\Paypal\ExpressCheckout\Nvp\Request\Api\TransactionSearch;
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
        $service = app(\Pheye\Payments\Contracts\PaymentService::class);
        $force = $this->option('force');
        $agreeId = $this->argument('agreement-id');
        $gateways = [];
        $this->info("start syncing payments...");
        if ($force) {
            $service->setParameter(PaymentService::PARAMETER_FORCE, true);
            $this->info("force flag set");
        }
        $payum = app('payum');
        $storage = $payum->getStorage('Payum\Core\Model\ArrayObject');
        $model = $storage->create();
        $model['PROFILEID'] = 'I-JKNM0USBH2L7';
        $model['STARTDATE'] = Carbon::now()->subYear()->toIso8601String();
        $storage->update($model);
        $gateway = $payum->getGateway('paypal_ec');
        $gateway->execute(new TransactionSearch($model));
        dd($model);
        $service->setLogger($this);
        $service->syncPayments($agreeId ? Subscription::where('agreement_id', $agreeId)->first() : null);
    }
}
