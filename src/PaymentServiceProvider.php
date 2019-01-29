<?php

namespace Pheye\Payments;

use Pheye\Payments\Models\GatewayConfig;
use Pheye\Payments\Models\AgreementDetails;
use Pheye\Payments\Models\RecurringPaymentDetails;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;
use Pheye\Payments\Facades\Payment as PaymentFacade;
use Payum\LaravelPackage\Storage\EloquentStorage;
use Payum\Core\Storage\FilesystemStorage;
use Payum\LaravelPackage\Model\Token;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // 发布配置
        $this->publishes([__DIR__ . '/../publishable/config/payment.php' => config_path('payment.php')]);
        // 发布视图
        $this->publishes([__DIR__ . '/../publishable/resources/views/' => resource_path('views')], 'voyager');

        // 发布迁移
        $this->publishes([__DIR__ . '/../publishable/database/migrations' => database_path('migrations')]);
        // 发布seeds(只有加--tags才行)
        $this->publishes([__DIR__ . '/../publishable/database/seeds/' => database_path('seeds')], 'voyager');

        // 注册扩展包视图
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment');

        \Pheye\Payments\Models\Refund::observe(\Pheye\Payments\Observers\RefundObserver::class);

        if ($this->app->runningInConsole()) {
            $this->registerConsoleCommands();
        }

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register('Payum\LaravelPackage\PayumServiceProvider');
        $this->app->singleton(Contracts\PaymentService::class, function() {
            // 配置应该由此处传入，以便达到解耦以及多个PaymentService实例共用的目的
            return new Services\PaymentService(config('payment'));
        });
        // TODO:这里不应该使用app.service.payment
        $this->app->singleton('payment', function() {
            return app(Contracts\PaymentService::class);
        });

        $this->app->resolving('payum.builder', function(\Payum\Core\PayumBuilder $payumBuilder) {
            $payumBuilder
                // this method registers filesystem storages, consider to change them to something more
                // sophisticated, like eloquent storage
                /* ->setTokenStorage(new FilesystemStorage(sys_get_temp_dir(), Token::class, 'hash')) */
                /* ->addStorage(Payment::class, new EloquentStorage(Payment::class)) */
                /* ->setTokenStorage(new EloquentStorage(Token::class)) */
                /* ->addStorage(Payout::class, new FilesystemStorage(sys_get_temp_dir(), Payout::class)) */
                ->addDefaultStorages()
                ->setTokenStorage(new EloquentStorage(Token::class))
                ->addStorage(AgreementDetails::class, new FilesystemStorage(sys_get_temp_dir(), AgreementDetails::class))
                ->addStorage(RecurringPaymentDetails::class, new FilesystemStorage(sys_get_temp_dir(), RecurringPaymentDetails::class));
            // Paypal配置全部从数据库中读取
            // Paypal Express Checkout
            $configs = GatewayConfig::all();
            foreach ($configs as $config) {
                switch ($config->factory_name) {
                case GatewayConfig::FACTORY_PAYPAL_EXPRESS_CHECKOUT:
                    $payumBuilder->addGateway($config->gateway_name, [
                        'factory' => 'paypal_express_checkout',
                        'username' => $config->config['username'],
                        'password' => $config->config['password'],
                        'signature' => $config->config['signature'],
                        'sandbox' => $config->config['sandbox'],
                    ]);
                    break;
                case GatewayConfig::FACTORY_STRIPE:
                    $payumBuilder->addGateway($config->gateway_name,[
                        'factory' => 'stripe_js',
                        'publishable_key' => $config->config['publishable_key'],
                        'secret_key' => $config->config['secret_key'],
                        'payum.extension.create_customer' => new \Pheye\Payments\Payum\Stripe\Extensions\CreateCustomerExtension(),
                    ]);
                    break;
                }
            }

            // Paypal REST API
            /* $configs = GatewayConfig::where('factory_name', GatewayConfig::FACTORY_PAYPAL_REST)->get(); */
            /* foreach ($configs as $config) { */
            /*     $payumBuilder->addGateway($config->gateway_name, [ */
            /*             'factory' => 'paypal_rest', */
            /*             'client_id' => $config->config['client_id'], */
            /*             'client_secret' => $config->config['client_secret'], */
            /*             'config_path' => $config->config['config_path'] */
            /*         ]); */
            /* } */
            /* $payumBuilder->addGateway('stripe',[ */
            /*         'factory' => 'stripe_js', */
            /*         'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'), */
            /*         'secret_key' => env('STRIPE_SECRET_KEY') */
            /*     ]); */
        });
    }

    public function registerConsoleCommands() {
        $this->commands([
            Console\Commands\InvoiceCommand::class,
            Console\Commands\RefundCommand::class,
            Console\Commands\SyncPaymentsCommand::class,
            Console\Commands\CancelCommand::class,
            Console\Commands\SyncPlansCommand::class
        ]);
    }
}
