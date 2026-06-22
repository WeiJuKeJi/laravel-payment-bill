<?php

namespace WeiJuKeJi\PaymentBill\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use WeiJuKeJi\PaymentBill\Services\BillImportService;
use WeiJuKeJi\PaymentBill\Services\WechatBillMonthlySplitter;
use Throwable;

/**
 * 从本地目录批量导入历史账单文件到系统中。
 */
class ImportLocalBillFilesCommand extends Command
{
    protected $signature = 'payment-bill:import-local-files
        {--directory= : 本地目录路径（必填）}
        {--channel= : 支付渠道ID或标识（必填）}
        {--pattern=*.csv : 文件名匹配模式}
        {--bill-type=ALL : 账单类型，默认为 ALL}
        {--bill-period=day : 账单周期，day 或 month。month 会按交易时间拆分微信月账单}
        {--auto-import : 导入文件后自动触发账单数据导入任务}
        {--force : 强制覆盖已存在的记录}
        {--dry-run : 预览模式，不实际执行操作}
        {--interactive : 交互式模式，手动选择要导入的文件}';

    protected $description = '从本地目录批量导入历史账单文件，创建 BillDownload 记录并可选择性触发导入任务';

    protected string $storageDisk;

    public function handle(BillImportService $importService): int
    {
        $this->storageDisk = config('payment-bill.storage_disk', 'local');

        // 验证参数
        $directory = $this->option('directory');
        if (!$directory) {
            $this->error('请使用 --directory 参数指定本地目录路径。');
            return self::FAILURE;
        }

        if (!File::isDirectory($directory)) {
            $this->error("目录不存在或无法访问：{$directory}");
            return self::FAILURE;
        }

        $channelIdentifier = $this->option('channel');
        if (!$channelIdentifier) {
            $this->error('请使用 --channel 参数指定支付渠道ID或标识。');
            return self::FAILURE;
        }

        $channel = $this->resolveChannel($channelIdentifier);
        if (!$channel) {
            return self::FAILURE;
        }

        $this->info("支付渠道：{$channel->name}（{$channel->channel}，ID: {$channel->id}）");

        $billPeriod = strtolower((string) ($this->option('bill-period') ?? 'day'));
        if (!in_array($billPeriod, ['day', 'month'], true)) {
            $this->error('bill-period 参数仅支持 day 或 month。');
            return self::FAILURE;
        }

        if ($billPeriod === 'month' && $channel->channel !== 'wechat') {
            $this->error('月账单拆分导入当前仅支持微信账单。');
            return self::FAILURE;
        }

        // 扫描文件
        $pattern = $this->option('pattern') ?? '*.csv';
        if (is_array($pattern)) {
            $pattern = '*.csv';
        }
        $files = $this->scanFiles($directory, $pattern);

        if (empty($files)) {
            $this->warn("未找到匹配的文件：{$directory}/{$pattern}");
            return self::SUCCESS;
        }

        $this->info("找到 ".count($files)." 个匹配的文件。");

        // 解析文件
        $parsedFiles = $billPeriod === 'month'
            ? $this->parseMonthlyWechatFiles($files)
            : $this->parseFiles($files);
        if (empty($parsedFiles)) {
            $this->warn('没有可以解析的有效文件。');
            return self::SUCCESS;
        }

        // 交互式选择
        if ($this->option('interactive')) {
            $allParsedFiles = $parsedFiles;
            $parsedFiles = $this->interactiveSelect($parsedFiles);
            $this->cleanupUnselectedParsedFiles($allParsedFiles, $parsedFiles);
            if (empty($parsedFiles)) {
                $this->info('未选择任何文件。');
                return self::SUCCESS;
            }
        }

        // 显示将要处理的文件
        $this->displayFilesTable($parsedFiles);

        if ($this->option('dry-run')) {
            $this->warn('预览模式，未实际执行操作。');
            $this->cleanupParsedFiles($parsedFiles);
            return self::SUCCESS;
        }

        // 确认执行
        if (!$this->option('interactive') && !$this->confirm('确认导入以上文件？', true)) {
            $this->info('操作已取消。');
            $this->cleanupParsedFiles($parsedFiles);
            return self::SUCCESS;
        }

        // 批量导入
        $billType = strtoupper($this->option('bill-type') ?? 'ALL');
        $force = (bool) $this->option('force');
        $autoImport = (bool) $this->option('auto-import');

        $stats = $this->importFiles($parsedFiles, $channel, $billType, $force, $autoImport, $importService);

        // 显示统计结果
        $this->displayStats($stats);

        return $stats['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 解析渠道标识，支持ID、channel或name。
     */
    protected function resolveChannel(string $identifier): ?PaymentChannel
    {
        $channel = PaymentChannel::query()
            ->where(function ($query) use ($identifier) {
                $query->where('id', $identifier)
                    ->orWhere('channel', $identifier)
                    ->orWhere('name', $identifier);
            })
            ->first();

        if (!$channel) {
            $this->error("未找到匹配的支付渠道：{$identifier}");
            return null;
        }

        if (!$channel->is_enabled) {
            $this->warn("警告：渠道 {$channel->name} 当前已禁用。");
        }

        return $channel;
    }

    /**
     * 扫描目录获取匹配的文件。
     */
    protected function scanFiles(string $directory, string $pattern): array
    {
        $files = File::glob(rtrim($directory, '/') . '/' . $pattern);

        // 过滤掉目录，只保留文件
        return array_filter($files, fn ($file) => File::isFile($file));
    }

    /**
     * 解析文件名提取日期和元信息。
     */
    protected function parseFiles(array $files): array
    {
        $parsed = [];

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            if ($this->looksLikeDateRangeFilename($filename)) {
                $this->warn("文件名疑似月账单，请使用 --bill-period=month 导入，跳过：{$filename}");
                continue;
            }

            $date = $this->extractDateFromFilename($filename);

            if (!$date) {
                $this->warn("无法从文件名解析日期，跳过：{$filename}");
                continue;
            }

            $parsed[] = [
                'path' => $filePath,
                'filename' => $filename,
                'date' => $date,
                'size' => File::size($filePath),
            ];
        }

        // 按日期排序
        usort($parsed, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        return $parsed;
    }

    /**
     * 解析微信月账单文件，并按交易时间拆分为日账单文件。
     */
    protected function parseMonthlyWechatFiles(array $files): array
    {
        $parsed = [];
        $splitter = new WechatBillMonthlySplitter();

        foreach ($files as $filePath) {
            $filename = basename($filePath);

            try {
                $dailyFiles = $splitter->split($filePath, $filename);
                $parsed = array_merge($parsed, $dailyFiles);
                $this->line(sprintf(
                    '已拆分月账单：%s → %d 个日账单',
                    $filename,
                    count($dailyFiles)
                ));
            } catch (Throwable $e) {
                $this->warn("月账单拆分失败，跳过：{$filename}，原因：{$e->getMessage()}");
            }
        }

        usort($parsed, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        return $parsed;
    }

    /**
     * 从文件名中提取日期。
     * 支持格式：2022-07-24_ALL.csv、20220724.csv 等
     */
    protected function extractDateFromFilename(string $filename): ?Carbon
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

    protected function createStrictDate(string $format, string $value): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('!'.$format, $value);
        } catch (Throwable) {
            return null;
        }

        if (!$date || $date->format($format) !== $value) {
            return null;
        }

        return $date;
    }

    protected function looksLikeDateRangeFilename(string $filename): bool
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
     * 交互式选择要导入的文件。
     */
    protected function interactiveSelect(array $parsedFiles): array
    {
        $this->info('请选择要导入的文件（多选，使用空格分隔序号）：');

        foreach ($parsedFiles as $index => $file) {
            $size = $this->formatFileSize($file['size']);
            $this->line(sprintf(
                '  [%d] %s - %s (%s)',
                $index + 1,
                $file['date']->format('Y-m-d'),
                $file['filename'],
                $size
            ));
        }

        $this->line('  [0] 全部选择');
        $this->line('');

        $input = $this->ask('请输入序号（如：1 3 5 或 0 表示全选）');

        if (!$input) {
            return [];
        }

        $input = trim($input);

        // 全选
        if ($input === '0') {
            return $parsedFiles;
        }

        // 解析选择的序号
        $selected = [];
        $indices = array_filter(array_map('trim', explode(' ', $input)));

        foreach ($indices as $index) {
            if (!is_numeric($index)) {
                continue;
            }

            $index = (int) $index;
            if (isset($parsedFiles[$index - 1])) {
                $selected[] = $parsedFiles[$index - 1];
            }
        }

        return $selected;
    }

    /**
     * 显示文件列表表格。
     */
    protected function displayFilesTable(array $parsedFiles): void
    {
        $rows = array_map(function ($file) {
            return [
                $file['date']->format('Y-m-d'),
                $file['filename'],
                $file['source_filename'] ?? $file['filename'],
                $this->formatFileSize($file['size']),
            ];
        }, $parsedFiles);

        $this->table(['账单日期', '文件名', '来源文件', '大小'], $rows);
    }

    /**
     * 批量导入文件。
     */
    protected function importFiles(
        array $parsedFiles,
        PaymentChannel $channel,
        string $billType,
        bool $force,
        bool $autoImport,
        BillImportService $importService
    ): array {
        $stats = [
            'total' => count($parsedFiles),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'imported' => 0,
        ];

        foreach ($parsedFiles as $file) {
            try {
                $result = $this->importFile($file, $channel, $billType, $force);

                if ($result['action'] === 'created') {
                    $stats['created']++;
                    $this->info(sprintf(
                        '✅ [新建] %s - %s',
                        $file['date']->format('Y-m-d'),
                        $file['filename']
                    ));
                } elseif ($result['action'] === 'updated') {
                    $stats['updated']++;
                    $this->info(sprintf(
                        '🔄 [更新] %s - %s',
                        $file['date']->format('Y-m-d'),
                        $file['filename']
                    ));
                } else {
                    $stats['skipped']++;
                    $this->line(sprintf(
                        'ℹ️ [跳过] %s - %s（已存在）',
                        $file['date']->format('Y-m-d'),
                        $file['filename']
                    ));
                }

                // 自动触发导入任务
                if ($autoImport && $result['record']) {
                    try {
                        if ($channel->channel === 'wechat') {
                            $importService->dispatchWechatImport($result['record'], $force);
                        } elseif ($channel->channel === 'alipay') {
                            $importService->dispatchAlipayImport($result['record'], $force);
                        }
                        $stats['imported']++;
                        $this->comment("  → 已派发导入任务");
                    } catch (Throwable $e) {
                        $this->warn("  → 派发导入任务失败：{$e->getMessage()}");
                    }
                }
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->error(sprintf(
                    '❌ [失败] %s - %s：%s',
                    $file['date']->format('Y-m-d'),
                    $file['filename'],
                    $e->getMessage()
                ));
            } finally {
                $this->cleanupParsedFile($file);
            }
        }

        return $stats;
    }

    /**
     * 导入单个文件。
     */
    protected function importFile(array $file, PaymentChannel $channel, string $billType, bool $force): array
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
            } elseif (!$force && $record->download_status === BillDownload::DOWNLOAD_STATUS_COMPLETED) {
                // 已存在且不强制覆盖
                return ['action' => 'skipped', 'record' => $record];
            }

            // 存储文件到规范路径
            $storagePath = $this->storeFile($file, $channel, $file['date'], $billType);

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
     * 将文件存储到规范路径。
     */
    protected function storeFile(array $file, PaymentChannel $channel, Carbon $billDate, string $billType): string
    {
        $extension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));

        $directory = sprintf(
            'payment/bills/%s/%d',
            $channel->channel,
            $channel->id
        );

        $filename = sprintf('%s.%s', $billDate->format('Ymd'), $extension);
        $path = $directory . '/' . $filename;

        $contents = File::get($file['path']);
        Storage::disk($this->storageDisk)->put($path, $contents);

        return $path;
    }

    /**
     * 格式化文件大小。
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 清理拆分月账单时产生的临时文件。
     */
    protected function cleanupParsedFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->cleanupParsedFile($file);
        }
    }

    /**
     * 清理交互式模式中未选中的拆分临时文件。
     */
    protected function cleanupUnselectedParsedFiles(array $allFiles, array $selectedFiles): void
    {
        $selectedPaths = array_flip(array_filter(array_map(
            fn ($file) => $file['path'] ?? null,
            $selectedFiles
        )));

        foreach ($allFiles as $file) {
            $path = $file['path'] ?? null;
            if (!is_string($path) || isset($selectedPaths[$path])) {
                continue;
            }

            $this->cleanupParsedFile($file);
        }
    }

    /**
     * 清理单个临时文件。
     */
    protected function cleanupParsedFile(array $file): void
    {
        if (!($file['temporary'] ?? false)) {
            return;
        }

        $path = $file['path'] ?? null;
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * 显示统计结果。
     */
    protected function displayStats(array $stats): void
    {
        $this->newLine();
        $this->info('========== 导入完成 ==========');
        $this->line("总计：{$stats['total']} 个文件");
        $this->line("新建：{$stats['created']} 条记录");
        $this->line("更新：{$stats['updated']} 条记录");
        $this->line("跳过：{$stats['skipped']} 条记录");
        $this->line("失败：{$stats['failed']} 条记录");

        if ($stats['imported'] > 0) {
            $this->line("已派发导入任务：{$stats['imported']} 个");
        }

        $this->info('==============================');
    }
}
