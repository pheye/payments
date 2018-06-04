<?php

namespace Pheye\Payments\Console\Commands;

use Illuminate\Console\Command;
use Pheye\Payments\Contracts\PaymentService;
use Pheye\Payments\Models\Payment;
use App\Exceptions\GenericException;

class InvoiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:invoice {transaction_id? : 交易id} {--force : 强制生成}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成交易票据，交易id可选，如果不填，将逐个生成所有交易的票据。交易id在票据中不会被体现，票据对应的交易必须是completed状态。';

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
        $this->comment("generate invoice start");
        $tids = $this->argument('transaction_id')? : [];
        $isForce = $this->option('force');
        $path = env('INVOICE_SAVE_PATH');
        if (empty($tids)) {
            $payments = Payment::where('payments.status', Payment::STATE_COMPLETED);
            //空交易id参数的情况下，强制生成=全表生成，不强制生成=生成表中invoice_id为空的记录的票据
            //调用命令时统一加第二个参数，强制生成
            if (!$isForce) {
                $payments->whereNull('invoice_id');//强制更新没有这个条件
            }
            $payments->chunk(
                10, function ($payments) {
                    $service = app(PaymentService::class);
                    foreach ($payments as $p) {
                        try{
                            $generateResult = $service->generateInvoice($p->number, true);
                            $this->comment("payment(id: $p->number) invoice was generated,invoice id is " . $generateResult);
                        }catch(GenericException $e){
                            $this->comment($e->getMessage());
                        }
                    }
                }
            );
        } else {
            $paymentService = app(PaymentService::class);
            if ($isForce) {
                //强制更新，重新生成一个票据
                try{
                    $generateResult = $paymentService->generateInvoice($tids, true);
                    $this->comment("payment(id: $tids) invoice was generated,invoice id is " . $generateResult);
                }catch(GenericException $e){
                    $this->comment($e->getMessage());
                }
            } else {
                //不强制，如果已经生成，给予提示
                $invoiceId = Payment::where('number', $tids)->value('invoice_id');
                if (!empty($invoiceId)) {
                    $this->comment("$tids has a invoice,that id is $invoiceId,file path is storage/app/" . $path . '/' . $invoiceId . '.pdf');
                    return;
                } else {
                    //如果没生成执行生成
                    try{
                        $generateResult = $paymentService->generateInvoice($tids);
                        $this->comment("payment(id: $tids) invoice was generated,invoice id is " . $generateResult);
                    }catch(GenericException $e){
                        $this->comment($e->getMessage());
                    }
                }
            }
        } 
        $this->comment("generate invoice end");
    }
}
