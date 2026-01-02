<?php

namespace WeiJuKeJi\PaymentBill\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Services\Importers\WechatBillImporter;
use RuntimeException;
use Throwable;

/**
 * 微信账单导入队列任务。
 */
class ImportWechatBillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly BillDownload $billDownload,
        private readonly bool $force = false
    ) {
        $queue = config('payment-bill.queues.wechat_bill_import');
        $connection = config('payment-bill.queues.connection');

        if ($connection) {
            $this->onConnection($connection);
        }

        $this->onQueue($queue);
    }

    /**
     * 执行导入任务。
     */
    public function handle(): void
    {
        $download = $this->billDownload->fresh();

        if (!$download) {
            throw new RuntimeException('账单记录不存在或已被删除');
        }

        if (!$this->force && $download->import_status === BillDownload::IMPORT_STATUS_COMPLETED) {
            Log::info('账单已导入，无需重复导入', [
                'bill_download_id' => $download->getKey(),
            ]);

            return;
        }

        if (!$download->file_path) {
            throw new RuntimeException('账单文件路径为空，无法导入');
        }

        $disk = config('payment-bill.storage_disk');
        $importer = new WechatBillImporter(
            $download->payment_channel_id,
            $disk,
            $download->file_path
        );

        $download->fill([
            'import_status' => BillDownload::IMPORT_STATUS_PROCESSING,
            'import_error' => null,
            'import_started_at' => now(),
            'import_completed_at' => null,
            'import_duration' => null,
            'import_last_attempt_at' => now(),
            'import_retry_count' => ($download->import_retry_count ?? 0) + 1,
            'import_total_transaction_count' => 0,
            'import_total_new_count' => 0,
            'import_total_updated_count' => 0,
            'import_total_settlement_amount' => '0.00',
            'import_total_refund_amount' => '0.00',
            'import_total_refund_voucher_amount' => '0.00',
            'import_total_fee_amount' => '0.00',
            'import_total_order_amount' => '0.00',
            'import_total_refund_apply_amount' => '0.00',
        ])->save();

        $startedAt = microtime(true);

        try {
            $result = $importer->import();

            $elapsed = microtime(true) - $startedAt;
            $durationSeconds = (int) max(1, round($elapsed));

            $download->fill(array_merge(
                [
                    'import_status' => BillDownload::IMPORT_STATUS_COMPLETED,
                    'import_completed_at' => now(),
                    'import_duration' => $durationSeconds,
                ],
                $result['import_totals'] ?? [],
                $result['summary'] ?? []
            ))->save();

            Log::info('微信账单导入完成', [
                'bill_download_id' => $download->getKey(),
                'import_totals' => $result['import_totals'] ?? [],
                'summary' => $result['summary'] ?? [],
                'duration_seconds' => $durationSeconds,
                'duration_exact' => $elapsed,
            ]);
        } catch (Throwable $throwable) {
            $elapsed = microtime(true) - $startedAt;
            $durationSeconds = (int) max(1, round($elapsed));

            $download->fill([
                'import_status' => BillDownload::IMPORT_STATUS_FAILED,
                'import_error' => $throwable->getMessage(),
                'import_duration' => $durationSeconds,
            ])->save();

            Log::error('微信账单导入失败', [
                'bill_download_id' => $download->getKey(),
                'exception' => $throwable,
                'duration_seconds' => $durationSeconds,
                'duration_exact' => $elapsed,
            ]);

            throw $throwable;
        }
    }

    /**
     * 任务失败后的处理。
     */
    public function failed(Throwable $throwable): void
    {
        $download = $this->billDownload->fresh();
        if (!$download) {
            return;
        }

        $download->fill([
            'import_status' => BillDownload::IMPORT_STATUS_FAILED,
            'import_error' => $throwable->getMessage(),
        ])->save();
    }
}
