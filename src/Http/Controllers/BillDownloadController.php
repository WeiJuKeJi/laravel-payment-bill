<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use WeiJuKeJi\PaymentBill\Http\Requests\BillDownload\BillDownloadCalendarStatsRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\BillDownload\BillDownloadCalendarRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\BillDownload\BillDownloadFilterRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\BillDownload\BillDownloadStoreRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\BillDownloadResource;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use WeiJuKeJi\PaymentBill\Services\BillDownloadService;
use WeiJuKeJi\PaymentBill\Services\BillImportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillDownloadController extends Controller
{
    public function __construct(
        private readonly BillDownloadService $billDownloadService,
        private readonly BillImportService $billImportService
    ) {}

    /**
     * 列出账单下载记录，围绕标准过滤器提供统一查询能力。
     */
    public function index(BillDownloadFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $order = Arr::pull($filters, 'order', 'desc');
        $onlyFailed = (bool) Arr::pull($filters, 'only_failed', false);
        $perPage = $this->resolvePerPage($filters, 15, 500);

        $order = $order === 'asc' ? 'asc' : 'desc';

        $query = BillDownload::with('paymentChannel')
            ->filter($filters)
            ->orderBy('bill_date', $order)
            ->orderBy('id', $order === 'asc' ? 'asc' : 'desc');

        if ($onlyFailed) {
            $query->where('download_status', BillDownload::DOWNLOAD_STATUS_FAILED);
        }

        $paginator = $query->paginate($perPage);

        return $this->respondWithPagination($paginator, BillDownloadResource::class);
    }

    /**
     * 按支付渠道和年份输出账单下载日历。
     */
    public function calendar(BillDownloadCalendarRequest $request): JsonResponse
    {
        $data = $request->validated();
        $year = (int) $data['year'];
        $paymentChannelId = (int) $data['payment_channel_id'];
        $startDate = sprintf('%d-01-01', $year);
        $endDate = sprintf('%d-12-31', $year);

        $query = BillDownload::query()
            ->with('paymentChannel')
            ->where('payment_channel_id', $paymentChannelId)
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->orderBy('bill_date')
            ->orderBy('id');

        if (! empty($data['bill_type'])) {
            $query->where('bill_type', strtoupper($data['bill_type']));
        }

        $records = $query->get();
        $days = $this->buildCalendarDays($records);
        $channel = PaymentChannel::query()->find($paymentChannelId);

        return $this->success([
            'year' => $year,
            'payment_channel_id' => $paymentChannelId,
            'channel' => $channel ? [
                'id' => $channel->id,
                'name' => $channel->name,
                'channel' => $channel->channel,
                'mode' => $channel->mode,
            ] : null,
            'stats' => $this->buildCalendarStats($days, $year),
            'monthly_totals' => $this->buildMonthlyTotals($records),
            'days' => $days,
        ]);
    }

    /**
     * 一次返回全部支付渠道的年度下载状态统计，供日历左侧渠道列表使用。
     */
    public function calendarStats(BillDownloadCalendarStatsRequest $request): JsonResponse
    {
        $year = (int) $request->validated('year');
        $startDate = sprintf('%d-01-01', $year);
        $endDate = sprintf('%d-12-31', $year);

        $records = BillDownload::query()
            ->select(['payment_channel_id', 'bill_date', 'download_status'])
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->get();

        $priority = [
            BillDownload::DOWNLOAD_STATUS_FAILED => 0,
            BillDownload::DOWNLOAD_STATUS_PROCESSING => 1,
            BillDownload::DOWNLOAD_STATUS_PENDING => 2,
            BillDownload::DOWNLOAD_STATUS_NO_STATEMENT => 3,
            BillDownload::DOWNLOAD_STATUS_COMPLETED => 4,
        ];

        $emptyStats = [
            BillDownload::DOWNLOAD_STATUS_COMPLETED => 0,
            BillDownload::DOWNLOAD_STATUS_FAILED => 0,
            BillDownload::DOWNLOAD_STATUS_PROCESSING => 0,
            BillDownload::DOWNLOAD_STATUS_PENDING => 0,
            BillDownload::DOWNLOAD_STATUS_NO_STATEMENT => 0,
        ];

        $channels = PaymentChannel::query()
            ->pluck('id')
            ->mapWithKeys(fn ($channelId) => [(string) $channelId => $emptyStats])
            ->all();

        $recordStats = $records
            ->groupBy('payment_channel_id')
            ->map(function (Collection $channelRecords) use ($emptyStats, $priority): array {
                $stats = $emptyStats;

                $channelRecords
                    ->groupBy(fn (BillDownload $record) => $record->bill_date?->toDateString())
                    ->each(function (Collection $dateRecords) use (&$stats, $priority): void {
                        $status = $dateRecords
                            ->sortBy(fn (BillDownload $record) => $priority[$record->download_status] ?? 99)
                            ->first()
                            ?->download_status;

                        if ($status !== null && array_key_exists($status, $stats)) {
                            $stats[$status]++;
                        }
                    });

                return $stats;
            })
            ->all();

        foreach ($recordStats as $channelId => $stats) {
            $channels[(string) $channelId] = $stats;
        }

        return $this->success(['year' => $year, 'channels' => $channels]);
    }

    public function store(BillDownloadStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $billType = strtoupper($data['bill_type'] ?? 'ALL');

        $options = array_filter([
            'force' => $data['force'] ?? false,
            'tar_type' => $data['tar_type'] ?? null,
        ], static fn ($value) => $value !== null && $value !== false);

        try {
            $record = $this->billDownloadService->download(
                $data['payment_channel_id'],
                $data['bill_date'],
                $billType,
                $options
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 400);
        }

        return $this->respondWithResource($record, BillDownloadResource::class, 'created', 200);
    }

    /**
     * @param  Collection<int, BillDownload>  $records
     * @return array<string, mixed>
     */
    protected function buildCalendarDays(Collection $records): array
    {
        return $records
            ->groupBy(fn (BillDownload $record) => $record->bill_date?->toDateString())
            ->filter(fn (Collection $records, ?string $date) => ! empty($date))
            ->map(function (Collection $dateRecords) {
                $record = $this->pickCalendarRecord($dateRecords);
                $payload = BillDownloadResource::make($record)->toArray(request());

                if ($dateRecords->count() > 1) {
                    $payload['records'] = $dateRecords
                        ->values()
                        ->map(fn (BillDownload $item) => BillDownloadResource::make($item)->toArray(request()))
                        ->all();
                }

                return $payload;
            })
            ->all();
    }

    /**
     * @param  Collection<int, BillDownload>  $records
     */
    protected function pickCalendarRecord(Collection $records): BillDownload
    {
        $priority = [
            BillDownload::DOWNLOAD_STATUS_FAILED => 0,
            BillDownload::DOWNLOAD_STATUS_PROCESSING => 1,
            BillDownload::DOWNLOAD_STATUS_PENDING => 2,
            BillDownload::DOWNLOAD_STATUS_NO_STATEMENT => 3,
            BillDownload::DOWNLOAD_STATUS_COMPLETED => 4,
        ];

        return $records
            ->sortBy(fn (BillDownload $record) => $priority[$record->download_status] ?? 99)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $days
     * @return array<string, int>
     */
    protected function buildCalendarStats(array $days, int $year): array
    {
        $stats = [
            BillDownload::DOWNLOAD_STATUS_COMPLETED => 0,
            BillDownload::DOWNLOAD_STATUS_FAILED => 0,
            BillDownload::DOWNLOAD_STATUS_PROCESSING => 0,
            BillDownload::DOWNLOAD_STATUS_PENDING => 0,
            BillDownload::DOWNLOAD_STATUS_NO_STATEMENT => 0,
            'missing' => 0,
        ];

        foreach ($days as $day) {
            $status = $day['download_status'] ?? null;

            if ($status && array_key_exists($status, $stats)) {
                $stats[$status]++;
            }
        }

        $stats['missing'] = max($this->daysInYear($year) - count($days), 0);

        return $stats;
    }

    /**
     * @param  Collection<int, BillDownload>  $records
     * @return array<string, array<string, string>>
     */
    protected function buildMonthlyTotals(Collection $records): array
    {
        $totals = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthKey = sprintf('%02d', $month);
            $totals[$monthKey] = $this->emptyAmountTotals();
        }

        foreach ($records as $record) {
            if (! $record->bill_date) {
                continue;
            }

            $monthKey = $record->bill_date->format('m');
            $totals[$monthKey]['order_amount'] = $this->addAmount(
                $totals[$monthKey]['order_amount'],
                $record->bill_summary_order_amount
            );
            $totals[$monthKey]['refund_amount'] = $this->addAmount(
                $totals[$monthKey]['refund_amount'],
                $record->bill_summary_refund_amount
            );
            $totals[$monthKey]['fee_amount'] = $this->addAmount(
                $totals[$monthKey]['fee_amount'],
                $record->bill_summary_fee_amount
            );
        }

        foreach ($totals as &$monthTotals) {
            $monthTotals['settlement_amount'] = bcsub(
                $monthTotals['order_amount'],
                $monthTotals['refund_amount'],
                2
            );
        }
        unset($monthTotals);

        return $totals;
    }

    /**
     * @return array<string, string>
     */
    protected function emptyAmountTotals(): array
    {
        return [
            'order_amount' => '0.00',
            'refund_amount' => '0.00',
            'settlement_amount' => '0.00',
            'fee_amount' => '0.00',
        ];
    }

    protected function addAmount(string $left, mixed $right): string
    {
        return bcadd($left, $this->normalizeAmount($right), 2);
    }

    protected function normalizeAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function daysInYear(int $year): int
    {
        return checkdate(2, 29, $year) ? 366 : 365;
    }

    public function show(BillDownload $billDownload): JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        return $this->respondWithResource($billDownload, BillDownloadResource::class);
    }

    /**
     * 下载账单文件到浏览器
     */
    public function downloadFile(BillDownload $billDownload): StreamedResponse|JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        // 检查下载状态
        if ($billDownload->download_status !== BillDownload::DOWNLOAD_STATUS_COMPLETED) {
            return $this->error('账单文件尚未下载完成', 400);
        }

        // 检查文件路径
        if (! $billDownload->file_path) {
            return $this->error('账单文件路径不存在', 404);
        }

        // 检查文件是否存在
        if (! Storage::exists($billDownload->file_path)) {
            return $this->error('账单文件不存在', 404);
        }

        // 生成文件名
        $channel = $billDownload->paymentChannel;
        $channelName = $channel ? $channel->channel : 'unknown';
        $billDate = str_replace('-', '', $billDownload->bill_date);
        $billType = $billDownload->bill_type;
        $extension = pathinfo($billDownload->file_path, PATHINFO_EXTENSION);
        $filename = sprintf('%s_%s_%s.%s', $channelName, $billDate, $billType, $extension);

        return Storage::download($billDownload->file_path, $filename);
    }

    /**
     * 重新下载账单
     *
     * 从支付平台重新下载该账单文件到服务器。
     */
    public function download(BillDownload $billDownload): JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        // 检查支付渠道
        if (! $billDownload->paymentChannel) {
            return $this->error('账单缺少关联的支付渠道信息', 400);
        }

        try {
            // 强制重新下载
            $record = $this->billDownloadService->download(
                $billDownload->payment_channel_id,
                $billDownload->bill_date,
                $billDownload->bill_type,
                ['force' => true]
            );

            return $this->respondWithResource($record, BillDownloadResource::class, '账单下载任务已派发');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->error('下载任务派发失败：'.$e->getMessage(), 500);
        }
    }

    /**
     * 重新导入账单数据
     *
     * 将账单文件数据重新解析并导入到账单明细表。
     */
    public function import(BillDownload $billDownload): JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        // 检查下载状态
        if ($billDownload->download_status !== BillDownload::DOWNLOAD_STATUS_COMPLETED) {
            return $this->error('账单文件尚未下载完成', 400);
        }

        // 检查文件路径
        if (! $billDownload->file_path) {
            return $this->error('账单文件路径不存在', 400);
        }

        // 检查文件是否存在
        $disk = config('payment-bill.storage_disk');
        if (! Storage::disk($disk)->exists($billDownload->file_path)) {
            return $this->error('账单文件不存在或已被删除', 404);
        }

        // 检查支付渠道
        if (! $billDownload->paymentChannel) {
            return $this->error('账单缺少关联的支付渠道信息', 400);
        }

        $channel = $billDownload->paymentChannel->channel;

        try {
            // 根据渠道类型派发导入任务（强制重新导入）
            if ($channel === 'wechat') {
                $this->billImportService->dispatchWechatImport($billDownload, force: true);
            } elseif ($channel === 'alipay') {
                $this->billImportService->dispatchAlipayImport($billDownload, force: true);
            } else {
                return $this->error("不支持的支付渠道类型：{$channel}", 400);
            }

            // 更新导入状态为待导入
            $billDownload->update([
                'import_status' => BillDownload::IMPORT_STATUS_PENDING,
                'imported_at' => null,
            ]);

            // 重新加载模型以获取最新数据
            $billDownload->refresh();

            return $this->respondWithResource(
                $billDownload,
                BillDownloadResource::class,
                $channel === 'wechat' ? '微信账单导入任务已派发' : '支付宝账单导入任务已派发'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->error('导入任务派发失败：'.$e->getMessage(), 500);
        }
    }
}
