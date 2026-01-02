<?php

use Illuminate\Support\Facades\Route;
use WeiJuKeJi\PaymentBill\Http\Controllers\AlipayBillController;
use WeiJuKeJi\PaymentBill\Http\Controllers\BillDownloadController;
use WeiJuKeJi\PaymentBill\Http\Controllers\PaymentChannelController;
use WeiJuKeJi\PaymentBill\Http\Controllers\WechatBillController;

Route::middleware(['api'])
    ->prefix(config('payment-bill.route_prefix'))
    ->name('payment-bill.')
    ->group(function () {
        Route::middleware(['auth:'.config('payment-bill.guard')])->group(function () {
            // 支付渠道管理
            Route::apiResource('payment-channels', PaymentChannelController::class)
                ->parameters(['payment-channels' => 'payment_channel']);

            // 账单下载管理
            Route::get('bill-downloads/{billDownload}/download', [BillDownloadController::class, 'download'])
                ->name('bill-downloads.download');
            Route::apiResource('bill-downloads', BillDownloadController::class)->only([
                'index', 'store', 'show',
            ]);

            // 支付宝账单流水
            Route::apiResource('alipay-bills', AlipayBillController::class)->only([
                'index', 'show',
            ]);

            // 微信账单流水
            Route::apiResource('wechat-bills', WechatBillController::class)->only([
                'index', 'show',
            ]);
        });
    });


