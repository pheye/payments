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
$php artisan vendor:publish --provider='Pheye\Payments\PaymentServiceProvider'
```

完成数据库迁移：

```
php artisan migrate
```

填充`Voyager`后台菜单:

```
php artisan db:seed --class=VoyagerAdminSeeder
```

至此，基本配置就完成了。

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



