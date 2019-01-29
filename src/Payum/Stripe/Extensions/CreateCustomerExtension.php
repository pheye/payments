<?php
namespace Pheye\Payments\Payum\Stripe\Extensions;

use Payum\Core\Extension\Context;
use Payum\Stripe\Extension\CreateCustomerExtension as PayumCreateCustomerExtension;

class CreateCustomerExtension extends PayumCreateCustomerExtension
{
    /**
     * @var Context $context
     */
    public function onPreExecute(Context $context)
    {
        // disable customers creation
    }

    /**
     * @var Context $context
     */
    public function onExecute(Context $context)
    {
        // disable customers creation
    }

    /**
     * @var Context $context
     */
    public function onPostExecute(Context $context)
    {
        // disable customers creation
    }
}
