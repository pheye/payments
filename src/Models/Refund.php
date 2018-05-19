<?php

namespace Pheye\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    const STATE_CREATED = "created";
    const STATE_PENDING = "pending";
    const STATE_ACCEPTED = "accepted";
    const STATE_REJECTED = "rejected";

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Voyager根据同名field去查找外键，需要优化下
     */
    /* public function paymentId() */
    /* { */
    /*     return $this->payment(); */
    /* } */

    public function isRefunding()
    {
        return in_array($this->status, [Refund::STATE_CREATED, Refund::STATE_PENDING]);
    }
}
