<?php

namespace WeiJuKeJi\PaymentBill\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use WeiJuKeJi\PaymentBill\Jobs\ImportAlipayBillJob;
use WeiJuKeJi\PaymentBill\Jobs\ImportWechatBillJob;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;

/**
 * 账单导入调度服务。
 */
class BillImportService
{
    /**
     * 派发指定账单下载记录的导入任务。
     *
     * @param  BillDownload|int  $billDownload
     */
    public function dispatchWechatImport(BillDownload|int $billDownload, bool $force = false): void
    {
        $record = $billDownload instanceof BillDownload
            ? $billDownload
            : BillDownload::query()->findOrFail($billDownload);

        $record->loadMissing('paymentChannel');

        $this->ensureWechatBill($record->paymentChannel);
        $this->ensureDownloadReady($record);

        $disk = config('payment-bill.storage_disk');
        if (!Storage::disk($disk)->exists($record->file_path)) {
            throw new InvalidArgumentException('账单文件不存在或已被删除：'.$record->file_path);
        }

        ImportWechatBillJob::dispatch($record, $force);

        Log::info('已派发微信账单导入任务', [
            'bill_download_id' => $record->getKey(),
            'payment_channel_id' => $record->payment_channel_id,
            'force' => $force,
        ]);
    }

    public function dispatchAlipayImport(BillDownload|int $billDownload, bool $force = false): void
    {
        $record = $billDownload instanceof BillDownload
            ? $billDownload
            : BillDownload::query()->findOrFail($billDownload);

        $record->loadMissing('paymentChannel');

        $this->ensureAlipayBill($record->paymentChannel);
        $this->ensureDownloadReady($record);

        $disk = config('payment-bill.storage_disk');
        if (!Storage::disk($disk)->exists($record->file_path)) {
            throw new InvalidArgumentException('账单文件不存在或已被删除：'.$record->file_path);
        }

        ImportAlipayBillJob::dispatch($record, $force);

        Log::info('已派发支付宝账单导入任务', [
            'bill_download_id' => $record->getKey(),
            'payment_channel_id' => $record->payment_channel_id,
            'force' => $force,
        ]);
    }

    private function ensureWechatBill(?PaymentChannel $channel): void
    {
        if (!$channel) {
            throw new InvalidArgumentException('账单缺少关联的支付渠道信息');
        }

        if ($channel->channel !== 'wechat') {
            throw new InvalidArgumentException('当前渠道非微信，无法使用微信导入逻辑');
        }
    }

    private function ensureAlipayBill(?PaymentChannel $channel): void
    {
        if (!$channel) {
            throw new InvalidArgumentException('账单缺少关联的支付渠道信息');
        }

        if ($channel->channel !== 'alipay') {
            throw new InvalidArgumentException('当前渠道非支付宝，无法使用支付宝导入逻辑');
        }
    }

    private function ensureDownloadReady(BillDownload $billDownload): void
    {
        if ($billDownload->download_status !== BillDownload::DOWNLOAD_STATUS_COMPLETED) {
            throw new InvalidArgumentException('账单尚未下载完成，无法导入');
        }
    }
}
