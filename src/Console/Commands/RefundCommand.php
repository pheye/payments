<?php

namespace Pheye\Payments\Console\Commands;

use Illuminate\Console\Command;
use Pheye\Payments\Models\Payment;
use Pheye\Payments\Models\Refund;

class RefundCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:refund {number : 定单号} {amount? : 退款金额，如无设置表示全额退款}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '对指定定单执行退款操作';

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
        $number = $this->argument('number');
        $payment =  Payment::where('number', $number)->first();
        $amount = $this->argument('amount');
        if (!$payment) {
            $this->error("payment {$number} not found");
            return;
        }
        $service->setLogger($this);
        $refund = $payment->refund;
        if (!$refund) {
            $refund = $service->requestRefund($payment, $amount);
            if (!$refund) {
                $this->error("refund generate failed");
                return;
            }
        }
        if (in_array($refund->status, [Refund::STATE_ACCEPTED, Refund::STATE_REJECTED])) {
            if (!$this->confirm("The refund has handled: {$refund->status}, are your sure to refund?")) {
                return;
            }
        }
        $service->refund($refund);
    }
}
