<?php

namespace Pheye\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class GatewayConfig extends Model
{
    const FACTORY_PAYPAL_EXPRESS_CHECKOUT = 'paypal_express_checkout';
    const FACTORY_PAYPAL_REST = 'paypal_rest';
    protected $casts = [
        'config' => 'json'
    ];
}
