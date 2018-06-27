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

    protected $casts = [
        'config' => 'json'
    ];

    public function getFormattedFactoryNameAttribute()
    {
        switch ($this->factory_name) {
        case static::FACTORY_PAYPAL_EXPRESS_CHECKOUT:
        case static::FACTORY_PAYPAL_REST:
            return 'Paypal';
        case static::FACTORY_ZHONGWAIBAO:
            return 'Credit';
        case statis::FACTORY_OFFLINE:
            return 'Offline';
        default:
            return 'Unknown';
        }
    }
}
