<?php

namespace WeiJuKeJi\PaymentBill\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use WeiJuKeJi\PaymentBill\Services\Downloaders\AlipayBillDownloader;
use WeiJuKeJi\PaymentBill\Services\Downloaders\WechatBillDownloader;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * 账单下载编排服务。
 */
class BillDownloadService
{
    /**
     * @var array<string, WechatBillDownloader|AlipayBillDownloader>
     */
    protected array $downloaders;

    protected string $storageDisk;

    public function __construct(
        protected readonly PaymentChannelConfigResolver $configResolver,
        WechatBillDownloader $wechatBillDownloader,
        AlipayBillDownloader $alipayBillDownloader
    ) {
        $this->downloaders = [
            $wechatBillDownloader->channel() => $wechatBillDownloader,
            $alipayBillDownloader->channel() => $alipayBillDownloader,
        ];

        $this->storageDisk = config('finance.payment.storage_disk', 'local');
    }

    /**
     * 发起账单下载。
     */
    public function download(int|PaymentChannel $paymentChannel, string|Carbon $billDate, ?string $billType = null, array $options = []): BillDownload
    {
        $channel = $paymentChannel instanceof PaymentChannel
            ? $paymentChannel
            : PaymentChannel::query()->findOrFail($paymentChannel);

        if (! $channel->is_bill_download_enabled) {
            throw new InvalidArgumentException('支付渠道未开启账单下载。');
        }

        $date = $this->normalizeDate($billDate);
        $normalizedType = strtoupper($billType ?? 'ALL');
        $force = (bool) Arr::get($options, 'force', false);

        $failure = null;

        $record = DB::transaction(function () use ($channel, $date, $normalizedType, $options, $force, &$failure) {
            $record = $this->prepareRecord($channel, $date, $normalizedType, $force);

            if (in_array($record->download_status, [
                BillDownload::DOWNLOAD_STATUS_COMPLETED,
                BillDownload::DOWNLOAD_STATUS_NO_STATEMENT,
            ], true) && ! $force) {
                return $record;
            }

            if ($record->download_status === BillDownload::DOWNLOAD_STATUS_PROCESSING && ! $force) {
                throw new RuntimeException('账单正在下载，请稍后再试。');
            }

            $previousPath = $record->file_path;

            $record->fill([
                'download_status' => BillDownload::DOWNLOAD_STATUS_PROCESSING,
                'download_error' => null,
                'download_started_at' => now(),
                'download_completed_at' => null,
                'download_retry_count' => ($record->download_retry_count ?? 0) + 1,
                'download_last_attempt_at' => now(),
                'import_status' => BillDownload::IMPORT_STATUS_PENDING,
                'file_path' => null,
                'import_total_transaction_count' => null,
                'import_total_new_count' => null,
                'import_total_updated_count' => null,
                'import_total_settlement_amount' => null,
                'import_total_refund_amount' => null,
                'import_total_refund_voucher_amount' => null,
                'import_total_fee_amount' => null,
                'import_total_order_amount' => null,
                'import_total_refund_apply_amount' => null,
                'import_started_at' => null,
                'import_completed_at' => null,
                'import_duration' => null,
                'import_retry_count' => 0,
                'import_last_attempt_at' => null,
                'import_error' => null,
            ])->save();

            try {
                $config = $this->configResolver->resolve($channel);
                $downloader = $this->resolveDownloader($channel->channel);
                $params = $this->buildDownloadParams($channel, $date, $normalizedType, $options);
                $result = $downloader->download($channel, $config, $params);
                $filePath = $this->storeBillFile($channel, $date, $normalizedType, $result);

                $record->fill([
                    'download_status' => BillDownload::DOWNLOAD_STATUS_COMPLETED,
                    'file_path' => $filePath,
                    'download_completed_at' => now(),
                    'download_error' => null,
                ])->save();

                if ($previousPath && $previousPath !== $filePath) {
                    Storage::disk($this->storageDisk)->delete($previousPath);
                }
            } catch (Throwable $exception) {
                $normalizedMessage = $this->normalizeExceptionMessage($exception);

                if ($this->isWechatNoStatement($channel, $normalizedMessage)) {
                    $record->fill([
                        'download_status' => BillDownload::DOWNLOAD_STATUS_NO_STATEMENT,
                        'download_error' => null,
                        'download_completed_at' => now(),
                        'file_path' => null,
                        'bill_summary_transaction_count' => 0,
                        'bill_summary_settlement_amount' => '0.00',
                        'bill_summary_refund_amount' => '0.00',
                        'bill_summary_refund_voucher_amount' => '0.00',
                        'bill_summary_fee_amount' => '0.00',
                        'bill_summary_order_amount' => '0.00',
                        'bill_summary_refund_apply_amount' => '0.00',
                        'import_status' => BillDownload::IMPORT_STATUS_COMPLETED,
                        'import_error' => null,
                        'import_started_at' => now(),
                        'import_completed_at' => now(),
                        'import_duration' => 0,
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

                    Log::info('微信账单不存在，已按空账单完成处理', [
                        'channel_id' => $channel->getKey(),
                        'bill_date' => $date->toDateString(),
                        'bill_type' => $normalizedType,
                        'message' => $normalizedMessage,
                    ]);

                    return $record->fresh();
                }

                $record->fill([
                    'download_status' => $this->resolveFailureStatus($exception),
                    'download_error' => Str::limit($normalizedMessage, 2000),
                    'download_completed_at' => now(),
                ])->save();

                Log::error('账单下载失败', [
                    'channel_id' => $channel->getKey(),
                    'bill_date' => $date->toDateString(),
                    'bill_type' => $normalizedType,
                    'exception' => $exception,
                    'normalized_message' => $normalizedMessage,
                ]);

                $failure = new RuntimeException($normalizedMessage, previous: $exception);
            }

            return $record->fresh();
        });

        if ($failure !== null) {
            throw $failure;
        }

        return $record;
    }

    /**
     * 将输入的日期参数统一转换为当天零点，确保账单按天维度处理。
     */
    protected function normalizeDate(string|Carbon $billDate): Carbon
    {
        return $billDate instanceof Carbon ? $billDate->copy()->startOfDay() : Carbon::parse($billDate)->startOfDay();
    }

    /**
     * 锁定或创建指定渠道与账单日期的下载记录，防止并发冲突。
     */
    protected function prepareRecord(PaymentChannel $channel, Carbon $billDate, string $billType, bool $force): BillDownload
    {
        $query = BillDownload::query()
            ->where('payment_channel_id', $channel->getKey())
            ->whereDate('bill_date', $billDate->toDateString())
            ->where('bill_type', $billType)
            ->lockForUpdate();

        $record = $query->first();

        if ($record === null) {
            $record = new BillDownload([
                'payment_channel_id' => $channel->getKey(),
                'bill_date' => $billDate->toDateString(),
                'bill_type' => $billType,
                'download_status' => BillDownload::DOWNLOAD_STATUS_PENDING,
                'import_status' => BillDownload::IMPORT_STATUS_PENDING,
            ]);

            $record->save();
        } elseif ($force) {
            $record->refresh();
        }

        return $record;
    }

    /**
     * 构造渠道所需的下载参数，处理账单类型映射与微信特有字段。
     *
     * @return array<string, mixed>
     */
    protected function buildDownloadParams(PaymentChannel $channel, Carbon $billDate, string $billType, array $options): array
    {
        $params = [
            'bill_date' => $billDate->toDateString(),
            'bill_type' => $this->mapBillType($channel->channel, $billType),
        ];

        if ($channel->channel === 'wechat') {
            $params['tar_type'] = Arr::get($options, 'tar_type', '');

            if (strtolower((string) $channel->mode) === 'service' && ! empty($channel->wechat_sub_mch_id)) {
                $params['sub_mchid'] = $channel->wechat_sub_mch_id;
            }
        }

        return array_merge($params, Arr::get($options, 'extra', []));
    }

    /**
     * 针对不同渠道将通用账单类型转换为平台识别的枚举值。
     */
    protected function mapBillType(string $channel, string $billType): string
    {
        if ($channel === 'alipay') {
            $type = strtolower($billType);

            return match ($type) {
                'signcustomer', 'fund' => 'signcustomer',
                'trade' => 'trade',
                default => 'trade',
            };
        }

        return strtoupper($billType);
    }

    /**
     * 根据渠道标识选取对应的下载器实例。
     *
     * @return WechatBillDownloader|AlipayBillDownloader
     */
    protected function resolveDownloader(string $channel)
    {
        if (! array_key_exists($channel, $this->downloaders)) {
            throw new InvalidArgumentException('暂不支持的账单渠道：'.$channel);
        }

        return $this->downloaders[$channel];
    }

    /**
     * 将账单响应内容落库并返回文件相对路径，便于后续导入。
     *
     * @param  array{response: ResponseInterface, filename: string|null}  $result
     */
    protected function storeBillFile(PaymentChannel $channel, Carbon $billDate, string $billType, array $result): string
    {
        $response = $result['response'];
        $filename = $this->resolveFilename($channel, $billDate, $billType, $result);

        $directory = sprintf(
            'payment/bills/%s/%d',
            $channel->channel,
            $channel->getKey(),
        );

        $path = $directory.'/'.$filename;

        $contents = $this->readResponseBody($response);
        Storage::disk($this->storageDisk)->put($path, $contents);

        return $path;
    }

    /**
     * 根据渠道规则与建议文件名生成最终文件名。
     *
     * @param  array{response: ResponseInterface, filename: string|null}  $result
     */
    protected function resolveFilename(PaymentChannel $channel, Carbon $billDate, string $billType, array $result): string
    {
        $response = $result['response'];
        $provided = $result['filename'] ?? $this->extractFilename($response);
        $provided = $provided ? basename($provided) : null;
        $providedName = $provided ? pathinfo($provided, PATHINFO_FILENAME) : null;
        $providedExt = $provided ? pathinfo($provided, PATHINFO_EXTENSION) : null;

        if ($channel->channel === 'wechat') {
            $extension = $providedExt ?: 'csv';

            return sprintf('%s.%s', $billDate->format('Ymd'), strtolower($extension));
        }

        if ($channel->channel === 'alipay') {
            $extension = strtolower($providedExt ?: $this->guessExtension($response, $channel->channel) ?: 'zip');

            return sprintf('%s.%s', $billDate->format('Ymd'), $extension);
        }

        $extension = $providedExt ?: $this->guessExtension($response, $channel->channel);
        $basename = $providedName ?: sprintf('%s_%s', $billDate->format('Ymd'), Str::slug($billType, '_'));

        return sprintf('%s.%s', $basename, strtolower($extension));
    }

    /**
     * 尝试从响应头中解析文件名，兼容 UTF-8 编码格式。
     */
    protected function extractFilename(ResponseInterface $response): ?string
    {
        $disposition = $response->getHeaderLine('Content-Disposition');
        if (! $disposition) {
            return null;
        }

        if (preg_match("/filename\*=UTF-8''([^;]+)$/i", $disposition, $matches)) {
            return rawurldecode($matches[1]);
        }

        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 依据响应的内容类型推断应使用的扩展名。
     */
    protected function guessExtension(ResponseInterface $response, string $channel): string
    {
        $contentType = $response->getHeaderLine('Content-Type');

        return match (true) {
            str_contains($contentType, 'gzip') => 'gzip',
            str_contains($contentType, 'zip') => 'zip',
            str_contains($contentType, 'json') => 'json',
            default => $channel === 'wechat' ? 'csv' : 'zip',
        };
    }

    /**
     * 读取响应流的全部内容，自动处理可复位的流指针。
     */
    protected function readResponseBody(ResponseInterface $response): string
    {
        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }

    /**
     * 从异常链条中提取最具代表性的错误消息，用于展示与存档。
     */
    protected function normalizeExceptionMessage(Throwable $throwable): string
    {
        $message = trim($throwable->getMessage());
        if ($message !== '') {
            return $message;
        }

        $previous = $throwable->getPrevious();
        while ($previous !== null) {
            $previousMessage = trim($previous->getMessage());
            if ($previousMessage !== '') {
                return $previousMessage;
            }

            $previous = $previous->getPrevious();
        }

        return sprintf('%s 未提供错误信息', get_class($throwable));
    }

    /**
     * 微信在指定日期无账单时会返回 NO_STATEMENT_EXIST，此场景不应进入失败重试。
     */
    protected function isWechatNoStatement(PaymentChannel $channel, string $message): bool
    {
        if ($channel->channel !== 'wechat') {
            return false;
        }

        return str_contains($message, 'NO_STATEMENT_EXIST');
    }

    /**
     * 当前统一将失败标记为 failed，如后续需要区分可在此扩展。
     */
    protected function resolveFailureStatus(Throwable $throwable): string
    {
        return BillDownload::DOWNLOAD_STATUS_FAILED;
    }
}
