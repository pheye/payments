<?php

Route::get('timezones/{timezone}', Pheye\Payments\Http\Controllers\TimezoneController::class . '@index');

Route::get('/payment/{method}/prepare', 'SubscriptionController@prepareCheckout');
Route::get('/payment/paypal/done', 'SubscriptionController@onPaypalDone')->name('paypal_done');
// 该post是由nuxt访问的，实际上应该访问的secure_api，但由于payum会检查return url，所以目前只能该开放该路由
Route::post('/payment/paypal/done', 'SubscriptionController@onPaypalDone');
Route::any('/payment/stripe/done', 'SubscriptionController@onStripeDone')->name('stripe_done');
Route::post('/payment/scoinpay/done', 'ScoinpayController@onPay')->name('scoinpay_done');

Route::group(['middleware'=>'auth'], function() {                                         
    Route::post('/pay', 'SubscriptionController@pay');                                    
    Route::get('/billings', 'SubscriptionController@billings');                           
    Route::post('/subscription/{id}/cancel', 'SubscriptionController@cancel');            
    Route::get('/invoice/{invoice}', function (Request $request, $invoiceId) {            
        return Auth::user()->downloadInvoice($invoiceId, [                                
            'vendor'  => env('app.name'),    
            'product' => env('app.name'),    
        ], storage_path('invoice'));
    });
    Route::put('/payments/{number}/refund_request', 'SubscriptionController@requestRefund');                                                                                         
    Route::get('/users/customize_invoice', 'UserController@getInvoiceCustomer');          
    Route::post('/users/customize_invoice', 'UserController@setInvoiceCustomer');         
    Route::get('/invoices/{invoice_id}', 'InvoiceController@downloadInvoice');            
}); 
