<?php
namespace Pheye\Payments\Contracts;

use Illuminate\Support\Collection;
use Pheye\Payments\Models\Subscription;
use Pheye\Payments\Models\Payment;
use Pheye\Payments\Models\Refund;

/**
 * Bigbigads的统一支付服务
 */
interface PaymentService
{
    const GATEWAY_NONE = "none";
    const GATEWAY_STRIPE = "stripe";
    const GATEWAY_PAYPAL = "paypal";

    /**
     * 默认情况下，同步订阅时不会同步取消的订阅，或者采用其他优化策略;同步订单也会有自己的优化策略；
     * 在特殊情况下，可能想所有订阅都同步，这时需要加该参数。
     * Value: true|false
     */
    const PARAMETER_FORCE = "FORCE";

    /**
     * 默认只更新tag为default的订阅，可以指明更新其他tag的订阅
     * Value: Array
     */
    const PARAMETER_TAGS = "TAGS";
    /**
     * Value: ['start' => Carbon|null, 'end' => Carbon|null]
     */
    /* const PARAMETER_SYNC_RANGE = "SYNC_RANGE"; */

    /**
     * 用于调试目的日志输出
     * @param mixed $logger 控制台或者Log
     */
    public function setLogger($logger);

    public function setParameter($key, $val);
    public function getParameter($key);


    /**
     * 一次性付款
     */
    public function checkout();

    /**
     * 同步计划
     * @param Array $gateways 为空值时表示同步所有计划;否则同步指定的计划。每个数组项应从GATEWAY_*中取值。
     * @return void
     * @throw Exception
     */
    public function syncPlans(Array $gateways);

    /**
     * 同步所有支付记录，将本地没有远程有的记录导入到本地数据库。该接口主要解决两个问题：
     * 1. 早前的Paypal并没有在数据库留下支付记录，所以需要遍历Paypal的所有订阅，导出卡住、成功和退款的支付记录;
     * 2. Paypal的Webhook是个不可靠的机制，为了防止此问题，也需要定期执行同步命令;
     * 3. 当用户被循环扣款后，通过该命令同步用户的过期时间
     * 4. 当用户退款时，通过该命令设置用户的过期时间和切换计划
     * @param Array $gateways 为空值时表示同步所有计划;否则同步指定的计划。每个数组项应从GATEWAY_*中取值。
     * @param mixed $subscription 指定同步的订阅，如果指明该参数将只同步该订阅
     */
    public function syncPayments(Array $gateways, $subscription = null);


    /**
     * 同步订阅计划, 解决以下问题：
     * 1. 以前的订阅计划的计划数据从Plan对象获取，但Plan对象可能改变其扣款周期等，已经完成的订阅不应该受此影响。
     * 2. 一些新增字段需要根据计划状态填充初始值
     * 3. 一个用户可能有多个订阅，除用户设置的订阅外，其他全部自动取消
     * 4. 对7天内即可到期的活动订阅推到队列，在到期前5小时执行syncPayments(这边的时间设计是比较随意的，只要订单完成后，过期时间前即可）
     * @param Array $gateways 为空值时表示同步所有计划;否则同步指定的计划。每个数组项应从GATEWAY_*中取值。
     * @param mixed $subscription 指定同步的订阅，如果指明该参数将只同步该订阅
     */
    public function syncSubscriptions(Array $gateways, $subscription);


    /**
     * 根据订单切换订阅的状态，切换用户当前的计划。用户的权限会被重置，同时过期时间将被设置。
     * @remark 该功能纳入支付服务是否合适的有待进一步分析设计
     */
    public function handlePayment(Payment $payment);

    /**
     * 获取指定支付网关的服务
     * @param String $gateway 从GATEWAY_*中取值
     */
    public function getRawService($gateway);

    /**
     * 取消指定订阅
     * @param Subscription $subscription
     * @return boolean
     */
    public function cancel(Subscription $subscription);


    /**
     * 申请退款
     * @param \App\Payment $payment 支付订单
     * @param $amount 如果为0则全额退款，否则按照$amount的金额退款
     */
    public function requestRefund(Payment $payment, $amount = 0);

    /**
     * 退款
     * @param \App\Refund $refund 退款记录
     * @return boolean
     */
    public function refund(Refund $refund);
}
