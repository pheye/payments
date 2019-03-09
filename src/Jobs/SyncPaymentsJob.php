<?php

namespace Pheye\Payments\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pheye\Payments\Models\Subscription;
use Log;

class SyncPaymentsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    private $subscription;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Subscription $sub)
    {
        $this->subscription = $sub;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->subscription instanceof Subscription) {
            Log::warning(SyncSubscriptionJob::class . ": subscription not found");
            return;
        }
        $paymentService = app(\Pheye\Payments\Contracts\PaymentService::class);
        $paymentService->syncPayments([], $this->subscription);
    }
}
