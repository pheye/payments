# payments
支付子系统：支持paypal rest, paypal ec, 微信支付，支付宝等支付方式；并支持账单，退款处理等功能.

# 安装
需要注意，本包目前需要依赖于`pheye/voyager`，才能提供后台菜单，以及动态配置

```
composer require pheye/payments dev-master
```

修改`config/app.php`

```
'providers' => [
    // ...
    Pheye\Payments\PaymentServiceProvider::class,
],
'aliases' => [
    // ...
    'PaymentService' => Pheye\Payments\Facades\PaymentService::class,
]
```

# 配置

生成相关配置文件：

```
$ php artisan vendor:publish --provider='Pheye\Payments\PaymentServiceProvider'
$ composer dump-autoload
```

完成数据库迁移：

```
$ php artisan migrate
```

填充`Voyager`后台菜单:

```
$ php artisan db:seed --class=VoyagerAdminSeeder
```

至此，基本配置就完成了。

## 额外的配置
1.编辑`app/User.php`:

```
<?php
 
namespace App;
use Pheye\Payments\Traits\Paymentable; 

class User extends Authenticatable           
{                                            
    use Paymentable;                                             
    // ...
} 
```

2.开放路由（这一步不推荐直接使用，仅做参考，建议根据实际需要添加对应路由），编辑`routes/web.php`:

```
PaymentService::routes();
```

# 用法
## 后台
1.价格计划中至少要有一项，并且价格不为0。如下图：

![](http://images.cnblogs.com/cnblogs_com/pheye/1220102/o_plans.png)

2.至少有一个有效的支付网关，默认名称为`paypal ec`，可通过`.env`中的`CURRENT_GATEWAY`修改使用的网关。如果没有可用账号测试，可直接使用我的配置。


```
Gateway Name: paypal_ec
Factory Name: paypal_express_checkout
Config:
{"sandbox":true,"password":"GM8G8QUF96Z4SM5K","username":"95496875-facilitator_api1.qq.com","signature":"AFcWxV21C7fd0v3bYYYRCpSSRl31AXAjyVXCseIVl89pjDWPgVXyKvaa"}
```

买家测试账号:

```
95496875-buyer2@qq.com
88888888
```
## 支付

注意必须使用WEB形式的路由才能被重定向到`Paypal`网站：

```
POST /pay
```

参数：

- `plan_name`: `plan`的名称
- `gateway_name`(可选): 如果指定就使用对应名称的网关，否则使用默认配置的网关
- `onetime`: 是否一次性支付，请设置为1，目前提供出来的正式的账号不能循环扣款


# 支付的网关类型

```
paypal_express_checkout Paypal EC
zhongwaibao 中外宝
```

# 其他
### 事件
`Pheye\Payments\Events\PayedEvent`:在每次支付完成后，会执行`event(new PayedEvent($payment))`抛出支付完成的事件，以便应用程序可根据自身需要做些额外操作
