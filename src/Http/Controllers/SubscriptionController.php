<?php

namespace Pheye\Payments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Auth;
use Log;
use App\User;
use App\Webhook;
use App\ActionLog;
use Pheye\Payments\Models\Role;
use Pheye\Payments\Models\Plan;
use Pheye\Payments\Models\Subscription;
use Pheye\Payments\Models\GatewayConfig;
use Pheye\Payments\Models\AgreementDetails;
use Pheye\Payments\Models\RecurringPaymentDetails;
use Pheye\Payments\Models\Coupon;
use Pheye\Payments\Models\Refund;
use Pheye\Payments\Services\PaypalService;
use Carbon\Carbon;
use Payum\Core\Request\Sync;
use Payum\Paypal\ExpressCheckout\Nvp\Api;
use Payum\Paypal\ExpressCheckout\Nvp\Request\Api\CreateRecurringPaymentProfile;
use Payum\LaravelPackage\Controller\PayumController;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Model\CreditCard;
use Payum\Core\Model\Payment;
use Pheye\Payments\Models\Payment as OurPayment;
use Pheye\Payments\Contracts\PaymentService;
use App\Jobs\SyncPaymentsJob;
use App\Jobs\SyncSubscriptionsJob;
use App\Jobs\LogAction;
use GuzzleHttp\Client;
use App\Jobs\SendUserMail;
use App\Jobs\SendUnsubscribeMail;
use Pheye\Payments\Exceptions\BusinessErrorException;
use Pheye\Payments\Events\PayedEvent;
use Pheye\Payments\Jobs\GenerateInvoiceJob;
use Voyager;
use Illuminate\Support\Collection;

class SubscriptionController extends PayumController
{
    private $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * 目前错误的返回统一以422作为Response返回码
     */
    public function responseError($desc, $code = -1)
    {
        return response(["code"=>$code, "desc"=> $desc], 422);
    }

    /**
     * 生成唯一订单号:16位数字
     */
    public function generateNo()
    {
        return substr(implode("", array_map('ord', str_split(str_random(12), 1))), 0, 16);
    }

    /**
     * 显示支付表单
     */
    public function form(Request $req)
    {
        // 暂时当$planid不存在时重定向到404页面
        if ($req->plan || $req->name) {
            $planid = $req->plan;
            $name = $req->name;
            $plan = Plan::where('id', $planid)->orwhere('slug', $name)->first();// find($planid);
            if (is_null($plan)) {
                return view('errors.404');
            } else {
                $plan->amount = number_format($plan->amount, 2); //价格统一格式：2位小数
                return view('subscriptions.pay', ['plan'=>$plan, 'key' =>  env('STRIPE_PUBLISHABLE_KEY') ]);
            }
        } else {
            return view('errors.404');
        }
    }

    /**
     * 正常来讲，前端已经做了各种错误提示和防止提交无效的coupon，所以后端简化处理，统一提示一致的错误
     */
    protected function checkCoupon($code, $price)
    {
        $coupon = Coupon::where('code', $code)->first();
        if (!$coupon) {
            return false;
        }
        if ($price < $coupon->discount) {
            return false;
        }
        if ($coupon->used >= $coupon->uses) {
            return false;
        }
        if (!$coupon->start  || !$coupon->end) {
            return false;
        }
        $now = new Carbon();
        if ($now->lt(new Carbon($coupon->start)) || $now->gt(new Carbon($coupon->end))) {
            return false;
        }
        return $coupon;
    }
    /**
     * 支付表单提示的处理
     *
     * 如果没有指明支付类型，就使用默认网关
     * @param  plan_name 购买的计划名称
     * @param  coupon coupon值
     * @param  gateway_name 网关名称
     * @param  skype @deprecated
     * @todo 全部移到Service中
     */
    public function pay(Request $req)
    {
        // check that we have nonce and plan in the incoming HTTP request
        if (!$req->has('planid') && !$req->has('plan_name')) {
            // TODO:统一处理
            throw new \ErrorException("invalid request");
            /* return redirect()->back()->withErrors(['desc' => 'Invalid request']); */
        }
        if ($req->has('planid')) {
            $plan = Plan::find(intval($req->input('planid')));
        } else {
            $plan = Plan::where('name', $req->input('plan_name'))->first();
        }
        $user = Auth::user();
        $coupon = null;
        $discount = 0;
        // 如果存在对应的优惠券就使用
        if ($req->has('coupon') && $req->coupon) {
            $coupon = $this->checkCoupon($req->coupon, $plan->amount);
            if (!$coupon) {
                return redirect()->back()->withErrors(['message' => 'Invalid coupon']);
            }
            $discount = $coupon->getDiscountAmount($plan->amount);
            /* if ($coupon->type == 0) { */
            /*     $discount = floor($plan->amount * $coupon->discount / 100); */
            /* } else if ($coupon->type == 1) { */
            /*     $discount = $coupon->discount; */
            /* } */
            if (Subscription::where('quantity', '>', 0)->where('user_id', $user->id)->where('coupon_id', $coupon->id)->count() >= $coupon->customer_uses) {
                return redirect()->back()->withErrors(['message' => 'You have used the coupon']);
            }
        }
        if ($req->has('gateway_name')) {
            $gatewayConfig = GatewayConfig::where('gateway_name', $req->gateway_name)->first();
        } else {
            $gatewayConfig = GatewayConfig::where('gateway_name', Voyager::setting('current_gateway') ? : config('payment.current_gateway'))->first();
        }
        if (!$gatewayConfig) {
            // TODO: 统一定向到某个错误页面或者前端需要知道如何读取Flash数据
            throw new \ErrorException('No GatewayConfig');
        }
        // 创建一个新的订阅(是否要清除已有的未支付订阅呢？需要的，防止数据库被填满，由于Paypal的token可能还在生效，所以在onPay时要确保不能支付成功)
        Subscription::where('user_id', $user->id)->where('status', Subscription::STATE_CREATED)->delete();
        $subscription = new Subscription();
        $subscription->user_id = $user->id;
        $subscription->plan = $plan->name;
        $subscription->quantity = 0;
        $subscription->coupon_id = $coupon ? $coupon->id : 0;
        if ($req->input('onetime', 0)) {
            $subscription->setup_fee = $plan->amount - $discount;
        } else {
            $subscription->setup_fee = $plan->setup_fee - $discount;
        }
        $subscription->frequency = $plan->frequency;
        $subscription->frequency_interval = $plan->frequency_interval;
        $subscription->gateway_id = $gatewayConfig->id;
        $subscription->status = Subscription::STATE_CREATED;
        $subscription->tag = Subscription::TAG_DEFAULT;
        $subscription->skype = $req->input('skype', ''); // 获取skype字段
        $subscription->save();

        if ($req->has('payType') && $req->payType == 'stripe') {
            return $this->payByStripe($req, $plan, $user, $coupon);
        }
        if ($gatewayConfig->factory_name == GatewayConfig::FACTORY_PAYPAL_EXPRESS_CHECKOUT) {
            return $this->preparePaypalCheckout($req, $subscription, $plan, $gatewayConfig);    
        } else if ($gatewayConfig->factory_name == GatewayConfig::FACTORY_ZHONGWAIBAO) {
            return $this->payByZhongwaibao($req, $plan, $subscription, $gatewayConfig);
        }
        $service = $this->paymentService->getRawService(PaymentService::GATEWAY_PAYPAL);
        $approvalUrl = $service->createPayment($plan, $coupon ? ['setup_fee' => $plan->amount - $discount] : null);
        // 由于此时订阅的相关ID没产生，所以没办法通过保存ID，此时就先通过token作为中转
        $queryStr = parse_url($approvalUrl, PHP_URL_QUERY);
        $queryTmpArr = explode("&", $queryStr);
        $queryArr  = [];
        foreach ($queryTmpArr as $key => $item) {
            $t = explode("=", $item);
            $queryArr[$t[0]] = $t[1];
        }

        $subscription->agreement_id = $queryArr['token'];
        $subscription->save();
        return redirect($approvalUrl);
    }

    /**
     * 获取帐单信息
     * 时间限制：
     * 首个订阅的首单成交时间7天之内可以申请1次退款，其他交易不能退款。
     * 7天后所有成功交易可以下载票据
     *
     * @return \App\Payment[]
     */
    public function billings()
    {
        $user = Auth::user();
        $payment = $user->payments()->with('refund')->orderBy('created_at', 'desc')->get();
        // 后续新增的plan 需要往$level数组内按 des, level, plan添加直接让前端使用
        // TODO: 前后端都应该修改，错误的使用方式
        $levels = [
            ['des'  =>  'lite_monthly'              , 'level' => 'Lite',       'plan' => 'Monthly'],
            ['des'  =>  'lite_annual'               , 'level' => 'Lite',       'plan' => 'Annual'],
            ['des'  =>  'lite_quarterly'            , 'level' => 'Lite',       'plan' => 'Quarterly'],
            ['des'  =>  'standard_monthly'          , 'level' => 'Standard',   'plan' => 'Monthly'],
            ['des'  =>  'standard_quarter_monthly'  , 'level' => 'Standard',   'plan' => 'Quarterly'],
            ['des'  =>  'standard'                  , 'level' => 'Standard',   'plan' => 'Annual'],
        ];
        foreach ($payment as $index => $key) {
            $endDate = new Carbon($key->end_date);
            $startDate = new Carbon($key->start_date);
            foreach ($levels as $level) {
                if ($level['des'] == $key->description) {
                    $payment[$index]['level'] = $level['level'];
                    $payment[$index]['plan'] = $level['plan'];
                }          
            }
            $payment[$index]['expireDate'] = $endDate->toFormattedDateString();
            $payment[$index]['startDate'] = $startDate->toFormattedDateString();
        }
        return $payment;
    }

    /**
     * 获取所有计划
     *
     * 默认禁止返回权限相关信息，只有必要时才允许返回
     * @return Role $items 计划列表
     */
    public function plans()
    {
        $items = Role::where('plan', '<>', null)->get();
        /* $items = Role::with('permissions', 'policies')->where('plan', '<>', null)->get(); */
        
        foreach ($items as $key => $item) {
            /* $item->groupPermissions = $item->permissions->groupBy('table_name'); */
            $item->append('plans');
        }
        return $items;
    }

    /**
     * 获取所有计划
     */
    public function getPlans()
    {
        return Plan::all()->makeHidden('paypal_id');
    }

    /**
     * 显示所有服务端计划(主要用于测试目的)
     */
    public function showPlans()
    {
        $service = $this->paymentService->getRawService(PaymentService::GATEWAY_PAYPAL);
        return $service->dropPlans();
    }


    /**
     * 客户同意支付后的回调
     * TODO: onPay这一步，应该考虑支持API请求会比较灵活
     */
    public function onPay(Request $request)
    {
        /* echo "processing...don't close the window."; */
        if ($request->success == 'false') {
            return redirect(Voyager::setting('payed_redirect'));
        }
        $subscription = Subscription::where('agreement_id', $request->token)->first();
        if (!($subscription instanceof Subscription)) {
            abort(500, "no subscription found");
        }
        $service = $this->paymentService->getRawService(PaymentService::GATEWAY_PAYPAL, $subscription->gatewayConfig->config);

        $agreement = $service->onPay($request);

        if (!$agreement) {
            echo "Paypal encountered some error, please <a href='/app/profile?active=0'>Back</a> and retry.";
            return;
        }
        $payer = $agreement->getPayer();
        $info = $payer->getPayerInfo();
        // 中途取消的情况下返回profile页面
        if ($agreement == null) {
            return redirect(Voyager::setting('payed_redirect'));
        }
        $detail = $agreement->getAgreementDetails();
        // abort(401, "error on agreement");
        $subscription->agreement_id = $agreement->getId();
        $subscription->quantity = 1;
        $subscription->status = $subscription->translateStatus($agreement->getState());
        $subscription->remote_status = $agreement->getState();
        $subscription->buyer_email = $info->getEmail();
        $subscription->next_billing_date = $detail->getNextBillingDate();
        $subscription->save();
        $subscription->user->subscription_id = $subscription->id;
        $subscription->user->save();
        if (strtolower($agreement->getState()) != 'active') {
            /* $subscription->user->subscription_id = null; */
            /* $subscription->user->save(); */
            // 如果没有立刻成功，补一个取消订阅的操作
            /* $service->cacnel($subscription); */
            return redirect('/app/profile?active=0&sub=' . $subscription->id);
        }

        $this->paymentService->syncPayments($subscription);
        // 对成功订阅的用户发送帮助邮件, 排除社交登录无有效邮箱和内部测试使用邮箱
        // modify by ruanmingzhi 2017-12-14
        $user = User::find($subscription->user->id);
        if ($user && $user->id > 3 && User::isTestEmail($user->email)) {
            Log::info("help mail sended after subscription");
            dispatch(new SendUserMail($user, new \App\Mail\PayHelpMail($user)));
        }
        // 完成订阅后的/app/profile界面，如果没有成功会自己尝试同步;同时webhook如果有收到，也会去同步。
        /* dispatch((new SyncPaymentsJob($subscription))->delay(Carbon::now()->addSeconds(10))); */
        dispatch((new SyncPaymentsJob($subscription))->delay(Carbon::now()->addSeconds(30)));

        // 更改七日内的统计为guzzle 同步请求
        return redirect(Voyager::setting('payed_redirect'));
    }

    /**
     * 处理Paypal支付的通知
     *
     * @warning 在Sandbox下测试发现，Webhook的webhook机制非常不可靠。要么收不到，要么收到了但是发现验证失败，能成功验证的次数不多。
     */
    public function onPayWebhooks(Request $request)
    {
        Log::info('webhooks id: '. $request->id);
        $service = $this->paymentService->getRawService(PaymentService::GATEWAY_PAYPAL);
        $isValid = $service->verifyWebhook($request);
        /* if (!$isValid) */
        /*     return; */
        $webhook_id = $request->id;//webhook id
        $count = Webhook::where('webhook_id', $webhook_id)->count();
        // webhook有记录说明处理过就不再处理
        if ($count == 0) {
            // 如果是无效的webhook就主从查询（可能是Paypal本身问题返回验证失败，也可能是伪造的），不管哪种情况，主动与Paypal做一次同步即可
            if (!$isValid) {
                switch ($request->event_type) {
                    case 'PAYMENT.SALE.COMPLETED':
                        $agreementId = $request->resource['billing_agreement_id'];
                        $subscription = Subscription::where('agreement_id', $agreementId)->first();
                        if ($subscription) {
                            dispatch(new SyncPaymentsJob($subscription));
                        }
                }
            } else {
                $resource = $request->resource;
                $webhook = new Webhook;
                $webhook->webhook_id = $webhook_id;
                $webhook->create_time = $request->create_time;
                $webhook->resource_type = $request->resource_type;
                $webhook->event_type = $request->event_type;
                $webhook->summary = $request->summary;
                $webhook->webhook_content = base64_encode(serialize($resource));
                $re = $webhook->save();
                Log::info('$webhook->save(): ' . $re);
                switch ($webhook->event_type) {
                    case 'BILLING.SUBSCRIPTION.CANCELLED':
                        break;
                    case 'PAYMENT.SALE.PENDING':
                        // 收到PENDING通常是安全原因引起，买家已付款，但是需要卖家确认才能收到款，暂不处理
                        // TODO: 创建PENDING的Payment，然后在其他状态中对其修改
                        break;
                    case 'PAYMENT.SALE.COMPLETED':
                        // 用户完成支付才切换权限
                        $agreementId = $request->resource['billing_agreement_id'];
                        $subscription = Subscription::where('agreement_id', $agreementId)->first();
                        if (!$subscription) {
                            Log::warning("payment completed, but no `$agreementId` subscription found");
                            break;
                        }
                        dispatch(new SyncPaymentsJob($subscription));
                        break;
                    case 'PAYMENT.SALE.REFUNDED':
                        $payment = OurPayment::where('number', $resource['sale_id'])->first();
                        if (!$payment) {
                            Log::warning("the payment is refunded, but no record in the system");
                            break;
                        }
                        dispatch(new SyncPaymentsJob($payment->subscription));
                        break;
                }
            }
        }
    }

    /**
     * Paypal Express Checkout
     */
    protected function preparePaypalCheckout(Request $request, $subscription, $plan, $gatewayConfig)
    {
        $amount = $subscription->setup_fee;
        if (!$request->has('amount') && !$amount) {
            return "amount parameter is required";
        }
        if (!$amount) {
            $amount = $request->amount;
        }
        // 一次性付款
        if ($request->input('onetime', 0)) {
            $storage = $this->getPayum()->getStorage('Payum\Core\Model\ArrayObject');
            $details = $storage->create();
            $details['PAYMENTREQUEST_0_CURRENCYCODE'] = $plan->currency;
            $details['PAYMENTREQUEST_0_AMT'] = $amount;
            $storage->update($details);
            $captureToken = $this->getPayum()->getTokenFactory()->createCaptureToken($gatewayConfig->gateway_name, $details, 'paypal_done');
        } else {
            $storage = $this->getPayum()->getStorage(AgreementDetails::class);
            $agreement = $storage->create();
            $agreement['PAYMENTREQUEST_0_AMT'] = $amount; // For an initial amount to be charged please add it here, eg $10 setup fee
            $agreement['L_BILLINGTYPE0'] = Api::BILLINGTYPE_RECURRING_PAYMENTS;
            $agreement['L_BILLINGAGREEMENTDESCRIPTION0'] = $plan->desc;
            $agreement['NOSHIPPING'] = 1;
            $agreement->subscription_id = $subscription->id;
            $agreement->plan_id = $plan->id;
            $storage->update($agreement);

            $captureToken = $this->getPayum()->getTokenFactory()->createCaptureToken($gatewayConfig->gateway_name, $agreement, 'paypal_capture');

            $storage->update($agreement);
        }
        return redirect($captureToken->getTargetUrl());
    }

    protected function preparePaypalRestCheckout(Request $request, $amount = null)
    {
        $payment = $this->paymentService->checkout();           
        return redirect($payment->getApprovalLink());
    }

    protected function payByStripe(Request &$req, Plan &$plan, &$user, $coupon)
    {
        if (!$req->has('stripeToken')) {
            return redirect()->back()->withErrors(['message' => 'invalid credit card']);
        }
        $discount = 0;
        if ($coupon) {
            $discount = $coupon->getDiscountAmount($plan->amount);
        }
        $storage = $this->getPayum()->getStorage(Payment::class);
        $payment = $storage->create();
        $payment->setNumber($this->generateNo());
        $payment->setCurrencyCode($plan->currency);
        $payment->setTotalAmount(0);
        $payment->setDescription($plan->display_name);
        $payment->setClientId($user->id);
        $payment->setClientEmail($user->email);
        $payment->setDetails(
            new \ArrayObject(
                [
                    'amount' => ($plan->amount  - $discount) * 100,
                    'currency' => $plan->currency,
                    'card' => $req->stripeToken,
                    'local' => [
                        'save_card' => true,
                        'customer' => ['plan' => $plan->name]
                    ]
                ]
            )
        );
        $storage->update($payment);

        $captureToken = $this->getPayum()->getTokenFactory()->createCaptureToken('stripe', $payment, 'stripe_done');
        return redirect($captureToken->getTargetUrl());
    }

    protected function prepareStripeCheckout(Request $request)
    {
        $user = Auth::user();
        $plan = Plan::where('name', 'standard_monthly')->first();
        return $this->payByStripe($request, $plan, $user);
    }

    public function prepareCheckout(Request $request, $method)
    {
        switch ($method) {
            case 'paypal':
                return $this->preparePaypalCheckout($request);
            case 'paypal_rest':
                return $this->preparePaypalRestCheckout($request);
            case 'stripe':
                return $this->prepareStripeCheckout($request);
            case 'scoinpay':
                return view('subscriptions.scoinpay');
            case 'zhongwaibao':
                return view('payment::subscriptions.zhongwaibao');
        }

        return 'unknown payment method';
    }

    /**
     * Paypl支付完成后的处理路径
     */
    public function onPaypalDone(Request $request)
    {
        $token = $this->getPayum()->getHttpRequestVerifier()->verify($request);
        $gateway = $this->getPayum()->getGateway($token->getGatewayName());
        // 获取最初的Model, 非常重要！！
        $identity = $token->getDetails();
        $model = $this->getPayum()->getStorage($identity->getClass())->find($identity->getId());
        $gateway->execute($status = new GetHumanStatus($token));
        $detail = iterator_to_array($status->getFirstModel());
        Log::info('detail:', ['detail' => $model, 'status' => $status]);
        if (isset($model['BILLINGPERIOD'])) {
            // 循环扣款
            return $this->onPaypalRecurringDone($request, $gateway, $detail, $model);
        } else {
            // 一次性扣款
            return $this->onPaypalOnetimeDone($request, $gateway, $detail, $status);
        }
        /* $payum->getHttpRequestVerifier()->invalidate($token); */
    }

    /**
     * 循环扣款订阅完成
     */
    public function onPaypalRecurringDone(Request $request, $gateway, $detail, $model)
    {
        $subscription = $model->subscription;
        $subscription->agreement_id = $detail['PROFILEID'];
        $subscription->quantity = 1;
        $subscription->details = $detail;
        $subscription->status = 'payed';
        $subscription->buyer_email = $detail['EMAIL'];
        $subscription->save();

        $this->paymentService->syncPayments($subscription);
        $payment = $subscription->payments()->orderBy('created_at', 'desc')->first();
        return $this->afterPay($request, $payment);
    }

    /**
     * 一次性付款的处理
     */
    public function onPaypalOnetimeDone(Request $request,  $gateway, $detail, $status)
    {
        if (!Auth::user()) {
            throw new BusinessErrorException('pay failed, if you have completed payment, please contact us');
        }
        if ($status->getValue() == 'canceled') {
            // 目前统一回到首页，实际上应该是去到信息提示页面，由信息提示页面做进一步的操作
            return redirect('/');   
        }
        if (!($status->getValue() == 'captured')) {
            Log::warning('pay failed', ['user' => Auth::user()->email, 'detail' => $detail]);
            throw new BusinessErrorException('pay failed, please try again');
        }

        if (OurPayment::where('number', $detail['TRANSACTIONID'])->count() > 0) {
            return redirect(Voyager::setting('payed_redirect') ? : '/');
        }
        $user = Auth::user();
        $subscription = $user->subscriptions()->where('status', Subscription::STATE_CREATED)->first();
        //$subscription->agreement_id = '';
        $subscription->quantity = 1;
        $subscription->status = Subscription::STATE_PAYED;
        $subscription->remote_status = '';
        $subscription->buyer_email = $detail['EMAIL'];
        // TODO:总是按月，应该做得更细致些
        $subscription->next_billing_date = Carbon::now()->addMonth();
        $subscription->save();
        $subscription->user->subscription_id = $subscription->id;
        $subscription->user->save();

        $payment = new OurPayment();
        $payment->number = $detail['TRANSACTIONID'];
        $payment->description = '';
        $payment->client_id = $user->id;
        $payment->client_email = $user->email;
        $payment->amount = $detail['AMT'];
        $payment->currency = $detail['PAYMENTINFO_0_CURRENCYCODE'];
        $payment->details = $detail;
        $payment->buyer_email = $detail['EMAIL'];
        $payment->status = OurPayment::STATE_COMPLETED;
        $payment->created_at = Carbon::now();

        $payment->subscription()->associate($subscription);
        $payment->save();

        $subscription->user->fixInfoByPayments();

        dispatch(new GenerateInvoiceJob(new Collection([$payment])));//入参类型为Collection  
        event(new PayedEvent($payment));
        $redirectUrl = Voyager::setting('payed_redirect') ? : '/';
        if ($request->expectsJson()) {
            return response()->json(['redirect' => $redirectUrl]);
        }
        return redirect($redirectUrl);
    }

    public function onStripeDone(Request $request)
    {
        $token = $this->getPayum()->getHttpRequestVerifier()->verify($request);
        $gateway = $this->getPayum()->getGateway($token->getGatewayName());

        $gateway->execute($status = new GetHumanStatus($token));
        $payment = $status->getFirstModel();

        $details = $payment->getDetails();
        if ($status->getValue() == 'captured') {
            // 这整个流程应该是原子操作，应该放在队列中
            $user = User::find($payment->getClientId());
            $planName = $details['local']['customer']['plan'];

            $subscription = Subscription::where('user_id', $user->id)->where(['status' => Subscription::STATE_CREATED, 'gateway' => PaymentService::GATEWAY_STRIPE])->first();
            $subscription->agreement_id = $details['local']['customer']['subscriptions']['data'][0]['id'];
            $subscription->quantity = 1;
            /* $subscription->setup_fee = $details['amount'] / 100; */
        $subscription->save();


            $ourPayment = new OurPayment();
            switch ($status->getValue()) {
                case 'captured':
                    $ourPayment->status = OurPayment::STATE_COMPLETED;
                    break;
                default:
                    $ourPayment->status = $status->getValue();
            }

            $ourPayment->client_id = $payment->getClientId();
            $ourPayment->client_email = $payment->getClientEmail();
            $ourPayment->amount = number_format($details['amount'] / 100, 2);
            $ourPayment->currency = ($payment->getCurrencyCode());
            $ourPayment->setNumber($payment->getNumber());
            $ourPayment->details = $details;
            $ourPayment->description = $payment->getDescription();
            $ourPayment->subscription()->associate($subscription);
            $ourPayment->save();


            $this->paymentService->handlePayment($ourPayment);
        }
        return redirect('/app/profile?active=0');
    }

    public function cancel($id)
    {
        $user = Auth::user();
        $sub = Subscription::where(['id' => $id, 'user_id' => $user->id])->first();
        if ($sub->status == Subscription::STATE_CANCLED) {
            return ['code' => 0, 'desc' => 'success'];
        }
        if ($sub->status != Subscription::STATE_SUBSCRIBED && $sub->status != Subscription::STATE_PAYED) {
            return new Response(['code' => -1, 'desc' => "not a valid state:{$sub->status}"]);
        }
        if (!$this->paymentService->cancel($sub)) {
            return ['code' => -1, 'desc' => "cancel failed"];
        }
        dispatch(new LogAction(ActionLog::ACTION_USER_CANCEL, $sub->toJson(), "", $user->id));
        // 用户申请退订后发送退订邮件到用户邮箱
        // Todo 通用邮件模板合并进去后需要使用通用jOb发送邮件，并删除该多余的job
        dispatch(new SendUnsubscribeMail($user));
        return ['code' => 0, 'desc' => 'success'];
    }

       
    public function sync($sid)
    {
        $user = Auth::user();
        $sub = $user->subscriptions()->where('agreement_id', $sid)->first();
        if (!$sub) {
            return ['code' => -1, 'desc' => "$sid not found"];
        }
        // 如果扣款失败，会自动被取消，因此同步的处理只需要处理pending的情况
        $this->paymentService->syncSubscriptions([], $sub);
        $this->paymentService->syncPayments([], $sub);
        /* if (Carbon::now()->diffInSeconds($sub->updated_at, true) > 30 && $sub->status ==  Subscription::STATE_PENDING) { */
        /*     $this->paymentService->cancel($sub); */
        /*     dispatch(new LogAction(ActionLog::ACTION_AUTO_CANCEL, $sub->toJson(), "", $user->id)); */
        /*     Log::info("{$sub->agreement_id} is auto canceled"); */
        /* } */
        return ['code' => 0, 'desc' => 'success'];
    }

    public function requestRefund($no)
    {
        $user = Auth::user();
        $payment = OurPayment::where('number', $no)->first();
        if (!$payment) {
            return $this->responseError("no such payment $no", -1);
        }
        if ($payment->refund) {
            return $this->responseError("you have request refunding before", -1);
        }
        $refund = $this->paymentService->requestRefund($payment);
        
        return ['code' => 0, 'desc' => 'success'];
    }

    private function curlZhongwaibao($url, $data)
    {
        if (empty($url)) return false;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch ,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch ,CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * 使用中外宝支付
     */
    public function payByZhongwaibao(Request $req, $plan, Subscription $subscription, GatewayConfig $gatewayConfig)
    {
        $user = Auth::user();
        $credit = $user->credit()->first();
        $config = $gatewayConfig->config;
        $data = $req->except(['gateway_name', 'plan_name', 'planid', 'coupon']);

        
        $creditCollection = new Collection($credit->toArray());
        foreach ($creditCollection->except(['id', 'created_at', 'updated_at', 'user_id', 'CardNumber', 'CardMonth', 'CardYear', 'CardCvv']) as $key => $value) {
            $data[$key] = $value;
        }

        $data['MerchantID'] = $config['merchantID'];
        $data['TransNo']    = $config['transNo'];
        $data['OrderID']    = $config['transNo'] . '-' . date("YmdHis",time());
        $data['Currency']   = $plan['currency'];    
        $data['Amount']     = $plan['amount'];
        $data['MD5info']    = strtoupper(md5($config['md5key']. $config['merchantID']. $config['transNo']. $data['OrderID'] . $data['Currency'] . $data['Amount']));                      
        $data['Version']    = 'V4.51';
        // 客户端信息
        // TODO:更新网址信息
        $data['URL']            = 'www.onlineadspyer.com';//$req->server('HTTP_HOST');
        $data['IP']             = $req->server('REMOTE_ADDR');
        $data['UserAgent']      = $req->server('HTTP_USER_AGENT');
        $data['AcceptLanguage'] = $req->server('HTTP_ACCEPT_LANGUAGE');
        $data['McCookie']       = $_COOKIE['McCookie'];
        /* $data['csid']           = $_POST['csid']; */
        $data['Products']       = $plan->display_name;
        // 提交数据到网关                            
        $result = json_decode($this->curlZhongwaibao('http://wru8zys.zwbpay.com/payment/interface/do', $data), true);                                                                                     
        if (!is_array($result)) {                    
            $result = json_decode($this->curlZhongwaibao('http://wru8zys.gtopay.com/payment/interface/do', $data), true);
        }    
        if (!is_array($result)) die('Error Code: 2001');                                          
        if ($result['error'] == true) {              
            throw new \Exception('Error Code: ' . $result['code']);   
        }                                      
        $OrderID  = $result['order']['OrderID']; 
        $Currency = $result['order']['Currency'];
        $Amount   = $result['order']['Amount'];  
        $Code     = $result['order']['Code'];    
        $Status   = $result['order']['Status'];  
        $MD5info  = $result['order']['MD5info'];

        $MD5src  = $config['md5key'] . $config['transNo'] . $OrderID . $Currency . $Amount . $Code . $Status;
        $MD5sign = strtoupper(md5($MD5src));
        if ($MD5sign != $MD5info) 
            throw new \Exception('Verify MAC Failed!');
        if ($Status != 1) {
            throw new \Exception("$OrderID Failed:$Status, $Code");    
        }

        // 付款成功后，信用卡信息首先需要保存下来才能为后续的循环扣款做基础
        $credit['CardNumber'] = $data['CardNumber'];
        $credit['CardMonth'] = $data['CardMonth'];
        $credit['CardYear'] = $data['CardYear'];
        $credit['CardCvv'] = $data['CardCvv'];
        $credit->save();

        $user = Auth::user();

        $subscription->quantity = 1;
        $subscription->status = Subscription::STATE_PAYED;
        $subscription->remote_status = '';
        $subscription->buyer_email = $data['BEmail'];
        // TODO:总是按月，应该做得更细致些
        $subscription->next_billing_date = Carbon::now()->addMonth();
        $subscription->save();
        $subscription->user->subscription_id = $subscription->id;
        $subscription->user->save();

        // 成功需要创建Payment之类的订单
        $payment = new OurPayment();
        $payment->number = $OrderID;
        $payment->description = "$Status $Code";
        $payment->client_id = $user->id;
        $payment->client_email = $user->email;
        $payment->amount = $Amount;
        $payment->currency = $Currency;
        $payment->details = $data;
        $payment->buyer_email = $data['BEmail'];
        $payment->status = OurPayment::STATE_COMPLETED;
        $payment->created_at = Carbon::now();

        $payment->subscription()->associate($subscription);
        $payment->save();

        Log::info('pay detail:', ['detail' => $data]);

        $subscription->user->fixInfoByPayments();

        dispatch(new GenerateInvoiceJob(new Collection([$payment])));//入参类型为Collection  
        event(new PayedEvent($payment));
        $redirectUrl = Voyager::setting('payed_redirect') ? : '/';
        if ($req->expectsJson()) {
            return response()->json(['redirect' => $redirectUrl]);
        }
        return redirect($redirectUrl);
    }

    /**
     * Paypal循环扣款签约
     */
    public function onPaypalCapture(Request $request)
    {
        /** @var \Payum\Core\Payum $payum */
        $payum = $this->getPayum();
        $token = $payum->getHttpRequestVerifier()->verify($request);
        $identity = $token->getDetails();
        $model = $this->getPayum()->getStorage($identity->getClass())->find($identity->getId());
        $payum->getHttpRequestVerifier()->invalidate($token);
        $gateway = $payum->getGateway($token->getGatewayName());
        $agreementStatus = new GetHumanStatus($token);
        $gateway->execute($agreementStatus);

        if (!$agreementStatus->isCaptured()) {
            abort(500, 'HTTP/1.1 400 Bad Request');
        }
        $agreement = $agreementStatus->getModel();
        $gateway->execute(new Sync($agreement));

        $plan = $agreement->plan;
        $storage = $this->getPayum()->getStorage(RecurringPaymentDetails::class);
        $recurringPayment = $storage->create();
        $recurringPayment['TOKEN'] = $agreement['TOKEN'];
        $recurringPayment['DESC'] = $plan->desc; // Desc must match agreement 'L_BILLINGAGREEMENTDESCRIPTION' in prepare.php
        $recurringPayment['EMAIL'] = $agreement['EMAIL'];
        $recurringPayment['AMT'] = $plan->amount;
        // 当使用这种方式去完成初始扣款时，ProfileStatus会处于PendingProfile，这个状态下的
        // Profile无法cancel或者suspend
        /* $recurringPayment['INITAMT'] = $agreement->subscription->setup_fee; */
        /* $recurringPayment['FAILEDINITAMTACTION'] = 'CancelOnFailure'; */
        $recurringPayment['CURRENCYCODE'] = $plan->currency;
        $recurringPayment['BILLINGFREQUENCY'] = $plan->frequency_interval;
        $recurringPayment['PROFILESTARTDATE'] = date(DATE_ATOM);
        $periodMap = [
            'day' => Api::BILLINGPERIOD_DAY, 
            'month' => Api::BILLINGPERIOD_MONTH,
            'year' =>  Api::BILLINGPERIOD_YEAR
        ];
        $recurringPayment['BILLINGPERIOD'] = $periodMap[strtolower($plan->frequency)];
        $recurringPayment->subscription_id = $agreement->subscription_id;
        $recurringPayment->plan_id = $agreement->plan_id;
        $gateway->execute(new CreateRecurringPaymentProfile($recurringPayment));
        $gateway->execute(new Sync($recurringPayment));
        Log::info('subscription', ['sub' => $recurringPayment]);
        $doneToken = $payum->getTokenFactory()->createToken($token->getGatewayName(), $recurringPayment, 'paypal_done');
        return redirect($doneToken->getTargetUrl());
    }

    protected function afterPay (Request $request, $payment)
    {
        if ($payment) {
            $payment->client->fixInfoByPayments();

            dispatch(new GenerateInvoiceJob(new Collection([$payment])));//入参类型为Collection  
            event(new PayedEvent($payment));
        }
        $redirectUrl = Voyager::setting('payed_redirect') ? : '/';
        if ($request->expectsJson()) {
            return response()->json(['redirect' => $redirectUrl]);
        }
        return redirect($redirectUrl);
    }
}
