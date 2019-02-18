<?php
namespace Pheye\Payments\Services;

use Illuminate\Support\Collection;
use Pheye\Payments\Contracts\PaymentService as PaymentServiceContract;
use Pheye\Payments\Models\Plan;
use Pheye\Payments\Models\Payment;
use Pheye\Payments\Models\Subscription;
use Pheye\Payments\Models\Refund;
use Pheye\Payments\Models\GatewayConfig;
use Payum\Stripe\Request\Api\CreatePlan;
use Stripe\Error;
use Stripe\Plan as StripePlan;
use Stripe\Stripe;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Cache;
use Pheye\Payments\Jobs\SyncPaymentsJob;
use Pheye\Payments\Notifications\RefundRequestNotification;
use Pheye\Payments\Notifications\CancelSubOnSyncNotification;
use Payum\Paypal\ExpressCheckout\Nvp\Request\Api\TransactionSearch;
use Pheye\Payments\Exceptions\BusinessErrorException;
use Mpdf\Mpdf;
use Storage;
use Voyager;
use Log;
use File;
use Payum\Core\Request\Cancel;
use Payum\Core\Request\Sync;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Refund as PayumRefund;
use Pheye\Payments\Events\RefundedEvent;
use Pheye\Payments\Events\CancelledEvent;

class PaymentService implements PaymentServiceContract
{
    const LOG_DEBUG = 'LOG_DEBUG';
    const LOG_INFO = 'LOG_INFO';
    const LOG_ERROR = 'LOG_ERROR';
    protected $config;
    protected $logger;
    private $paypalService;

    /**
     * 参数影响支付系统的行为
     * force: true强制与远程同步;false按优化情况与远程同步
     */
    private $parameters;

    public function __construct($config)
    {
        $this->config = $config;
        $this->parameters = new Collection();
        // TODO: stripe的相关内容移到其他地方
        /* Stripe::setApiKey($this->config['stripe']['secret_key']); */
        $this->setParameter(PaymentService::PARAMETER_TAGS, ['default']);
        /* $this->setParameter(PaymentService::PARAMETER_SYNC_RANGE, ['start' => '2017-06-07 14:15:12', 'end' => null]); */
    }

    public function setParameter($key, $val)
    {
        $this->parameters[$key] = $val;
    }

    public function getParameter($key)
    {
        if ($this->parameters->has($key)) {
            return $this->parameters[$key];
        }
        return null;
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * 获取网关的顺序：
     * 1. Voyager::setting('current_gateway')是否是Paypal REST网关
     * 2. 不然就获取第1个Paypal REST配置
     * 3. 不然就黑夜`config/payment.php`的配置
     */
    protected function getPaypalService($config = null, $force = false)
    {
        if (!$this->paypalService || $force) {
            if (!$config) {
                $gatewayConfig = GatewayConfig::where('gateway_name', Voyager::setting('current_gateway'))->first();
                if (!$gatewayConfig) {
                    $gatewayConfig = GatewayConfig::where('factory_name', GatewayConfig::FACTORY_PAYPAL_REST)->first();
                }
                if ($gatewayConfig)
                    $config = $gatewayConfig->config;
            }
            $this->paypalService = new PaypalService($config ? : $this->config['paypal']);
        }
        return $this->paypalService;
    }

    public function getRawService($gateway, $config = null)
    {
        if ($gateway == PaymentService::GATEWAY_PAYPAL) {
            return $this->getPaypalService($config);
        }
        return false;
    }



    /**
     * 根据不同的记录器调用不同方法
     */
    public function log($msg, $level = PaymentService::LOG_DEBUG)
    {
        if ($this->logger instanceof Command) {
            switch ($level) {
                case PaymentService::LOG_DEBUG:
                    $this->logger->comment($msg);
                    break;
                case PaymentService::LOG_INFO:
                    $this->logger->info($msg);
                    break;
                case PaymentService::LOG_ERROR:
                    $this->logger->error($msg);
                    break;
                default:
                    $this->logger->comment($msg);
            }
        } else {
            switch ($level) {
                case PaymentService::LOG_INFO:
                    Log::info($msg);
                    break;
                case PaymentService::LOG_ERROR:
                    Log::error($msg);
                    break;
            }
        }
    }

    public function checkout()
    {
        $payment = $this->getPaypalService()->checkout();
        return $payment;
    }

    protected function syncStripePlans(GatewayConfig $gateway)
    {
        $plans = Plan::all();
        Stripe::setApiKey($gateway->config['secret_key']);
        foreach ($plans as $plan) {
            if (!$plan->amount)
                continue;
            // 如果已经存在就对比，发现不一致就删除，再创建
            $planDesc = [
                "amount" => $plan->amount * 100,
                "interval" => strtolower($plan->frequency),
                'interval_count' => $plan->frequency_interval,
                "name" => $plan->display_name,
                "currency" => strtolower($plan->currency),
                "id" => $plan->name
            ];

            // 有没有更好的机制可以输出更详细的信息？
            $this->log("stripe plan:{$plan->name} is creating");
            try {
                $old = StripePlan::retrieve($planDesc['id']);
                if ($old) {
                    $dirty = false;
                    $oldArray = $old->__toArray(true);
                    foreach ($planDesc as $key => $item) {
                        if ($oldArray[$key] != $item) {
                            $old->delete();
                            $this->log("stripe plan已存在并且{$key}(new:{$item}, old:{$oldArray[$key]})不一致，删除", PaymentService::LOG_INFO);
                            $dirty = true;
                            break;
                        }
                    }
                    if (!$dirty) {
                        $this->log("{$plan->name}已经创建过且无修改,忽略");
                        continue;
                    }
                }
            } catch (\Exception $e) {
            }
            StripePlan::create($planDesc);
            $this->log("{$plan->name} created for stripe");
        }
    }

    protected function syncPaypalRestPlans(GatewayConfig $config)
    {
        $plans = Plan::all();
        $this->log("sync to paypal, this will cost time, PLEASE WAITING...");

        $gatewayConfigs = GatewayConfig::where('factory_name', GatewayConfig::FACTORY_PAYPAL_REST)->get();
        foreach ($gatewayConfigs as $k => $gatewayConfig) {
            $service = $this->getPaypalService($gatewayConfig->config, true);
            $this->log('Gateway Name:' . $gatewayConfig->gateway_name, PaymentService::LOG_INFO);
            $plans->each(
                function ($plan, $key) use ($service) {
                    $this->log("Plan {$plan->name} is creating");
                    //Paypal如果要建立TRIAL用户，过程比较繁琐，这里直接跳过
                    if ($plan->amount == 0) {
                        $this->log("Plan {$plan->name}: ignore free plan in paypal");
                        return;
                    }
                    $paypalPlan = null;
                    if ($plan->paypal_id) {
                        $paypalPlan = $service->getPlan($plan->paypal_id);
                    }
                    if ($paypalPlan) {
                        $merchantPreference = $paypalPlan->getMerchantPreferences();
                        $paymentDef = $paypalPlan->getPaymentDefinitions();
                        $paymentDef = $paymentDef[0];
                        $money = $paymentDef->getAmount();
                        $paypalPlanDesc = [
                            'name' => $paypalPlan->getName(),
                            'display_name' => $paypalPlan->getDescription(),
                            'amount' => $money->getValue(),
                            'currency' => $money->getCurrency(),
                            'frequency' => $paymentDef->getFrequency(),
                            'frequency_interval' => $paymentDef->getFrequencyInterval()
                        ];
                        $isDirty = false;
                        foreach ($paypalPlanDesc as $key => $val) {
                            if (strtolower($plan[$key]) != strtolower($val)) {
                                $this->log("remote paypal diff with local ({$key}):{$plan[$key]} $val");
                                $service->deletePlan($plan->paypal_id);
                                $isDirty = true;
                                break;
                            }
                        }
                        if (!$isDirty) {
                            $this->log("{$plan->name}已经创建过且无修改,忽略");
                            return;
                        }
                    }
                    $output = $service->createPlan($plan);
                    $plan->paypal_id = $output->getId();
                    $plan->save();
                    $this->log("{$plan->name} created for paypal");
                }
            );
        }
        $this->log("paypal sync done");
    }
    
    /**
     * {@inheritDoc}
     * @remark 同步计划没有考虑到试用期, 建立费用，延迟时间
     */
    public function syncPlans(array $gateways)
    {
        $gateways = new Collection($gateways);

        foreach ($gateways as $gateway) {
            switch ($gateway->factory_name) {
            case GatewayConfig::FACTORY_STRIPE:
                $this->syncStripePlans($gateway);
                break;
            case GatewayConfig::FACTORY_PAYPAL_REST:
                $this->syncPaypalRestPlans($gateway);
                break;
            default:
                throw new \Exception("not implemented");
            }
        }
    }


    /**
     * {@inheritDoc}
     * @todo 同步远程的Paypal, stripe
     */
    public function syncSubscriptions(array $gateways = [], $subscription)
    {
        // 正常应该是从远程同步，以确定status状态，从本地同步是错误的方法
        // 特别是frequency_interval和frequency,它们的目的就是为了防止本地Plan修改后
        // 影响到已经完成的订阅，所以一定不能从本地Plan去同步。

        // Stripe的同步
        // TODO:

        // Paypal的同步
        $tags = $this->getParameter(PaymentService::PARAMETER_TAGS);
        if ($subscription) {
            if (is_array($subscription) || $subscription instanceof Collection) {
                $subs = $subscription;
            } else {
                $subs = new Collection([$subscription]);
            }
            $force = true;
        } else {
            $subs = Subscription::where('gateway', 'paypal')->where('status', '<>', Subscription::STATE_CREATED)->where('status', '<>', '')->whereIn('tag', $tags)->get();
            $force = $this->getParameter(PaymentService::PARAMETER_FORCE);
        }
        $paypalService = $this->getPaypalService();
        $this->log("sync to paypal, this may take long time...({$subs->count()})");
        foreach ($subs as $sub) {
            if (!in_array($sub->tag, $tags)) {
                $this->log("{$sub->agreement_id} tag is {$sub->tag}, not in tags, skip");
                continue;
            }
            if (strlen($sub->agreement_id) < 3) {
                continue;
            }
            if ($sub->status == Subscription::STATE_CANCLED && !$force) {
                $this->log("skip cancelled subscription {$sub->agreement_id}");
                $this->checkSubscription($sub);
                continue;
            }
            $this->log("handling {$sub->agreement_id}");
            $remoteSub = $paypalService->subscription($sub->agreement_id);
            if (!$remoteSub) {
                $this->log($sub->agreement_id . " is not found");
                continue;
            }
            $plan = $remoteSub->getPlan();
            $def = $plan->getPaymentDefinitions()[0];

            $detail = $remoteSub->getAgreementDetails();
            $payer = $remoteSub->getPayer();
            $info = $payer->getPayerInfo();
            $nextBillingDate = $detail->getNextBillingDate();
            $newData = [
                'frequency' => $def->getFrequency(),
                'frequency_interval' => $def->getFrequencyInterval(),
                'remote_status' => $remoteSub->getState(),
                'buyer_email' => $info->getEmail(),
                'next_billing_date' => $nextBillingDate ? new Carbon($nextBillingDate) : null
            ];
            $state = $remoteSub->getState();
            $newStatus = '';
            switch (strtolower($state)) {
                case 'pending':
                    $newStatus = Subscription::STATE_PENDING;
                    break;
                case 'active':
                    if ($sub->payments()->count() > 0) {
                        $newStatus = Subscription::STATE_PAYED;
                    } else {
                        $newStatus = Subscription::STATE_SUBSCRIBED;
                    }
                    break;
                case 'cancelled':
                    $newStatus = Subscription::STATE_CANCLED;
                    //当订阅退订时获取其退订时间
                    $cancelTime = $this->getCancelledTime($sub->agreement_id);
                    if ($cancelTime) {
                        $newData['canceled_at'] = $cancelTime;
                    } else {
                        $this->log("cannot get cancel time on {$sub->agreement_id},but subscription in paypal was cancelled", PaymentService::LOG_INFO);
                    }
                    break;
                case 'suspended':
                    $newStatus = Subscription::STATE_SUSPENDED;
                    break;
            }
            // 一个用户只能有一个激活的订阅，其他订阅应该设置被取消或挂起，采用通知操作，由管理员确认后手动取消。
            if ($sub->user->subscription_id != $sub->id && strtolower($state) == 'active') {
                $this->log("{$sub->agreement_id} is not {$sub->user->email}'s active subscrition, now send email to notify admin to cancel it", PaymentService::LOG_INFO);
                /* if ($paypalService->suspendSubscription($sub->agreement_id)) */
                /*     $newStatus = Subscription::STATE_SUSPENDED; */
                // $sub->user->notify(new CancelSubOnSyncNotification($sub));
            }
            if (!empty($newStatus)) {
                $newData['status'] = $newStatus;
            }
            $isDirty = false;
            foreach ($newData as $key => $val) {
                if ($sub[$key] != $val) {
                    $this->log("{$key}: old {$sub[$key]}, new: $val", PaymentService::LOG_INFO);
                    $isDirty = true;
                    $sub[$key] = $val;
                }
            }
            if ($isDirty) {
                $sub->save();
                $this->checkSubscription($sub);
            } else {
                $this->log("{$sub->agreement_id} has no change");
            }
        }
        /* $this->log("sync to paypal, this will cost time, PLEASE WAITING..."); */
        /* foreach ($subs as $sub) { */
        /*     $remoteSub = $paypalService->subscription($sub->agreement_id); */
        /* } */
    }


    /**
     * 同步Paypal REST API的支付
     */
    public function syncPaypalRestPayments (Subscription $item)
    {
        $service = $this->getPaypalService();
        if (!in_array($item->tag, $tags)) {
            $this->log("{$item->agreement_id} tag is {$item->tag}, not in tags, skip");
            return;
        }
        // 未完成订阅直接忽略
        if ($item->status == Subscription::STATE_CREATED) {
            return;
        }
        if ($item->status == Subscription::STATE_CANCLED && !$force) {
            $this->log("skip cancelled subscription {$item->agreement_id}");
            return;
        }
        $this->log("sync payments from paypal agreement:"  . $item->agreement_id);
        $transactions = $service->transactions($item->agreement_id);
        if ($transactions == null) {
            return;
        }
        foreach ($transactions as $t) {
            $amount = $t->getAmount();
            if ($amount == null) {
                return;
            }

            $carbon = new Carbon($t->getTimeStamp(), $t->getTimeZone());
            $carbon->tz = Carbon::now()->tz;

            $isDirty = false;
            $paypalStatus = strtolower($t->getStatus());
            $payment = Payment::firstOrNew(['number' => $t->getTransactionId()]);
            $payment->number = $t->getTransactionId();
            $payment->description = $item->plan;
            $payment->client_id = $item->user->id;
            $payment->client_email = $item->user->email;
            $payment->amount = $amount->getValue();
            $payment->currency = $amount->getCurrency();
            $payment->subscription()->associate($item);
            $payment->details = $t->toJSON();
            $payment->created_at = $carbon;

            // TODO: 该代码主要解决早期buyer_email为空的问题，应该直接赋值，移除判断
            if (empty($payment->buyer_email)) {
                $payment->buyer_email = $t->getPayerEmail();
                $isDirty = true;
            } else {
                $payment->buyer_email = $t->getPayerEmail();
            }
            // 当状态变化时要更新订单
            if ($paypalStatus != $payment->status) {
                $this->log("status will change:{$payment->status} -> $paypalStatus", PaymentService::LOG_INFO);

                $isDirty = true;
                switch ($paypalStatus) {
                case 'completed':
                    if ($payment->refund && $payment->refund->isRefunding()) {
                        $payment->status = Payment::STATE_REFUNDING;
                        $this->log("the payment is refunding, change to `refunding` instead of `completed`", PaymentService::LOG_INFO);
                    } else {
                        $payment->status = Payment::STATE_COMPLETED;
                    }
                    break;

                default:
                    // 刚好我们的状态名称与Paypal一致，如果发现不一致需要一一转换
                    $payment->status = $paypalStatus;
                }
                if ($payment->status == Payment::STATE_COMPLETED) {
                    $this->log("handle payment...", PaymentService::LOG_INFO);
                }
            }

            if ($isDirty) {
                $payment->save();
                $this->handlePayment($payment);
                $this->log("payment {$payment->number} is synced", PaymentService::LOG_INFO);
            } else {
                $this->log("payment {$payment->number} has no change", PaymentService::LOG_INFO);
            }

            // 补全退款申请单和根据退款状态处理用户状态
            if ($payment->status == Payment::STATE_REFUNDED) {
                $this->log("generate refund on payment $payment->number", PaymentService::LOG_INFO);
                $refund = $payment->refund;
                if (!$refund) {
                    $this->log("payment $payment->number dont have refund record", PaymentService::LOG_INFO);
                    $refund = new Refund();
                    $refund->amount = $payment->amount;
                    $refund->note = "auto synced refunds";
                    $refund->status = Refund::STATE_ACCEPTED;
                    $refund->payment_id = $payment->id;//payment()->associate($payment);
                    $refund->refunded_at = $this->getRefundedCompletedTime($payment->number);// 获取交易的退款<<完成>>时间
                    $res = $refund->save();
                    $this->log("generate refund automatically:$res", PaymentService::LOG_INFO);
                }
                $this->log("payment $payment->number has refund record", PaymentService::LOG_INFO);
                if ($refund->status != Refund::STATE_ACCEPTED) {
                    $this->log("but status is not accept,now change to it", PaymentService::LOG_INFO);
                    $refund->status = Refund::STATE_ACCEPTED;
                    $refund->refunded_at = $this->getRefundedCompletedTime($payment->number);// 获取交易的退款<<完成>>时间
                    $refund->save();
                    $this->log("payment $payment->number refunded_at: $refund->refunded_at", PaymentService::LOG_INFO);
                } else {
                    if (is_null($refund->refunded_at)) {
                        // 用于填充旧记录中refunded_at为空的记录
                        $this->log("save refunded_at to payment(number: $payment->number)", PaymentService::LOG_INFO);
                        $refund->refunded_at = $this->getRefundedCompletedTime($payment->number);// 获取交易的退款<<完成>>时间
                        $refund->save();
                    }
                }
                $this->handleRefundedPayment($payment);
            }
            if ($payment->status == Payment::STATE_COMPLETED && is_null($payment->invoice_id)) {
                // 如果票据未生成执行生成
                dispatch((new \App\Jobs\GenerateInvoiceJob(Payment::where('number', $payment->number)->get())));//入参类型为Collection
            }
        }
        // 根据用户过期时间规划是否在指定时间同步该订阅的订单
        if ($item->status == Subscription::STATE_PAYED) {
            $this->autoScheduleSyncPayments($item);
        }

    }

    /**
     * 同步Paypal EC循环扣款的订单
     * @warning 只考虑5年内的订单
     */
    public function syncPaypalECPayments(Subscription $subscription)
    {
        if (!$subscription->agreement_id)
            return;
        $this->log("sync {$subscription->agreement_id}");
        $payum = app('payum');
        $gateway = $payum->getGateway($subscription->gatewayConfig->gateway_name);
        // 首次付款的支付账单
        $model['TOKEN'] = $subscription->details['TOKEN'];
        $gateway->execute($status = new Sync($model));
        $model = $status->getFirstModel();
        if (isset($model['TRANSACTIONID'])) {
            $transactionId = $model['TRANSACTIONID'];
            $model = [];
            $model['STARTDATE'] = Carbon::now()->subYears(5)->toIso8601String();
            $model['TRANSACTIONID'] = $transactionId;
            $gateway->execute($status = new TransactionSearch($model));
            $model = $status->getFirstModel();
            $number = $model['TRANSACTIONID'];
            if ($model['L_TYPE0'] == 'Payment') {
                $idx = 0;
            } else {
                $idx = 1;
            }
            // 重复代码过多，必须优化
            $payment = Payment::firstOrNew(['number' => $number]);
            $payment->number = $number;
            $payment->description = '';
            $payment->buyer_email = $model["L_EMAIL{$idx}"];
            $payment->client_id = $subscription->user->id;
            $payment->client_email = $subscription->user->email;
            $payment->amount = $model["L_AMT{$idx}"];
            $payment->currency = $model["L_CURRENCYCODE{$idx}"];
            $payment->subscription()->associate($subscription);
            $payment->details = (array)$model;
            $payment->status = strtolower($model["L_STATUS{$idx}"]);
            $payment->created_at = Carbon::createFromTimeString($model["L_TIMESTAMP{$idx}"]);

            switch ($payment->status) {
            case 'completed':
                if ($payment->refund && $payment->refund->isRefunding()) {
                    $payment->status = Payment::STATE_REFUNDING;
                    $this->log("the payment is refunding, change to `refunding` instead of `completed`", PaymentService::LOG_INFO);
                } else {
                    $payment->status = Payment::STATE_COMPLETED;
                }
                break;
            }
            $payment->save();
            // TODO:每次同步都重新计算一次优惠券其实是没必要的，应优化
            $this->calcCouponUsed($subscription);
            if ($payment->status == Payment::STATE_COMPLETED) {
                $this->generateInvoice($number);
            }
        }
        // 循环扣款的支付账单
        $model = [];
        $model['PROFILEID'] = $subscription->agreement_id;
        $model['STARTDATE'] = Carbon::now()->subYears(5)->toIso8601String();
        $gateway->execute($status = new TransactionSearch($model));
        $model = $status->getFirstModel();
        $idx = 1;
        Log::debug('test', ['data' => (array)$model]);
        while (isset($model["L_TRANSACTIONID{$idx}"])) {
            if (!isset($model["L_EMAIL{$idx}"])) {
                $idx++;
                continue;
            }
            $number = $model["L_TRANSACTIONID{$idx}"];
            $payment = Payment::firstOrNew(['number' => $number]);
            $payment->number = $number;
            $payment->description = '';
            $payment->buyer_email = $model["L_EMAIL{$idx}"];
            $payment->client_id = $subscription->user->id;
            $payment->client_email = $subscription->user->email;
            $payment->amount = $model["L_AMT{$idx}"];
            $payment->currency = $model["L_CURRENCYCODE{$idx}"];
            $payment->subscription()->associate($subscription);
            $payment->details = (array)$model;
            $payment->status = strtolower($model["L_STATUS{$idx}"]);
            $payment->created_at = Carbon::createFromTimeString($model["L_TIMESTAMP{$idx}"]);
            $payment->save();
            switch ($payment->status) {
            case 'completed':
                if ($payment->refund && $payment->refund->isRefunding()) {
                    $payment->status = Payment::STATE_REFUNDING;
                    $this->log("the payment is refunding, change to `refunding` instead of `completed`", PaymentService::LOG_INFO);
                } else {
                    $payment->status = Payment::STATE_COMPLETED;
                }
                break;
            }
            // TODO:每次同步都重新计算一次优惠券其实是没必要的，应优化
            $this->calcCouponUsed($subscription);
            if ($payment->status == Payment::STATE_COMPLETED) {
                $this->generateInvoice($number);
            }
            $idx++;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function syncPayments($subscription = null)
    {
        // 目前只有Paypal需要同步支付记录, stripe是立即获取的
        $res = [];
        $tags = $this->getParameter(PaymentService::PARAMETER_TAGS);
        if (is_array($subscription)|| $subscription instanceof Collection) {
            $subscriptions = $subscription;
            $force = true;
        } elseif ($subscription instanceof Subscription) {
            $subscriptions = [$subscription];
            $force = true;
        } else {
            $subscriptions = Subscription::where('quantity', '>', 0)->whereIn('tag', $tags)->get();
            $force = $this->getParameter(PaymentService::PARAMETER_FORCE);
        }
        foreach ($subscriptions as $item) {
            switch ($item->gatewayConfig->factory_name) {
            case GatewayConfig::FACTORY_PAYPAL_REST:
                $this->syncPaypalRestPayments($item);
                break;
            case GatewayConfig::FACTORY_PAYPAL_EXPRESS_CHECKOUT:
                $this->syncPaypalECPayments($item);
                break;
            }
        }
    }

    /**
     * 同步用户的订阅与支付订单、
     *
     * @param Array $users 用户列表
     */
    public function syncUsers(array $users = [])
    {
    }

    /**
     * 对于退款支付订单满足以下条件，则订阅会被取消：
     * 1. 退款订意属于活动订阅
     * 2. 活动订阅没有其他有效订单
     * 用户权限切换到Free。
     *
     * @remark 如果先取消订阅再发起退款呢？这种情况不能忽略。否则会出现用户取消订阅了，钱也退了，但是权限还在。
     *
     * @return bool
     */
    public function handleRefundedPayment(Payment $payment)
    {
        $user = $payment->subscription->user;
        if ($payment->status != Payment::STATE_REFUNDED) {
            $this->log('=========payment->status != Payment::STATE_REFUNDED===========', PaymentService::LOG_INFO);
            return false;
        }
        if ($payment->subscription->hasEffectivePayment()) {
            $this->log('=========$payment->subscription->hasEffectivePayment()===========', PaymentService::LOG_INFO);
            return false;
        }
        $this->log("{$user->email}'s subscription is refunded:{$payment->number} . User's fixInfoByPayments will executed, please check this user's role is correct by checking the log file or database.", PaymentService::LOG_INFO);
        //modify by chenxin 20171114,修复了Issue #36
        if ($payment->subscription->isActive()) {
            $this->cancel($payment->subscription);
        }
        $user->fixInfoByPayments();
        return true;
    }

    /**
     * 用户一旦使用coupon，就需要对coupon的使用情况重新计算
     */
    private function calcCouponUsed(Subscription $subscription) {
        if ($subscription->coupon_id > 0) {
            $newUsed = $subscription->coupon->calcUsed();
            if ($subscription->coupon->used != $newUsed) {
                $subscription->coupon->used = $newUsed;
                $subscription->coupon->save();
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function handlePayment(Payment $payment)
    {
        $subscription = $payment->subscription;

        $this->calcCouponUsed($subscription);
        if ($subscription->status == Subscription::STATE_PAYED) {
            $this->log("You haved payed for the subscription, check if it's the next payment");
            return $subscription->user->fixInfoByPayments();
        }

        // 添加payment记录和修改subscription状态
        $subscription->status = Subscription::STATE_PAYED;
        $subscription->save();

        // 切换用户计划
        $subscription->user->fixInfoByPayments();
        $this->log("handle user {$subscription->user->email} payments,fixInfoByPayments() exec finish", PaymentService::LOG_INFO);
    }



    /**
     * 自动规划当用户到期时间快到时，对活动订阅的订单同步，以解决循环扣款不能及时检测到的问题。
     * 只要是正常操作，用户就不会过期。如果出现订阅正常扣款，但是用户过期的情况，由用户联系客户手动处理。
     * 不在此处考虑范围内。
     *
     * @param Subscription $subscription 需要同步的订阅
     *
     * @return object 同步订单任务加入队列
     */
    public function autoScheduleSyncPayments(Subscription $subscription)
    {
        $user = $subscription->user;
        if ($subscription->status != Subscription::STATE_PAYED || $subscription->id != $user->subscription_id) {
            return;
        }
        if ($user->inWhitelist()) {
            return;
        }
        $key = "schedule-subscription-" . $subscription->id;
        if (Cache::has($key)) {
            $this->log("{$subscription->agreement_id} has scheduled, ignore");
            return;
        }
        $this->log("on schedule checking...");
        $carbon = new Carbon($user->expired);
        // 7天及以内过期的用户，在过期前几个小时检查订单状态
        if ($carbon->gt(Carbon::now()) && Carbon::now()->diffInDays($carbon, false) <= 10) {
            $scheduleTime = $carbon->subHours(5);
            // 对于在5小时内就要过期的订单，1分钟后就立刻执行
            if ($scheduleTime->lt(Carbon::now())) {
                $scheduleTime = Carbon::now()->addMinutes(1);
            }
            $this->log("schedule {$subscription->agreement_id} at " . $scheduleTime->toDateTimeString(), PaymentService::LOG_INFO);
            dispatch((new \App\Jobs\SyncPaymentsJob($subscription))->delay($scheduleTime));
            Cache::put($key, $subscription->agreement_id, $scheduleTime);// 自动过期
        }
    }

    /**
     * 检查订阅是否符合系统设计
     * 当订阅取消时，如果是活动订阅，则当前用户的活动订阅应该清空
     *
     * @param object $subscription 订阅
     *
     * @return object
     */
    private function checkSubscription($subscription)
    {
        if ($subscription->status == Subscription::STATE_CANCLED) {
            // 对于活动订阅，解除用户的当前订阅
            if ($subscription->isActive() && !$subscription->hasEffectivePayment()) {
                $this->log("{$subscription->user->email}'s subscription set to null", PaymentService::LOG_INFO);
                $user = $subscription->user;
                $user->subscription_id = null;
                $user->save();
            }
        }
    }

    /**
     * 退订，执行完后同步订阅，重置权限
     *
     * @param Subscription $subscription 要退订的订阅
     *
     * @return bool
     */
    public function cancel(Subscription $subscription)
    {
        if ($subscription->status == Subscription::STATE_CANCLED) {
            return true;
        }
        if (!$subscription->canCancel()) {
            return false;
        }
        $isOk = false;
        $gatewayConfig = $subscription->gatewayConfig;

        if ($gatewayConfig->factory_name === GatewayConfig::FACTORY_STRIPE) {
            Stripe::setApiKey($gatewayConfig->config['secret_key']);
            $stripeSub = \Stripe\Subscription::retrieve($subscription->agreement_id);
            $res = $stripeSub->cancel();
            if ($res["status"] == \Stripe\Subscription::STATUS_CANCELED) {
                $isOk = true;
            }
        }

        if ($gatewayConfig->factory_name === GatewayConfig::FACTORY_PAYPAL_REST) {
            $res = $this->getPaypalService()->cancelSubscription($subscription->agreement_id);
            if ($res) {
                $isOk = true;
            }
            $this->syncSubscriptions([], $subscription);
        } 

        if ($gatewayConfig->factory_name == GatewayConfig::FACTORY_PAYPAL_EXPRESS_CHECKOUT) {
            $payum = app('payum');
            $model = $subscription->details;
            $gateway = $payum->getGateway($subscription->gatewayConfig->gateway_name);
            $gateway->execute(new Cancel($model));
            $gateway->execute($status = new Sync($model));
            /* $gateway->execute($status = new GetHumanStatus($model)); */
            $model = $status->getFirstModel();
            if ($model['STATUS'] == 'Cancelled') {
                $subscription->canceled_at = Carbon::now();
                $isOk = true;
            }
        }

        if ($isOk) {
            $subscription->status = Subscription::STATE_CANCLED;
            $subscription->save();

            $this->checkSubscription($subscription);
            event(new CancelledEvent($subscription));
        }
        return $isOk;
    }

    /**
     * 退款请求
     * 在退款表插入一条初始记录，由管理员在后台批准后执行退款
     * 除非删掉该记录，否则用户不能再次发起退款申请
     *
     * @param Payment $payment 需要退款的交易
     * @param double  $amount  价格，可以部分退款
     *
     * @return Refund $refund
     */
    public function requestRefund(Payment $payment, $amount = 0)
    {
        if ($payment->status != Payment::STATE_COMPLETED) {
            return false;
        }
        if (!$amount) {
            $amount = $payment->amount;
        }
        // 状态转移由Model监听完成
        $refund = new Refund();
        $refund->amount = $amount;
        $refund->note = "";
        $refund->status = Refund::STATE_CREATED;
        $refund->payment()->associate($payment);
        $refund->save();
        Log::info("{$payment->number} is on refunding");
        $admin = \App\User::where('email', env('ADMIN_EMAIL'))->first();
        if ($admin instanceof \App\User) {
            $admin->notify(new RefundRequestNotification($refund));
        }
        return $refund;
    }

    /**
     * 退款
     * 调用paypal api执行退款，成功后更新refund记录并同步交易和权限
     *
     * @param Refund $refund 退款记录
     *
     * @return bool
     *
     * @todo stripe退款
     */
    public function refund(Refund $refund)
    {
        if ($refund->status == Refund::STATE_ACCEPTED) {
            return true;
        }
        $amount = $refund->amount;
        $payment = $refund->payment;
        $this->log("handle refunding {$payment->number}...", PaymentService::LOG_INFO);
        $paypalService = $this->getPaypalService();
        $gatewayConfig = $payment->subscription->gatewayConfig;
        $refunded = false;

        if ($gatewayConfig->factory_name === GatewayConfig::FACTORY_STRIPE) { 
            $id = $payment->details['id'];
            if (strpos($id, 'sub_') !== FALSE) {
                $refunded = true;

            } else {
                Stripe::setApiKey($gatewayConfig->config['secret_key']);
                $r = \Stripe\Refund::create([
                    'charge' => $payment->details['id'],
                    'amount' => $refund->amount * 100
                ]);
                Log::info('stripe refunded', ['refund' => $r]);
                if ($r['status'] == 'succeeded') {
                    $refunded = true;
                }
            }
            if ($refunded) {
                $refund->status = Refund::STATE_ACCEPTED;
                $refund->save();
                $refund->payment->client->fixInfoByPayments();
            }
        } else if ($gatewayConfig->factory_name === GatewayConfig::FACTORY_PAYPAL_EXPRESS_CHECKOUT) {

            $payum = app('payum');
            $model = [];
            $model['TRANSACTIONID'] = $payment->number;
            if ($amount != $payment->amount) {
                $model['REFUNDTYPE'] = 'Partial';
                $model['AMT'] = $amount;
            }
            $gateway = $payum->getGateway($gatewayConfig->gateway_name);
            $gateway->execute($status = new PayumRefund($model));
            $model = $status->getFirstModel();
            if ($model['ACK'] == 'Success' || $model['L_ERRORCODE0'] == 10009) {
                $this->log("{$payment->number} refunded");
                $refund->status = Refund::STATE_ACCEPTED;
                $refund->save();
                $refund->payment->client->fixInfoByPayments();
                $refunded = true;
            }
        } else if ($gatewayConfig->factory_name == GatewayConfig::FACTORY_ZHONGWAIBAO) {
            Log::info('信用卡直接返回退款成功，管理员请到对应后台去完成实际退款');
            $refund->status = Refund::STATE_ACCEPTED;
            $refund->save();
            $refund->payment->client->fixInfoByPayments();
            $refunded = true;
        } else if ($gatewayConfig->factory_name === GatewayConfig::FACTORY_PAYPAL_REST && !$gatewayConfig->config['checkout']) {
            // 退款成功后，应该同步该订单的状态，同时及时修正用户权限
            $paypalRefund= $paypalService->refund($payment->number, $payment->currency, $amount);
            if (!$paypalRefund) {
                return false;
            }
            switch (strtolower($paypalRefund->getState())) {
                case 'pending':
                    $refund->status = Refund::STATE_PENDING;
                    break;
                case 'completed':
                    $refund->status = Refund::STATE_ACCEPTED;
                    $refund->refunded_at = $this->getRefundedCompletedTime($payment->number);// 获取交易的退款<<完成>>时间
                    break;
                case 'canceled':
                case 'failed':
                    // 退款失败，状态不变
                    return false;
            }
            $refund->save();
            // 退款成功后，立刻与服务器同步订单状态，确保订单是处于订款状态
            $this->syncPayments([], $payment->subscription);
            /* $this->handleRefundedPayment($payment); */
            // 当处于pending时，10秒后同步防止钱退了，没将用户权限切回去
            // 或者退完成了，但是在handleRefundedPayment取消用户订阅时跟Paypal通信出错
            dispatch((new SyncPaymentsJob($payment->subscription))->delay(Carbon::now()->addSeconds(10)));
            $refunded = true;
        } else {
            $this->log("refund failed:unsupported gateway {$gatewayConfig->factory_name}");
        }
        if ($refunded) {
            event(new RefundedEvent($refund));
        }
        return $refunded; 
    }
    /**
     * @param 参数是paypal买家邮箱
     * 然后查他的退款历史次数，即他之前是否退款过多次
     * @return count
     */
    public function getRefundHistoryCount($buyer_email)
    {
        $count = Payment::where('buyer_email', $buyer_email)
            ->where('status', Payment::STATE_REFUNDED)->count();
        return $count;
    }
    /**
     * @param 参数是指定的Subscriptions
     * 为了防止重复订购，需要判断用户是否存在Active的订阅，且处于延迟扣款情况
     * 是这种情况，则返回true
     * @return bool
     */
    public function onFailedRecurringPayments($subscription)
    {
        if (!$subscription) {
            return false;
        }
        if (is_array($subscription) || $subscription instanceof Collection) {
            $subs = $subscription;
        } else {
            $subs = new Collection([$subscription]);
        }
        $nowTime = Carbon::now();
        $longestEndDate = Carbon::createFromDate(1970, 1, 1);
        foreach ($subs as $sub) {
            if ($sub->status != Subscription::STATE_PAYED) {
                continue;
            }
            $payments = $sub->payments;
            //把这个订阅的所有payment循环一下，得到最新的那个payment的过期时间
            foreach ($payments as $payment) {
                if ($payment->status != Payment::STATE_COMPLETED) {
                    continue;
                }
                $endDate = new Carbon($payment->end_date);
                //取得最长的过期时间
                if ($endDate->gt($longestEndDate)) {
                    $longestEndDate = $endDate;
                }
            }
            //建个新变量，存放11天后的日期，因为paypal规定，最多延迟10天，超过10天还扣款失败，会把订阅取消
            //How reattempts on failed recurring payments work
            //https://developer.paypal.com/docs/classic/paypal-payments-standard/integration-guide/reattempt_failed_payment/?mark=fail#how-reattempts-on-failed-recurring-payments-work
            $longestEndDatePlus = $longestEndDate;
            $longestEndDatePlus->addDay(11);
            //既要已经过期，又要加11天没过期
            if($longestEndDate->lt($nowTime) && $longestEndDatePlus->gt($nowTime)){
                return true;
            }
        }
        return false;
    }

    /**
     * 获取订阅的退订时间
     * 使用订阅id获取交易列表，取退订的时间，转换时区后返回
     *
     * @param string $agreementId 订阅Id,I-XXX
     *
     * @return datetime 退订时间，经过时区转换过的
     *
     * @author ChenTeng <shanda030258@hotmail.com>
     *
     * @todo 这个方法只适用于paypal的订阅，stripe订阅要另外写，或者补充
     */
    public function getCancelledTime($agreementId)
    {
        if (Subscription::where('agreement_id', $agreementId)->value('status') != Subscription::STATE_CANCLED) {
            $this->log("check status in database,this subscription(agreement id: $agreementId) is not a canceled subscription", PaymentService::LOG_INFO);
            //return false;
        }
        $service = $this->getPaypalService();
        $transactions = $service->transactions($agreementId);
        if (!$transactions || empty($transactions)) {
            $this->log("cannot find transaction list with $agreementId on use paypal api", PaymentService::LOG_INFO);
            return false;
        }
        foreach ($transactions as $key => $trans) {
            $trans_status = strtolower($trans->getStatus());
            if ($trans_status == Subscription::STATE_CANCLED) {
                $cancelTime = Carbon::parse($trans->getTimeStamp())->timezone(Carbon::now()->tz)->toDateTimeString();
            } elseif ($trans_status == Subscription::STATE_FAILED && $key === 1) {
                // 如果查询到的交易列表返回数组中第二个的状态是failed,那么就是首单收款失败，订阅会被paypal退订处理(创建订阅时配置)
                $this->log("check status in paypal,subscription(agreement id: $agreementId) has first payment failed,status is canceled(auto change by paypal)", PaymentService::LOG_INFO);
            }
        }
        if (isset($cancelTime)) {
            return $cancelTime;
        } else {
            $this->log("check status in paypal,this subscription(agreement id: $agreementId) is not a canceled subscription", PaymentService::LOG_INFO);
            return false;
        }
    }

    /**
     * 获取交易的退款时间
     * 如果使用searchTransaction api，只能获取到交易状态从completed转变成refunded的时间，或者说是refunding这个状态的创建时间，而非完成时间
     * 调用PaypalService的sale方法获取交易的详细内容，然后返回当中的updated_time作为退款完成时间
     *
     * @param string $transactionId 交易的id，17位，payments表的number字段值
     *
     * @return datetime 交易的更新时间，经过时区转换
     *
     * @author ChenTeng <shanda030258@hotmail.com>
     */
    public function getRefundedCompletedTime($transactionId)
    {
        $service = $this->getPaypalService();
        $saleInfo = $service->sale($transactionId);
        if (!$saleInfo || $saleInfo->getId() != $transactionId) {
            $this->log("cannot get sale info on use paypal api with transaction id($transactionId)", PaymentService::LOG_INFO);
            return false;
        }
        return Carbon::parse($saleInfo->getUpdateTime())->timezone(Carbon::now()->tz)->toDateTimeString();
    }

    /**
     * 生成票据
     * 字段值及定义：
     * Reference ID:自定义票据id，作为invoice_id存入payments表作为下载传参，来源:计算获取---> ceil(microtime(true)   100).mt_rand(1000, 9999)
     * Amount of payment: payments.amount
     * Date of payment:payments.details中解析timestamp,然后自定义格式转换，看情况改时区
     * Payment account:payments.details中解析payer_email
     * Package:plans.display_name
     * Expiration time:payments.details中解析timestamp,然后依据package计算过期时间
     * Method:Paypal或者Stripe,stripe的程序要另外写或者后期补上
     * Name:users.name
     * Email:payments.client_email
     * 重新生成时使用原来的票据id
     *
     * @param string $transactionId 交易的id，17位，payments表的number字段值
     * @param bool   $force         是否强制生成
     * @param array  $extra         额外参数，存于customize_invoice表，每个用户一份，有就加入生成
     * 
     * @return string $referenceId   票据id
     *
     * @todo   stripe票据生成
     * @author ChenTeng <shanda030258@hotmail.com>
     */
    public function generateInvoice($transactionId, $force = false, $extra = [])
    {
        // 默认不强制生成
        if (empty($transactionId)) {
            // 交易id的格式不符合
            throw new BusinessErrorException('missing parameter');
        }
        $payment = Payment::where('number', $transactionId)->where('status', Payment::STATE_COMPLETED)->first();
        if (!$payment) {
            // 交易的状态不对或者查不到交易
            throw new BusinessErrorException("payment is invalid on use transaction id: $transactionId,maybe status is not completed or isn't exists");
        }
        /* if ($payment->gateway != PaymentService::GATEWAY_PAYPAL_EC) { */
        /*     //暂时不处理非paypal订单 */
        /*     throw new BusinessErrorException($this, "this payment(transaction id: $transactionId) is stripe payment,ignore"); */
        /* } */
        if (!empty($payment->invoice_id) && !$force) {
            // 该交易的invoice_id不为空，则已经生成过
            return $payment->invoice_id;
        }
        if ($plan = $payment->subscription->getPlan()) {
            $packageName = $plan->display_name;
        } else {
            $packageName = $payment->subscription->plan;
        }
        
        $data = (object)array();
        $data->referenceId = $payment->invoice_id ? : ceil(microtime(true) * 100) . mt_rand(1000, 9999);// reference ID
        $data->amount = '$ ' . $payment->amount;// amount
        $data->package = $packageName;// $payment->subscription->getPlan()->display_name;// package
        $data->name = $payment->client->name;// name
        $data->email = $payment->client_email;// email
        $data->method = $payment->gateway;// Paypal, Credit
        // details已经通过casts进行了属性转化，不需要再json_decode
        $details = $payment->details;//paypal的details全部都是string类型
        $data->paymentAccount = $payment->buyer_email;// payment account
        $data->subscription = $payment->subscription;
        $time = Carbon::parse($payment->created)->setTimezone(Carbon::now()->tz);// 需要转换时区

        // 目标时间格式 1 September 2017 at 5:16:04 p.m. HKT
        $yearAndMonth = $time->format("j F Y");
        $day = $time->format("g:i:s");
        $ampm = $time->format("a");
        if ($ampm == 'am') {
            $ampm = 'a.m.';
        } else {
            $ampm = 'p.m.';
        }
        // 在每天的0点~1点之间小时位有点奇怪，是12而不是1，Carbon限制？
        $data->date = $yearAndMonth . ' at ' . $day . ' ' . $ampm . ' HKT';// date of payment

        // 交易服务过期时间，格式 19 Sep 2017
        switch (strtolower($payment->subscription->frequency)) {
            case 'year':
                $months = 12;
                break;
            case 'month':
            default:
                $months = 1;
                break;
        }
        $data->expirationTime = $time->addMonths($months * $payment->subscription->frequency_interval)->format('j M Y');// expiration time
        
        // 保存票据id到payments
        $paymentInfo = Payment::where('number', $transactionId)->first();
        $paymentInfo->invoice_id = $data->referenceId;
        $updatePayment = $paymentInfo->save();

        //合并额外参数
        if (empty($extra)) {
            $extra = \Pheye\Payments\Models\CustomizedInvoice::select('company_name', 'address', 'contact_info', 'website', 'tax_no')
            ->where('user_id', $payment->client_id)
            ->first();
        }

        $data->company_name = $extra['company_name'] ? : false;
        $data->address = $extra['address']? : false;
        $data->contact_info = $extra['contact_info'] ? : false;
        $data->website = $extra['website'] ? : false;
        $data->tax_no = $extra['tax_no'] ? : false;
        $data->icon = false;

        // 渲染html,转换成pdf
        $mpdf = new Mpdf(['mode'=>'utf-8']);// 指定用utf-8,就不会有乱码问题
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->showImageErrors = true;
        if (config('payment.invoice.icon')) {
            $mpdf->imageVars['icon'] = File::get(config('payment.invoice.icon'));
            $data->icon = true;
        }
        $mpdf->WriteHTML($this->getInvoicePage($data));


        // 如果票据id成功更新到表，那么生成文件
        // 存储路径storage/app/invoice
        if ($updatePayment) {
            $file = $this->config['invoice']['save_path'] . '/' . "$data->referenceId.pdf";
            Storage::delete($file);
            Storage::put($file, $mpdf->Output('', 'S'));
        }

        // 成功
        $this->log("$transactionId 's payment invoice was generated,reference id is $data->referenceId.", PaymentService::LOG_INFO);
        return $data->referenceId;
    }

    /**
     * 生成html页面，后续使用dompdf转换成pdf
     * 调用视图并完成渲染，返回视图内容
     *
     * @param array $invoiceData payments相关信息，用于视图中的内容赋值
     *
     * @return object 视图内容
     *
     * @author ChenTeng <shanda030258@hotmail.com>
     */
    public function getInvoicePage($invoiceData)
    {
        $view = view('payment::subscriptions.invoice')->with('data', $invoiceData);
        return response($view)->getContent();// 返回视图内容
    }

    /**
     * 检查票据是否存在
     *
     * @param int $invoiceId 票据id
     *
     * @return bool
     *
     * @author ChenTeng <shanda030258@hotmail.com>
     */
    public function checkInvoiceExists($invoiceId)
    {
        $fileName = $invoiceId . '.pdf';
        // 这里直接访问票据存储路径获取文件，另一种做法是存储使用存储路径，读取使用公开路径，中间做一个软链接,但是可见性要改成public
        $savePath = $this->config['invoice']['save_path'] . '/' . $fileName;
        if (Storage::exists($savePath)) {
            // 找到文件
            return true;
        } else {
            // 不存在这个文件,因为这里传参只是票据id，如果不存在，无法对应到任何一个交易，所以没办法调用命令去生成再下载
            // 可以从外部调用生成
            $this->log("invoice file is not exists,file name is $fileName", PaymentService::LOG_INFO);
            return false;
        }
    }

    /**
     * 票据下载方法
     * 不再做验证，权限验证和文件验证在其他地方完成
     *
     * @param int $invoiceId 票据id,每个payment记录有一个，如果没有需要执行方法生成
     *
     * @return object 下载文件
     *
     * @author ChenTeng <shanda030258@hotmail.com>
     *
     * @todo 是否有需要做防止恶意下载的限制
     */
    public function downloadInvoice($invoiceId)
    {
        $this->log("download invoice");
        $fileName = $invoiceId . '.pdf';
        // 这里直接访问票据存储路径获取文件，另一种做法是存储使用存储路径，读取使用公开路径，中间做一个软链接
        $savePath = $this->config['invoice']['save_path'] . '/' . $fileName;
        $url = storage_path() . '/app/' . $savePath;
        $this->log("download invoice file ,file name is $fileName,download url is $url", PaymentService::LOG_INFO);
        return response()->download($url, $fileName);
    }

    public function routes()
    {
        include __DIR__ . '/../../routes/payment.php';
    }

    public function adminRoutes()
    {
        include __DIR__ . '/../../routes/admin.php';
    }
}
