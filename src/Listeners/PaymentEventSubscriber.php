<?php

namespace Pheye\Payments\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pheye\Payments\Models\Payment;
use Pheye\Payments\Events\PayedEvent;
use Pheye\Payments\Events\RefundedEvent;
use Pheye\Payments\Events\CancelledEvent;
use Pheye\Payments\Events\CreditUsedEvent;
use Log;

class PaymentEventSubscriber
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * 处理支付完成事件
     */
    public function onPayed(PayedEvent $event)
    {
        // stub
        /* $payment = $event->payment; */
        /* Log::info('on payed:' . $payment->number); */
    }

    /**
     * 处理退款完成事件
     */
    public function onRefunded(RefundedEvent $event)
    {
        // stub
        /* $refund = $event->refund; */
        /* Log::info('on refuned'.  $refund->payment->number); */
    }

    /**
     * 处理取消订阅事件
     */
    public function onCancelled(CancelledEvent $event)
    {
        // stub
        /* $sub = $event->subscription; */
        /* Log::info('on cancelled'.  $sub->agreement_id); */
    }

    /**
     * 处理信用卡使用事件
     *
     * 这是保存信用卡信息的最佳时机
     */
    public function onCreditUsed(CreditUsedEvent $event)
    {
        // stub
    }

    /**
     * @param  Illuminate\Events\Dispatcher  $events
     */
    public function subscribe($events)
    {
        $class = get_called_class();
        $events->listen(
            PayedEvent::class,
            $class . '@onPayed'
        );

        $events->listen(
            RefundedEvent::class,
            $class . '@onRefunded'
        );

        $events->listen(
            CancelledEvent::class,
            $class . '@onCancelled'
        );


        $events->listen(
            CreditUsedEvent::class,
            $class . '@onCreditUsed'
        );
    }
}
