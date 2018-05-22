<?php

Route::get('/refunds/{id}/accept', '\Pheye\Payments\Http\Controllers\Admin\RefundController@acceptRefund')->name('refund_accept');

Route::get('/refunds/{id}/reject', '\Pheye\Payments\Http\Controllers\Admin\RefundController@rejectRefund')->name('refund_reject');
