<?php

namespace Pheye\Payments\Models;

use Payum\LaravelPackage\Model\Payment as BasePayment;
use Carbon\Carbon;

class Payment extends BasePayment
{
    const STATE_CREATED = "created";
    const STATE_PENDING = "pending";
    const STATE_COMPLETED = "completed";
    const STATE_FAILED = "failed";
    const STATE_REFUNDED = "refunded";
    const STATE_REFUNDING = "refunding";


    // 状态key值与value对应
    const STATE = [
        self::STATE_CREATED   =>  'Created',
        self::STATE_COMPLETED =>  'Completed',
        self::STATE_PENDING   =>  'Pending',
        self::STATE_FAILED    =>  'Failed',
        self::STATE_REFUNDED  =>  'Refunded',
        self::STATE_REFUNDING =>  'Refunding'
    ];

    protected $table = "payments";
    protected $fillable = ['number'];
    protected $appends = ['gateway', 'start_date', 'end_date', 'is_effective', 'plan'];
    protected $hidden = ['details'];
    protected $casts = [
        'details' => 'json'
    ];


    public function client()
    {
        return $this->belongsTo(\App\User::class, 'client_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }

    public function getGatewayAttribute()
    {
        return $this->subscription->gatewayConfig->formatted_factory_name;
    }

    public function getStartDateAttribute()
    {
        return $this->created_at->toDateTimeString();
    }

    public function getEndDateAttribute()
    {
        $subscription = $this->subscription;
        $carbon = new Carbon($this->created_at);

        switch (strtolower($subscription->frequency)) {
            case 'day':
                $carbon->addDays($subscription->frequency_interval);
                break;
            case 'week':
                $carbon->addWeeks($subscription->frequency_interval);
                break;
            case 'month':
                $carbon->addMonths($subscription->frequency_interval);
                break;
            case 'year':
                $carbon->addYears($subscription->frequency_interval);
                break;
        }
        return $carbon->toDateTimeString();
    }

    public function setCreatedAtAttribute($value)
    {
        $carbon = new \Carbon\Carbon($value);
        $carbon->tz = config('app.timezone');
        $this->attributes['created_at'] = $carbon->toDateTimeString();
    }

    public function setUpdatedAtAttribute($value)
    {
        $carbon = new \Carbon\Carbon($value);
        $carbon->tz = config('app.timezone');
        $this->attributes['updated_at'] = $carbon->toDateTimeString();
    }


    /**
     * 当前Payment是否生效，判断依据：
     * 1. 状态为完成或refunding
     * 2. 当前时间在起始时间和结束时间之间
     */
    public function isEffective()
    {
        //add by chenxin 20171114,修复了Issue #37
        if ($this->status != Payment::STATE_COMPLETED && $this->status != Payment::STATE_REFUNDING) {
            return false;
        }
        $end = new Carbon($this->end_date);
        return Carbon::now()->lt($end);
    }

    public function getIsEffectiveAttribute()
    {
        return $this->isEffective();
    }

    public function getPlanAttribute()
    {
        return str_replace('_', ' ', $this->subscription->plan);
    }

    public function scopeCompleted($query)   
    {                                        
        return $query->where('status', self::STATE_COMPLETED);                            
    } 
}
