<?php

namespace WeiJuKeJi\PaymentBill\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use Throwable;

/**
 * 本地账单文件导入服务。
 */
class LocalBillFileImportService
{
    private string $storageDisk;

    public function __construct(private readonly BillImportService $billImportService)
    {
        $this->storageDisk = config('payment-bill.storage_disk', 'local');
    }

    /**
     * 批量导入本地账单文件。
     *
     * @param  array<UploadedFile>  $files
     * @return array{total: int, created: int, updated: int, skipped: int, failed: int, imported: int, details: array}
     */
    public function import(
        array $files,
        int $paymentChannelId,
        string $billType = 'ALL',
        bool $force = false,
        bool $autoImport = false
    ): array {
        // 验证支付渠道
        $channel = $this->resolveChannel($paymentChannelId);
        if (! $channel) {
            throw new InvalidArgumentException("未找到支付渠道：{$paymentChannelId}");
        }

        $billType = strtoupper($billType);

        // 解析文件
        $parsedFiles = $this->parseUploadedFiles($files);

        if (empty($parsedFiles)) {
            return [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'imported' => 0,
                'details' => [],
            ];
        }

        // 批量导入
        return $this->importFiles($parsedFiles, $channel, $billType, $force, $autoImport);
    }

    /**
     * 解析支付渠道。
     */
    private function resolveChannel(int $channelId): ?PaymentChannel
    {
        $channel = PaymentChannel::query()->find($channelId);

        if (! $channel) {
            return null;
        }

        if (! $channel->is_enabled) {
            throw new InvalidArgumentException("支付渠道 {$channel->name} 当前已禁用");
        }

        return $channel;
    }

    /**
     * 解析上传的文件。
     *
     * @param  array<UploadedFile>  $files
     * @return array<array{file: UploadedFile, filename: string, date: Carbon, size: int}>
     */
    private function parseUploadedFiles(array $files): array
    {
        $parsed = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $filename = $file->getClientOriginalName();
            $date = $this->extractDateFromFilename($filename);

            if (! $date) {
                throw new InvalidArgumentException("无法从文件名解析日期：{$filename}。支持的格式：YYYY-MM-DD_ALL.csv 或 YYYYMMDD.csv");
            }

            $parsed[] = [
                'file' => $file,
                'filename' => $filename,
                'date' => $date,
                'size' => $file->getSize(),
            ];
        }

        // 按日期排序
        usort($parsed, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        return $parsed;
    }

    /**
     * 从文件名中提取日期。
     * 支持格式：2022-07-24_ALL.csv、20220724.csv 等
     */
    private function extractDateFromFilename(string $filename): ?Carbon
    {
        // 移除扩展名
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // 尝试匹配 YYYY-MM-DD 格式
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $nameWithoutExt, $matches)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $matches[1]);
            } catch (Throwable $e) {
                // 继续尝试其他格式
            }
        }

        // 尝试匹配 YYYYMMDD 格式
        if (preg_match('/(\d{8})/', $nameWithoutExt, $matches)) {
            try {
                return Carbon::createFromFormat('Ymd', $matches[1]);
            } catch (Throwable $e) {
                // 解析失败
            }
        }

        return null;
    }

    /**
     * 批量导入文件。
     *
     * @param  array<array{file: UploadedFile, filename: string, date: Carbon, size: int}>  $parsedFiles
     * @return array{total: int, created: int, updated: int, skipped: int, failed: int, imported: int, details: array}
     */
    private function importFiles(
        array $parsedFiles,
        PaymentChannel $channel,
        string $billType,
        bool $force,
        bool $autoImport
    ): array {
        $stats = [
            'total' => count($parsedFiles),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'imported' => 0,
            'details' => [],
        ];

        foreach ($parsedFiles as $file) {
            try {
                $result = $this->importFile($file, $channel, $billType, $force);

                $detail = [
                    'filename' => $file['filename'],
                    'date' => $file['date']->format('Y-m-d'),
                    'action' => $result['action'],
                    'success' => true,
                    'message' => $this->getActionMessage($result['action']),
                ];

                if ($result['action'] === 'created') {
                    $stats['created']++;
                } elseif ($result['action'] === 'updated') {
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }

                // 自动触发导入任务
                if ($autoImport && $result['record']) {
                    try {
                        if ($channel->channel === 'wechat') {
                            $this->billImportService->dispatchWechatImport($result['record'], $force);
                        } elseif ($channel->channel === 'alipay') {
                            $this->billImportService->dispatchAlipayImport($result['record'], $force);
                        }
                        $stats['imported']++;
                        $detail['imported'] = true;
                        $detail['message'] .= '，已派发导入任务';
                    } catch (Throwable $e) {
                        $detail['imported'] = false;
                        $detail['import_error'] = $e->getMessage();
                    }
                }

                $stats['details'][] = $detail;
            } catch (Throwable $e) {
                $stats['failed']++;
                $stats['details'][] = [
                    'filename' => $file['filename'],
                    'date' => $file['date']->format('Y-m-d'),
                    'action' => 'failed',
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    /**
     * 导入单个文件。
     *
     * @param  array{file: UploadedFile, filename: string, date: Carbon, size: int}  $file
     * @return array{action: string, record: ?BillDownload}
     */
    private function importFile(array $file, PaymentChannel $channel, string $billType, bool $force): array
    {
        return DB::transaction(function () use ($file, $channel, $billType, $force) {
            // 查找或创建记录
            $record = BillDownload::query()
                ->where('payment_channel_id', $channel->id)
                ->whereDate('bill_date', $file['date']->toDateString())
                ->where('bill_type', $billType)
                ->lockForUpdate()
                ->first();

            $isNew = $record === null;
            $action = 'skipped';

            if ($isNew) {
                $record = new BillDownload([
                    'payment_channel_id' => $channel->id,
                    'bill_date' => $file['date']->toDateString(),
                    'bill_type' => $billType,
                    'download_status' => BillDownload::DOWNLOAD_STATUS_PENDING,
                    'import_status' => BillDownload::IMPORT_STATUS_PENDING,
                ]);
            } elseif (! $force && $record->download_status === BillDownload::DOWNLOAD_STATUS_COMPLETED) {
                // 已存在且不强制覆盖
                return ['action' => 'skipped', 'record' => $record];
            }

            // 存储文件到规范路径
            $storagePath = $this->storeUploadedFile($file, $channel, $file['date'], $billType);

            // 更新记录
            $previousPath = $record->file_path;

            $record->fill([
                'file_path' => $storagePath,
                'download_status' => BillDownload::DOWNLOAD_STATUS_COMPLETED,
                'download_completed_at' => now(),
                'download_error' => null,
                'import_status' => BillDownload::IMPORT_STATUS_PENDING,
                'download_started_at' => $record->download_started_at ?? now(),
            ]);

            $record->save();

            // 删除旧文件
            if ($previousPath && $previousPath !== $storagePath && Storage::disk($this->storageDisk)->exists($previousPath)) {
                Storage::disk($this->storageDisk)->delete($previousPath);
            }

            $action = $isNew ? 'created' : 'updated';

            return ['action' => $action, 'record' => $record];
        });
    }

    /**
     * 将上传的文件存储到规范路径。
     *
     * @param  array{file: UploadedFile, filename: string, date: Carbon, size: int}  $file
     */
    private function storeUploadedFile(array $file, PaymentChannel $channel, Carbon $billDate, string $billType): string
    {
        $extension = strtolower($file['file']->getClientOriginalExtension());

        $directory = sprintf(
            'payment/bills/%s/%d',
            $channel->channel,
            $channel->id
        );

        $filename = sprintf('%s.%s', $billDate->format('Ymd'), $extension);
        $path = $directory.'/'.$filename;

        // 使用 Laravel Storage 的 putFileAs 方法存储上传文件
        Storage::disk($this->storageDisk)->putFileAs($directory, $file['file'], $filename);

        return $path;
    }

    /**
     * 获取操作消息。
     */
    private function getActionMessage(string $action): string
    {
        return match ($action) {
            'created' => '新建成功',
            'updated' => '更新成功',
            'skipped' => '已存在，跳过',
            default => '未知操作',
        };
    }
}
