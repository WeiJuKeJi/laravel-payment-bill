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
        bool $autoImport = false,
        string $billPeriod = 'day'
    ): array {
        // 验证支付渠道
        $channel = $this->resolveChannel($paymentChannelId);
        if (! $channel) {
            throw new InvalidArgumentException("未找到支付渠道：{$paymentChannelId}");
        }

        $billType = strtoupper($billType);
        $billPeriod = strtolower($billPeriod);
        if (! in_array($billPeriod, ['day', 'month'], true)) {
            throw new InvalidArgumentException('账单周期仅支持 day 或 month');
        }

        if ($billPeriod === 'month' && $channel->channel !== 'wechat') {
            throw new InvalidArgumentException('月账单拆分导入当前仅支持微信账单');
        }

        if ($billPeriod === 'day' && $this->containsMonthlyBillFilename($files)) {
            if ($channel->channel !== 'wechat') {
                throw new InvalidArgumentException('检测到月账单文件名，但月账单拆分导入当前仅支持微信账单');
            }

            $billPeriod = 'month';
        }

        // 解析文件
        $parsedFiles = $billPeriod === 'month'
            ? $this->parseUploadedMonthlyWechatFiles($files)
            : $this->parseUploadedFiles($files);

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
     * 解析上传的微信月账单文件，并按交易时间拆分为日账单文件。
     *
     * @param  array<UploadedFile>  $files
     * @return array<array{path: string, filename: string, date: Carbon, size: int, source_filename: string, temporary: bool}>
     */
    private function parseUploadedMonthlyWechatFiles(array $files): array
    {
        $parsed = [];
        $splitter = new WechatBillMonthlySplitter();

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->getRealPath();
            if (! $path) {
                throw new InvalidArgumentException('无法读取上传的月账单文件：'.$file->getClientOriginalName());
            }

            $parsed = array_merge(
                $parsed,
                $splitter->split($path, $file->getClientOriginalName())
            );
        }

        usort($parsed, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        return $parsed;
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
            if ($this->looksLikeDateRangeFilename($filename)) {
                throw new InvalidArgumentException("文件名疑似月账单：{$filename}。请将账单周期设置为 month 后导入。");
            }

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
        if (preg_match_all('/(?<!\d)(\d{4}-\d{2}-\d{2})(?!\d)/', $nameWithoutExt, $matches)) {
            foreach ($matches[1] as $dateValue) {
                $date = $this->createStrictDate('Y-m-d', $dateValue);
                if ($date) {
                    return $date;
                }
            }
        }

        // 尝试匹配 YYYYMMDD 格式
        if (preg_match_all('/(?<!\d)(\d{8})(?!\d)/', $nameWithoutExt, $matches)) {
            foreach ($matches[1] as $dateValue) {
                $date = $this->createStrictDate('Ymd', $dateValue);
                if ($date) {
                    return $date;
                }
            }
        }

        return null;
    }

    private function createStrictDate(string $format, string $value): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('!'.$format, $value);
        } catch (Throwable) {
            return null;
        }

        if (! $date || $date->format($format) !== $value) {
            return null;
        }

        return $date;
    }

    private function looksLikeDateRangeFilename(string $filename): bool
    {
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        preg_match_all('/(?<!\d)(\d{4}-\d{2}-\d{2})(?!\d)/', $nameWithoutExt, $dashMatches);
        preg_match_all('/(?<!\d)(\d{8})(?!\d)/', $nameWithoutExt, $compactMatches);

        $validDates = [];
        foreach ($dashMatches[1] as $dateValue) {
            if ($this->createStrictDate('Y-m-d', $dateValue)) {
                $validDates[] = $dateValue;
            }
        }

        foreach ($compactMatches[1] as $dateValue) {
            if ($this->createStrictDate('Ymd', $dateValue)) {
                $validDates[] = $dateValue;
            }
        }

        return count(array_unique($validDates)) >= 2;
    }

    /**
     * 判断上传文件中是否包含微信商户平台月账单常见的日期范围文件名。
     *
     * @param  array<UploadedFile>  $files
     */
    private function containsMonthlyBillFilename(array $files): bool
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $this->looksLikeDateRangeFilename($file->getClientOriginalName())) {
                return true;
            }
        }

        return false;
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
                    'source_filename' => $file['source_filename'] ?? $file['filename'],
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
                    'source_filename' => $file['source_filename'] ?? $file['filename'],
                    'date' => isset($file['date']) ? $file['date']->format('Y-m-d') : null,
                    'action' => 'failed',
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            } finally {
                $this->cleanupTemporaryFile($file);
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
        $extension = isset($file['file'])
            ? strtolower($file['file']->getClientOriginalExtension())
            : strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = 'csv';
        }

        $directory = sprintf(
            'payment/bills/%s/%d',
            $channel->channel,
            $channel->id
        );

        $filename = sprintf('%s.%s', $billDate->format('Ymd'), $extension);
        $path = $directory.'/'.$filename;

        if (isset($file['file'])) {
            Storage::disk($this->storageDisk)->putFileAs($directory, $file['file'], $filename);
        } else {
            Storage::disk($this->storageDisk)->put($path, file_get_contents($file['path']));
        }

        return $path;
    }

    /**
     * 清理月账单拆分产生的临时日账单文件。
     */
    private function cleanupTemporaryFile(array $file): void
    {
        if (! ($file['temporary'] ?? false)) {
            return;
        }

        $path = $file['path'] ?? null;
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
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
