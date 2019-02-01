<?php
$controller = '\Pheye\Payments\Http\Controllers\SubscriptionController';

Route::get('/payment/{method}/prepare', $controller . '@prepareCheckout');
Route::get('/payment/paypal/done', $controller .  '@onPaypalDone')->name('paypal_done');
// 该post是由nuxt访问的，实际上应该访问的secure_api，但由于payum会检查return url，所以目前只能该开放该路由
Route::get('/payment/alipay/done', $controller . '@onAlipayDone')->name('alipay_done');
Route::post('/payment/alipay/notify', $controller . '@onAlipayNotify');
Route::post('/payment/paypal/done', $controller . '@onPaypalDone');
Route::any('/payment/stripe/done', $controller . '@onStripeDone')->name('stripe_done');
Route::get('/payment/paypal/capture', $controller . '@onPaypalCapture')->name('paypal_capture');
Route::get('/coupons', '\Pheye\Payments\Http\Controllers\CouponController@index');

Route::group(['middleware'=>'auth'], function() use ($controller) {
    Route::match(['get', 'post'], '/pay', $controller . '@pay');                                    
    Route::get('/billings', $controller . '@billings');                           
    Route::post('/subscription/{id}/cancel', $controller . '@cancel');            
    Route::get('/invoice/{invoice}', function (Request $request, $invoiceId) {            
        return Auth::user()->downloadInvoice($invoiceId, [                                
            'vendor'  => env('app.name'),    
            'product' => env('app.name'),    
        ], storage_path('invoice'));
    });
    Route::put('/payments/{number}/refund_request', $controller . '@requestRefund');                                                                                         
    /* Route::get('/users/customize_invoice', 'UserController@getInvoiceCustomer'); */          
    /* Route::post('/users/customize_invoice', 'UserController@setInvoiceCustomer'); */         
    /* Route::get('/invoices/{invoice_id}', 'InvoiceController@downloadInvoice'); */            
}); 
