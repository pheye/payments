<?php
namespace Pheye\Payments\Payum\Stripe\Extensions;

use Payum\Core\Extension\Context;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Stripe\Extension\CreateCustomerExtension as PayumCreateCustomerExtension;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\GatewayInterface;
use Payum\Stripe\Request\Api\CreateCustomer;
use Pheye\Payments\Events\CreditUsedEvent;

class CreateCustomerExtension extends PayumCreateCustomerExtension
{
    /**
     * @var Context $context
     */
    public function onPreExecute(Context $context)
    {
        $request = $context->getRequest();
        if (false == $request instanceof \Payum\Core\Request\Capture) {
            return;
        }
        $model = $request->getModel();
        if (false == $model instanceof \Payum\Core\Bridge\Spl\ArrayObject) {
            return;
        }
        if (false == ($model instanceof \ArrayAccess)) {
            return;
        }

        $this->createCustomer($context->getGateway(), ArrayObject::ensureArrayObject($model));
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
        /** @var Capture $request */
        $request = $context->getRequest();
        /* if (false == $request instanceof ObtainToken) { */
        /*     return; */
        /* } */
        return;
        $model = $request->getModel();
        $details = $model->getDetails();
        if (false == ($details instanceof \ArrayAccess)) {
            return;
        }

        $this->createCustomer($context->getGateway(), ArrayObject::ensureArrayObject($details));
    }

    /**
     * @param GatewayInterface $gateway
     * @param ArrayObject $model
     */
    protected function createCustomer(GatewayInterface $gateway, ArrayObject $model)
    {
        if (false == ($model['card'] && is_string($model['card']))) {
            return;
        }

        $local = $model->getArray('local');
        if (false == $local['save_card']) {
            return;
        }
        $customer = $local->getArray('customer');
        $customer['card'] = $model['card'];

        $gateway->execute(new CreateCustomer($customer));

        $local['customer'] = $customer->toUnsafeArray();
        $model['local'] = $local->toUnsafeArray();
        unset($model['card']);

        if ($customer['id']) {
            $model['customer'] = $customer['id'];
        } else {
            $model['status'] = Constants::STATUS_FAILED;
        }
        $data = $local['customer']['sources']['data'];
        $client = ['id' => $local['user_id']];
        event(new CreditUsedEvent($data, $client));
    }
}
