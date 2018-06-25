<?php

namespace Pheye\Payments\Console\Commands;

use Illuminate\Console\Command;
use Pheye\Payments\Models\Subscription;
use App\ActionLog;
use App\Jobs\LogAction;

class CancelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:cancel {agreement_id : 订阅ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '取消指定的订阅';

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
        $agreementId = $this->argument('agreement_id');
        $sub = Subscription::where('agreement_id', $agreementId)->first();
        if (!$sub) {
            $this->error("$agreementId not found");
            return;
        }
        if ($sub->status == Subscription::STATE_CANCLED) {
            $this->error("$agreementId has been cancelled, don't cancel again");
            return;
        }
        $this->comment("cancelling agreement id: $agreementId");
        $service = app(\Pheye\Payments\Contracts\PaymentService::class);
        $service->setLogger($this);

        if ($service->cancel($sub)) {
            $this->comment("cancel successfully");
            /* dispatch(new LogAction(ActionLog::ACTION_ADMIN_CANCEL, $sub->toJson(), "", $sub->user->id)); */
        } else {
            $this->error("cancel failed");
        }
    }
}
