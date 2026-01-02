<?php

namespace WeiJuKeJi\PaymentBill\Console\Commands;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use WeiJuKeJi\PaymentBill\Services\BillDownloadService;
use Throwable;

/**
 * 下载账单的终端命令，支持日期范围、渠道筛选与失败重试等场景。
 */
class DownloadBillsCommand extends Command
{
    protected $signature = 'payment-bill:download
        {--channel=* : 指定支付渠道ID或标识，可多次传入}
        {--from= : 下载开始日期，格式 Y-m-d，默认昨日}
        {--to= : 下载结束日期，格式 Y-m-d，默认与开始日期相同}
        {--bill-type=ALL : 账单类型，默认为 ALL}
        {--tar-type= : 微信账单压缩格式，可选 GZIP|ZIP|CSV|NONE}
        {--force : 强制重新下载，即使已有成功记录}
        {--retry-failed : 仅重试状态为 failed 的历史记录}';

    protected $description = '按渠道与日期下载微信/支付宝账单，支持批量与失败重试。';

    public function handle(BillDownloadService $billDownloadService): int
    {
        $billType = strtoupper($this->option('bill-type') ?? 'ALL');
        $tarType = $this->option('tar-type');
        $force = (bool) $this->option('force');
        $retryFailed = (bool) $this->option('retry-failed');

        [$fromDate, $toDate] = $this->resolveDateRange();
        if (! $fromDate || ! $toDate) {
            return self::FAILURE;
        }

        $channels = $this->resolveChannels();
        if ($channels->isEmpty()) {
            $this->warn('未找到任何匹配的支付渠道，请检查参数。');

            return self::FAILURE;
        }

        if ($retryFailed) {
            return $this->retryFailedDownloads($billDownloadService, $channels, $fromDate, $toDate);
        }

        $dates = CarbonPeriod::create($fromDate, $toDate);
        $success = 0;
        $failed = 0;

        foreach ($dates as $date) {
            foreach ($channels as $channel) {
                $options = array_filter([
                    'force' => $force,
                    'tar_type' => $tarType,
                ], static fn ($value) => $value !== null && $value !== false && $value !== '');

                try {
                    $billDownloadService->download($channel, $date, $billType, $options);
                    $this->info(sprintf(
                        '✅ %s 渠道 %s 日期 %s 下载完成',
                        $channel->channel,
                        $channel->name,
                        $date->toDateString()
                    ));
                    $success++;
                } catch (Throwable $exception) {
                    $this->error(sprintf(
                        '❌ %s 渠道 %s 日期 %s 下载失败：%s',
                        $channel->channel,
                        $channel->name,
                        $date->toDateString(),
                        $exception->getMessage()
                    ));
                    Log::error('账单下载命令执行失败', [
                        'channel_id' => $channel->getKey(),
                        'bill_date' => $date->toDateString(),
                        'bill_type' => $billType,
                        'exception' => $exception,
                    ]);
                    $failed++;
                }
            }
        }

        $this->line(sprintf('完成：成功 %d 次，失败 %d 次。', $success, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 根据命令参数解析日期范围，默认昨日。
     */
    protected function resolveDateRange(): array
    {
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        $from = $fromOption ? $this->parseDate($fromOption, 'from') : Carbon::yesterday();
        $to = $toOption ? $this->parseDate($toOption, 'to') : $from;

        if (! $from || ! $to) {
            return [null, null];
        }

        if ($from->greaterThan($to)) {
            $this->error('参数错误：开始日期不能晚于结束日期。');

            return [null, null];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    protected function parseDate(string $value, string $label): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (Throwable $exception) {
            $this->error(sprintf('参数错误：%s 日期格式应为 Y-m-d，当前值 %s。', $label, $value));

            return null;
        }
    }

    /**
     * 根据参数解析目标渠道，默认选择所有启用的渠道。
     */
    protected function resolveChannels(): Collection
    {
        $identifiers = array_map(static fn ($value) => trim((string) $value), (array) $this->option('channel'));
        $identifiers = array_values(array_filter($identifiers, static fn ($value) => $value !== ''));
        $query = PaymentChannel::query()->where('is_enabled', true);

        if (! empty($identifiers)) {
            $numericIds = array_map('intval', array_filter($identifiers, static fn ($value) => ctype_digit($value)));
            $textualIdentifiers = array_values(array_filter($identifiers, static fn ($value) => ! ctype_digit($value)));

            $query->where(function ($builder) use ($numericIds, $textualIdentifiers) {
                $conditionApplied = false;

                if (! empty($numericIds)) {
                    $builder->whereIn('id', $numericIds);
                    $conditionApplied = true;
                }

                if (! empty($textualIdentifiers)) {
                    $method = $conditionApplied ? 'orWhereIn' : 'whereIn';
                    $builder->{$method}('channel', $textualIdentifiers)
                        ->orWhereIn('name', $textualIdentifiers);
                }
            });
        }

        $channels = $query->get();

        if (! empty($identifiers)) {
            $matched = $channels->flatMap(fn (PaymentChannel $channel) => [$channel->id, $channel->channel, $channel->name])->filter()->map('strval')->all();
            $missed = array_filter($identifiers, static fn ($identifier) => ! in_array((string) $identifier, $matched, true));
            if (! empty($missed)) {
                $this->warn('部分渠道未匹配成功：'.implode(', ', $missed));
            }
        }

        return $channels;
    }

    /**
     * 重试 download 状态为 failed 的历史记录。
     */
    protected function retryFailedDownloads(BillDownloadService $service, Collection $channels, Carbon $fromDate, Carbon $toDate): int
    {
        $channelIds = $channels->pluck('id')->all();

        $query = BillDownload::query()
            ->where('download_status', BillDownload::DOWNLOAD_STATUS_FAILED)
            ->whereBetween('bill_date', [$fromDate->toDateString(), $toDate->toDateString()]);

        if (! empty($channelIds)) {
            $query->whereIn('payment_channel_id', $channelIds);
        }

        $failedRecords = $query->get();

        if ($failedRecords->isEmpty()) {
            $this->info('未找到需要重试的失败记录。');

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        foreach ($failedRecords as $record) {
            $options = ['force' => true];

            try {
                $service->download($record->payment_channel_id, $record->bill_date, $record->bill_type, $options);
                $this->info(sprintf(
                    '🔄 重试成功：渠道 %d 日期 %s 类型 %s',
                    $record->payment_channel_id,
                    $record->bill_date,
                    $record->bill_type
                ));
                $success++;
            } catch (Throwable $exception) {
                $this->error(sprintf(
                    '重试失败：渠道 %d 日期 %s 类型 %s，原因：%s',
                    $record->payment_channel_id,
                    $record->bill_date,
                    $record->bill_type,
                    $exception->getMessage()
                ));
                Log::error('账单下载失败重试仍失败', [
                    'record_id' => $record->id,
                    'channel_id' => $record->payment_channel_id,
                    'bill_date' => $record->bill_date,
                    'bill_type' => $record->bill_type,
                    'exception' => $exception,
                ]);
                $failed++;
            }
        }

        $this->line(sprintf('重试完成：成功 %d 次，失败 %d 次。', $success, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
