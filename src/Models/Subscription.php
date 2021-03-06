<?php

namespace Pheye\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $casts = [
        'details'           =>  'array',
        'trial_ends_at'     =>  'datetime',
        'ends_at'           =>  'datetime',
        'next_billing_date' =>  'datetime',
        'canceled_at'       =>  'datetime'
    ];

    protected $hidden = ['details'];

    public function getDetailsAttribute ($value)
    {
        if (is_null($value))
            return [];
        return json_decode($value, true);
    }
    /**
     * 订阅刚创建，未完成时的状态,每个用户应最多只有一个处于该状态的订阅
     */
    const STATE_CREATED = "created";

    /**
     * 订阅完成时的状态
     */
    const STATE_SUBSCRIBED = "subscribed";

    /**
     * 订阅完成支付的状态
     */
    const STATE_PAYED = "payed";

    /**
     * 订阅到期未续费的状态
     */
    const STATE_EXPIRED = "expired";

    /**
     * 取消订阅时的状态
     */
    const STATE_CANCLED = "canceled";

    /**
     * 挂起订阅时的状态
     */
    const STATE_SUSPENDED = "suspended";
    
    /**
     * 订阅失败时的状态
     */
    const STATE_FAILED = "failed";
    /**
     * 挂起订阅时的状态
     */
    const STATE_PENDING = "pending";

    // 状态 key与对应的值
    const STATE = [
        self::STATE_CREATED     =>  'Created',
        self::STATE_SUBSCRIBED  =>  'Subscribed',
        self::STATE_PAYED       =>  'Payed',
        self::STATE_EXPIRED     =>  'Expired',
        self::STATE_CANCLED     =>  'Canceled',
        self::STATE_SUBSCRIBED  =>  'Suspended',
        self::STATE_FAILED      =>  'Failed',
        self::STATE_PENDING     =>  'Pending'
    ];

    /**
     * 默认的tag值
     */
    const TAG_DEFAULT = "default";


    const GATEWAY_STRIPE = "stripe";
    const GATEWAY_PAYPAL = "paypal";

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function gatewayConfig()
    {
        return $this->hasOne(GatewayConfig::class, 'id', 'gateway_id');
    }

    public function isActive()
    {
        return $this->user->subscription_id === $this->id;
    }

    public function translateStatus($remoteStatus)
    {
        if ($this->gateway === 'paypal') {
            switch (strtolower($remoteStatus)) {
            case 'active':
                return Subscription::STATE_PAYED;
            case 'pending':
                return Subscription::STATE_PENDING;
            case 'canceled':
                return Subscription::STATE_CANCLED;
            }
        }
        return Subscription::STATE_SUBSCRIBED;
    }


    public function canCancel()
    {
        if (!$this->agreement_id)
            return false;
        // 未完成的订阅和已经取消的不允许取消，其他的都允许取消
        if ($this->status == Subscription::STATE_CREATED || $this->status == Subscription::STATE_CANCLED)
            return false;
        return true;
    }

    /**
     * 当前订阅是否有生效的订单
     */
    public function hasEffectivePayment()
    {
        foreach ($this->payments as $payment) {
            if ($payment->isEffective())
                return true;
        }
        return false;
    }

    /**
     * 获取第一个有效的订单
     */
    /* public function getFirstEffectivePayment() */
    /* { */
    /*     foreach ($this->payments as $payment) { */
    /*         if ($payment->isEffective()) */
    /*             return $payment; */
    /*     } */
    /*     return null; */
    /* } */

    public function getPlan()
    {
        return Plan::where('name', $this->plan)->first();
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan', 'name');
    }

    /**
     * 根据订阅计算出天数
     */
    public function getLeftDays()
    {
        $nowTime = Carbon::now();
        $longestExpired = $nowTime;
        $selectedPayment = null;
        foreach ($this->payments as $payment) {
            if ($payment->isEffective()) {
                $expired = new Carbon($payment->end_date);
                $expired->addDay();
                if ($expired->gt($nowTime)) {
                    $longestExpired = $expired;
                    $selectedPayment = $payment;
                }
            }
        }
        if (!$selectedPayment)
            return 0;
        $days = $longestExpired->diffInDays($nowTime);
        if ($days > 0) {
            $days -= 1;
        }
        return $days;
    }

    /**
     * 获取该订阅总付款额
     * 统计所有订单金额
     */
    public function getTotalPaid()
    {
        $total = 0;
        foreach ($this->payments as $payment) {
            $total += $payment->amount;
        }
        return $total;
    }
}
