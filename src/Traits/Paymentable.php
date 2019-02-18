<?php
namespace Pheye\Payments\Traits;

use Pheye\Payments\Models\Subscription;
use Pheye\Payments\Models\Payment;
use Pheye\Payments\Models\Coupon;

trait Paymentable
{
    /**
     * 当前订阅
     * 用户的当前订阅只能是已经付款且处于活动状态的订阅
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * 所有订阅
     * 用户可能购买订阅，但是没有付款，获取用户的所有订阅可以更好地通知用户
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * 获取最近的有效订阅
     */
    public function getEffectiveSub()
    {
        $subs = $this->subscriptions()
            ->where('status', '<>', Subscription::STATE_CREATED)
            ->whereNotNull('agreement_id')
            ->orderBy('created_at', 'desc')
            ->get();
        $baseSub = null;
        // 有效订阅未必是最后一条，比如用户取消当前订阅，购买新订阅，但是扣款失败。这时没有活动订阅，有效订阅仍然是前一个。
        for ($i = 0; $i < count($subs); ++$i) {
            if ($subs[$i]->hasEffectivePayment()) {
                $baseSub = $subs[$i];
                break;
            }
        }
        return $baseSub;
    }

    /**
     * 获取所有支付记录
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'client_id');
    }

    public function isFree()
    {
        return $this->hasRole('Free');
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function fixInfoByPayments()
    {
        // stub
    }
}
