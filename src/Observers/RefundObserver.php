<?php

namespace Pheye\Payments\Observers;

use Pheye\Payments\Model\Payment;
use Pheye\Payments\Model\Refund;

class RefundObserver
{
    protected function translate(Refund $refund)
    {
        $old = $refund->payment->status;
        switch ($refund->status) {
        case Refund::STATE_CREATED:
        case Refund::STATE_PENDING;
            $refund->payment->status = Payment::STATE_REFUNDING;
            break;
        case Refund::STATE_ACCEPTED:
            $refund->payment->status = Payment::STATE_REFUNDED;
            break;
        case Refund::STATE_REJECTED:
            $refund->payment->status = Payment::STATE_COMPLETED;
            break;
        }
        if ($old != $refund->payment->status)
            $refund->payment->save();
    }

    public function creating(Refund $refund)
    {
        $payment = Payment::find($refund->payment_id);
        if (!$payment)
            return false;
        if ($payment->refund ||  !in_array($payment->status, [Payment::STATE_COMPLETED, Payment::STATE_REFUNDED]))
            return false;
        return true;
    }

    
    public function created(Refund $refund)
    {
        $this->translate($refund);
    }

    public function updated(Refund $refund)
    {
        $this->translate($refund);
    }

    public function deleted(Refund $refund)
    {
       if ($refund->payment->status != Payment::STATE_COMPLETED) {
           $refund->payment->status = Payment::STATE_COMPLETED;
           $refund->payment->save();
       }
    }

}
