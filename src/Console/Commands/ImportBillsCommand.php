<?php

namespace WeiJuKeJi\PaymentBill\Console\Commands;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Services\BillImportService;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use Throwable;

/**
 * 账单导入命令，当前支持微信账单。
 */
class ImportBillsCommand extends Command
{
    protected $signature = 'payment-bill:import
        {--channel=* : 指定支付渠道ID或标识，可多次传入}
        {--from= : 导入开始日期，格式 Y-m-d，默认为昨日}
        {--to= : 导入结束日期，格式 Y-m-d，默认与开始日期相同}
        {--force : 强制重新导入，即使已完成}
        {--only-failed : 仅重试导入失败的记录}
        {--bill-type=all : 导入的账单类型，可选 wechat|alipay|all，默认全部}';

    protected $description = '将已下载的账单文件导入数据库，支持微信与支付宝账单的异步导入。';

    public function handle(BillImportService $importService): int
    {
        $billType = strtolower((string) $this->option('bill-type'));
        if (!in_array($billType, ['wechat', 'alipay', 'all'], true)) {
            $this->error('bill-type 参数仅支持 wechat、alipay 或 all。');

            return self::FAILURE;
        }

        [$fromDate, $toDate] = $this->resolveDateRange();
        if (!$fromDate || !$toDate) {
            return self::FAILURE;
        }

        $channels = $this->resolveChannels();
        if ($channels->isEmpty()) {
            $this->warn('未找到任何匹配的支付渠道，请检查参数。');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $onlyFailed = (bool) $this->option('only-failed');
        $dates = CarbonPeriod::create($fromDate, $toDate);

        $allowedChannels = match ($billType) {
            'wechat' => ['wechat'],
            'alipay' => ['alipay'],
            default => ['wechat', 'alipay'],
        };

        $dispatched = 0;
        $filtered = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($dates as $date) {
            foreach ($channels as $channel) {
                if (!in_array($channel->channel, $allowedChannels, true)) {
                    $this->warn(sprintf(
                        '跳过渠道 %s（%s）：与 bill-type 参数不匹配。',
                        $channel->name,
                        $channel->channel
                    ));
                    ++$filtered;
                    continue;
                }

                $query = BillDownload::query()
                    ->where('payment_channel_id', $channel->getKey())
                    ->whereDate('bill_date', '=', $date->toDateString())
                    ->where('bill_type', 'ALL');

                if ($onlyFailed) {
                    $query->where('import_status', BillDownload::IMPORT_STATUS_FAILED);
                }

                $downloads = $query->get();
                if ($downloads->isEmpty()) {
                    $this->line(sprintf(
                        'ℹ️ 渠道 %s 日期 %s 没有可导入的记录。',
                        $channel->name,
                        $date->toDateString()
                    ));
                    continue;
                }

                foreach ($downloads as $download) {
                    if (!$force && $download->import_status === BillDownload::IMPORT_STATUS_COMPLETED) {
                        $this->line(sprintf(
                            'ℹ️ 渠道 %s 日期 %s 记录 #%d 已导入，跳过。',
                            $channel->name,
                            $date->toDateString(),
                            $download->getKey()
                        ));
                        ++$skipped;
                        continue;
                    }

                    try {
                        if ($channel->channel === 'wechat') {
                            $importService->dispatchWechatImport($download, $force);
                        } elseif ($channel->channel === 'alipay') {
                            $importService->dispatchAlipayImport($download, $force);
                        } else {
                            throw new InvalidArgumentException('不支持的账单渠道：'.$channel->channel);
                        }

                        $this->info(sprintf(
                            '✅ 已派发导入任务：渠道 %s（%s） 日期 %s 记录 #%d',
                            $channel->name,
                            $channel->channel,
                            $date->toDateString(),
                            $download->getKey()
                        ));
                        ++$dispatched;
                    } catch (Throwable $exception) {
                        $this->error(sprintf(
                            '❌ 渠道 %s 日期 %s 记录 #%d 派发失败：%s',
                            $channel->name,
                            $date->toDateString(),
                            $download->getKey(),
                            $exception->getMessage()
                        ));
                        ++$failed;
                    }
                }
            }
        }

        $this->line(sprintf(
            '完成：已派发 %d 条任务，过滤 %d 条，跳过 %d 条，失败 %d 条。',
            $dispatched,
            $filtered,
            $skipped,
            $failed
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 根据命令参数解析日期范围，默认昨日。
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function resolveDateRange(): array
    {
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        $from = $fromOption ? $this->parseDate($fromOption, 'from') : Carbon::yesterday();
        $to = $toOption ? $this->parseDate($toOption, 'to') : $from;

        if (!$from || !$to) {
            return [null, null];
        }

        if ($from->greaterThan($to)) {
            $this->error('参数错误：开始日期不能晚于结束日期。');

            return [null, null];
        }

        return [$from->copy()->startOfDay(), $to->copy()->startOfDay()];
    }

    protected function parseDate(string $value, string $label): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (Throwable) {
            $this->error(sprintf('参数错误：%s 日期格式应为 Y-m-d，当前值 %s。', $label, $value));

            return null;
        }
    }

    /**
     * 根据参数解析目标渠道，默认选择所有启用的渠道。
     */
    protected function resolveChannels(): Collection
    {
        $identifiers = (array) $this->option('channel');
        $query = PaymentChannel::query()->where('is_enabled', true);

        if (!empty($identifiers)) {
            $query->where(function ($builder) use ($identifiers) {
                foreach ($identifiers as $identifier) {
                    $identifier = trim((string) $identifier);
                    if ($identifier === '') {
                        continue;
                    }

                    $builder->orWhere('id', $identifier)
                        ->orWhere('channel', $identifier)
                        ->orWhere('name', $identifier);
                }
            });
        }

        $channels = $query->get();

        if (!empty($identifiers)) {
            $matched = $channels->flatMap(fn (PaymentChannel $channel) => [$channel->id, $channel->channel, $channel->name])
                ->filter()
                ->map('strval')
                ->all();

            $missed = array_filter($identifiers, static fn ($identifier) => !in_array((string) $identifier, $matched, true));
            if (!empty($missed)) {
                $this->warn('部分渠道未匹配成功：'.implode(', ', $missed));
            }
        }

        return $channels;
    }
}
