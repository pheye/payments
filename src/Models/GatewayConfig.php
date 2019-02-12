<?php

namespace Pheye\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class GatewayConfig extends Model
{
    const FACTORY_PAYPAL_EXPRESS_CHECKOUT = 'paypal_express_checkout';
    const FACTORY_PAYPAL_REST = 'paypal_rest';
    const FACTORY_ZHONGWAIBAO = "zhongwaibao";
    const FACTORY_STRIPE = "stripe";
    const FACTORY_OFFLINE = "offline";
    const FACTORY_ALIPAY = "alipay";

    protected $casts = [
        'config' => 'json'
    ];
    protected $hidden = ['config'];

    public function getFormattedFactoryNameAttribute()
    {
        switch ($this->factory_name) {
        case static::FACTORY_PAYPAL_EXPRESS_CHECKOUT:
        case static::FACTORY_PAYPAL_REST:
            return 'Paypal';
        case static::FACTORY_ZHONGWAIBAO:
            return 'Credit';
        case static::FACTORY_ALIPAY:
            return 'Alipay';
        case static::FACTORY_STRIPE:
            return 'Credit';
        case static::FACTORY_OFFLINE:
            return 'Offline';
        default:
            return 'Unknown';
        }
    }
}
