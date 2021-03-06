<?php

namespace Pheye\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;

class Coupon extends Model
{
    const TYPE_PERCENTAGE = 'Percentage';
    const TYPE_FIXED_AMOUNT = 'Fixed Amount';

    // 折扣类型， 0 为百分比， 1 为固定金额
    const TYPE = [self::TYPE_PERCENTAGE, self::TYPE_FIXED_AMOUNT];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime'
    ];

    //
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * 动态计算实际折扣价格
     * @param float $amount
     * @return int 实际折扣价格
     */
    public function getDiscountAmount($amount)
    {
        if ($this->type == 0) {
            $discount = floor($amount * $this->discount / 100);
        } else if ($this->type == 1) {
            $discount = $this->discount;
        }
        return $discount;
    }

    /**
     * 扫描订单，动态计算出实际使用优惠券的数量
     * 只有订阅有订单，就认为使用了优惠券，不论是支付失败；成功；还是退款。
     *
     * @return int
     */
    public function calcUsed() : int
    {
        return $this->subscriptions()->has('payments')->count();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
