<?php
namespace Pheye\Payments\Models;

use Pheye\Payments\Models\Subscription;
use Pheye\Payments\Models\Plan;

/**
 * 该类的存在是为了让Payum的Paypal EC循环扣款可以正常运作
 */
class AgreementDetails extends \ArrayObject
{
    public $subscription_id;
    public $plan_id;
    public $payum_id;

    public function __get ($name )
    {
        switch ($name) {
        case 'subscription':
            return Subscription::find($this->subscription_id);
        case 'plan':
            return Plan::find($this->plan_id);
        }
    }
}
