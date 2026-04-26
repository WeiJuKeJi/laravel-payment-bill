<?php

use Illuminate\Support\Facades\Route;
use WeiJuKeJi\PaymentBill\Http\Controllers\AlipayBillController;
use WeiJuKeJi\PaymentBill\Http\Controllers\BillDownloadController;
use WeiJuKeJi\PaymentBill\Http\Controllers\LocalBillFileImportController;
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
            Route::get('bill-download-calendar', [BillDownloadController::class, 'calendar'])
                ->name('bill-download-calendar');
            Route::get('bill-downloads/{billDownload}/file', [BillDownloadController::class, 'downloadFile'])
                ->name('bill-downloads.file');
            Route::post('bill-downloads/{billDownload}/download', [BillDownloadController::class, 'download'])
                ->name('bill-downloads.download');
            Route::post('bill-downloads/{billDownload}/import', [BillDownloadController::class, 'import'])
                ->name('bill-downloads.import');
            Route::apiResource('bill-downloads', BillDownloadController::class)->only([
                'index', 'store', 'show',
            ]);

            // 本地账单文件导入
            Route::post('local-bill-files/import', [LocalBillFileImportController::class, 'import'])
                ->name('local-bill-files.import');

            // 支付宝账单流水
            Route::apiResource('alipay-bills', AlipayBillController::class)->only([
                'index', 'show',
            ]);
            // 支付宝账单对账
            Route::prefix('alipay-bills/{alipayBill}/reconciliation')->name('alipay-bills.reconciliation.')->group(function () {
                Route::post('mark-as-matched', [AlipayBillController::class, 'markAsMatched'])->name('mark-as-matched');
                Route::post('mark-as-mismatched', [AlipayBillController::class, 'markAsMismatched'])->name('mark-as-mismatched');
                Route::post('mark-as-manual', [AlipayBillController::class, 'markAsManual'])->name('mark-as-manual');
                Route::post('mark-as-ignored', [AlipayBillController::class, 'markAsIgnored'])->name('mark-as-ignored');
                Route::post('mark-as-pending', [AlipayBillController::class, 'markAsPending'])->name('mark-as-pending');
            });

            // 微信账单流水
            Route::apiResource('wechat-bills', WechatBillController::class)->only([
                'index', 'show',
            ]);
            // 微信账单对账
            Route::prefix('wechat-bills/{wechatBill}/reconciliation')->name('wechat-bills.reconciliation.')->group(function () {
                Route::post('mark-as-matched', [WechatBillController::class, 'markAsMatched'])->name('mark-as-matched');
                Route::post('mark-as-mismatched', [WechatBillController::class, 'markAsMismatched'])->name('mark-as-mismatched');
                Route::post('mark-as-manual', [WechatBillController::class, 'markAsManual'])->name('mark-as-manual');
                Route::post('mark-as-ignored', [WechatBillController::class, 'markAsIgnored'])->name('mark-as-ignored');
                Route::post('mark-as-pending', [WechatBillController::class, 'markAsPending'])->name('mark-as-pending');
            });
        });
    });

