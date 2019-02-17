<?php
namespace Pheye\Payments\Payum\Stripe\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Payum\Stripe\Request\Api\CreateCharge;
use Payum\Stripe\Request\Api\CreateSubscription;
use Payum\Stripe\Request\Api\ObtainToken;
use Payum\Core\Model\Payment;
use Log;

class CaptureAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /* dd($request->getModel()); */
        /* $payment = $request->getFirstModel(); */
        /* $model = ArrayObject::ensureArrayObject($payment->getDetails()); */
        $model = ArrayObject::ensureArrayObject($request->getModel()); 

        if ($model['status']) {
            return;
        }
        if ($model['customer']) {
        } else {
            if (false == $model['card']) {
                $obtainToken = new ObtainToken($request->getToken());
                $obtainToken->setModel($model);

                $this->gateway->execute($obtainToken);
            }
        }

        if ($model['items']) {
            $newModel = ArrayObject::ensureArrayObject([
                'customer' => $model['customer'],
                'items' => $model['items']
            ]);
            $this->gateway->execute(new CreateSubscription($newModel));
            $model->replace($newModel);
        } else {
            $this->gateway->execute(new CreateCharge($model));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        $ret = 
            ($request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess &&
            !($request->getModel() instanceof \Payum\LaravelPackage\Model\Token));
        return $ret;
    }
}
