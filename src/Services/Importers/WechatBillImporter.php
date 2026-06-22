<?php

namespace WeiJuKeJi\PaymentBill\Services\Importers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use WeiJuKeJi\PaymentBill\Models\WechatBill;
use RuntimeException;
use SplFileObject;

/**
 * 微信账单导入器，负责将下载的 CSV 文件解析为结构化数据并入库。
 */
class WechatBillImporter
{
    /**
     * @var array<string, string>
     */
    private const HEADER_MAP = [
        '交易时间' => 'trade_time',
        '公众账号id' => 'appid',
        '商户号' => 'mch_id',
        '特约商户号' => 'special_mch_id',
        '设备号' => 'device_id',
        '微信订单号' => 'wechat_transaction_id',
        '商户订单号' => 'out_trade_no',
        '用户标识' => 'user_open_id',
        '交易类型' => 'trade_type',
        '交易状态' => 'trade_state',
        '付款银行' => 'payment_bank',
        '货币种类' => 'currency',
        '应结订单金额' => 'settlement_amount',
        '订单金额' => 'total_amount',
        '代金券金额' => 'voucher_amount',
        '微信退款单号' => 'wechat_refund_no',
        '商户退款单号' => 'out_refund_no',
        '退款金额' => 'refund_amount',
        '充值券退款金额' => 'refund_voucher_amount',
        '退款类型' => 'refund_type',
        '退款状态' => 'refund_status',
        '商品名称' => 'goods_name',
        '商户数据包' => 'goods_info',
        '手续费' => 'fee_amount',
        '费率' => 'fee_rate',
        '申请退款金额' => 'refund_apply_amount',
    ];

    /**
     * @var array<string, callable>
     */
    private const SUMMARY_HANDLERS = [
        '总交易单数' => 'handleSummaryInteger',
        '应结订单总金额' => 'handleSummarySettlement',
        '退款总金额' => 'handleSummaryRefund',
        '充值券退款总金额' => 'handleSummaryRefundVoucher',
        '手续费总金额' => 'handleSummaryFee',
        '订单总金额' => 'handleSummaryOrder',
        '申请退款总金额' => 'handleSummaryRefundApply',
    ];

    private int $paymentChannelId;

    private string $storageDisk;

    private string $relativePath;

    /**
     * 导入统计数据。
     *
     * @var array<string, int|string>
     */
    private array $importTotals = [];

    /**
     * 账单汇总统计数据。
     *
     * @var array<string, int|string>
     */
    private array $summaryStats = [];

    private bool $summaryFound = false;

    /**
     * @param  int  $paymentChannelId
     * @param  string  $storageDisk
     * @param  string  $relativePath
     */
    public function __construct(int $paymentChannelId, string $storageDisk, string $relativePath)
    {
        $this->paymentChannelId = $paymentChannelId;
        $this->storageDisk = $storageDisk;
        $this->relativePath = $relativePath;

        $this->resetImportTotals();
        $this->resetSummaryStats();
    }

    /**
     * 执行导入，并返回导入结果统计。
     *
     * @return array{import_totals: array<string, int|string>, summary: array<string, int|string>}
     */
    public function import(): array
    {
        $path = $this->resolveAbsolutePath();

        $file = new SplFileObject($path, 'rb');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = null;
        $headerMap = [];

        DB::beginTransaction();

        try {
            while (!$file->eof()) {
                $row = $file->fgetcsv();

                if ($row === false || $this->isEmptyRow($row)) {
                    continue;
                }

                $row = $this->normalizeRow($row);

                if ($headers === null) {
                    $headers = $row;
                    $headerMap = $this->buildHeaderMap($headers);
                    continue;
                }

                if ($this->isSummaryLabelRow($row)) {
                    $summaryValues = $this->readNextNonEmptyRow($file);
                    $this->processSummaryRow($row, $summaryValues ?? []);
                    $this->summaryFound = true;
                    break;
                }

                if ($this->isMetadataRow($row)) {
                    continue;
                }

                $formattedRow = $this->formatRow($row, $headerMap);
                if (empty($formattedRow)) {
                    continue;
                }

                $this->persistRow($formattedRow);
                $this->accumulateTotals($formattedRow);
            }

            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();
            Log::error('微信账单导入失败', [
                'payment_channel_id' => $this->paymentChannelId,
                'file_path' => $this->relativePath,
                'exception' => $throwable,
            ]);

            throw new RuntimeException($throwable->getMessage(), 0, $throwable);
        }

        if (! $this->summaryFound) {
            $this->fillSummaryFromImportTotals();
        }

        return [
            'import_totals' => $this->importTotals,
            'summary' => $this->summaryStats,
        ];
    }

    /**
     * 重置导入统计。
     */
    private function resetImportTotals(): void
    {
        $this->importTotals = [
            'import_total_transaction_count' => 0,
            'import_total_new_count' => 0,
            'import_total_updated_count' => 0,
            'import_total_settlement_amount' => '0.00',
            'import_total_refund_amount' => '0.00',
            'import_total_refund_voucher_amount' => '0.00',
            'import_total_fee_amount' => '0.00',
            'import_total_order_amount' => '0.00',
            'import_total_refund_apply_amount' => '0.00',
        ];
    }

    /**
     * 重置账单汇总统计。
     */
    private function resetSummaryStats(): void
    {
        $this->summaryStats = [
            'bill_summary_transaction_count' => 0,
            'bill_summary_settlement_amount' => '0.00',
            'bill_summary_refund_amount' => '0.00',
            'bill_summary_refund_voucher_amount' => '0.00',
            'bill_summary_fee_amount' => '0.00',
            'bill_summary_order_amount' => '0.00',
            'bill_summary_refund_apply_amount' => '0.00',
        ];
        $this->summaryFound = false;
    }

    /**
     * 拆分后的日账单没有汇总行时，使用明细导入合计作为账单汇总。
     */
    private function fillSummaryFromImportTotals(): void
    {
        $this->summaryStats = [
            'bill_summary_transaction_count' => $this->importTotals['import_total_transaction_count'],
            'bill_summary_settlement_amount' => $this->importTotals['import_total_settlement_amount'],
            'bill_summary_refund_amount' => $this->importTotals['import_total_refund_amount'],
            'bill_summary_refund_voucher_amount' => $this->importTotals['import_total_refund_voucher_amount'],
            'bill_summary_fee_amount' => $this->importTotals['import_total_fee_amount'],
            'bill_summary_order_amount' => $this->importTotals['import_total_order_amount'],
            'bill_summary_refund_apply_amount' => $this->importTotals['import_total_refund_apply_amount'],
        ];
    }

    /**
     * 将格式化后的数据写入数据库。
     *
     * @param  array<string, mixed>  $formattedRow
     */
    private function persistRow(array $formattedRow): void
    {
        $unique = [
            'payment_channel_id' => $formattedRow['payment_channel_id'],
            'trade_time' => $formattedRow['trade_time'],
            'out_trade_no' => $formattedRow['out_trade_no'],
        ];

        if (!empty($formattedRow['wechat_transaction_id'])) {
            $unique['wechat_transaction_id'] = $formattedRow['wechat_transaction_id'];
        }

        /** @var WechatBill $bill */
        $bill = WechatBill::updateOrCreate($unique, $formattedRow);

        if ($bill->wasRecentlyCreated) {
            $this->importTotals['import_total_new_count']++;
        } else {
            $this->importTotals['import_total_updated_count']++;
        }
    }

    /**
     * 累加导入统计数据。
     *
     * @param  array<string, mixed>  $formattedRow
     */
    private function accumulateTotals(array $formattedRow): void
    {
        $this->importTotals['import_total_transaction_count']++;

        $this->importTotals['import_total_settlement_amount'] = $this->bcAdd(
            $this->importTotals['import_total_settlement_amount'],
            $formattedRow['settlement_amount'] ?? '0.00'
        );

        $this->importTotals['import_total_refund_amount'] = $this->bcAdd(
            $this->importTotals['import_total_refund_amount'],
            $formattedRow['refund_amount'] ?? '0.00'
        );

        $this->importTotals['import_total_refund_voucher_amount'] = $this->bcAdd(
            $this->importTotals['import_total_refund_voucher_amount'],
            $formattedRow['refund_voucher_amount'] ?? '0.00'
        );

        $this->importTotals['import_total_fee_amount'] = $this->bcAdd(
            $this->importTotals['import_total_fee_amount'],
            $formattedRow['fee_amount'] ?? '0.00'
        );

        $this->importTotals['import_total_order_amount'] = $this->bcAdd(
            $this->importTotals['import_total_order_amount'],
            $formattedRow['total_amount'] ?? '0.00'
        );

        $this->importTotals['import_total_refund_apply_amount'] = $this->bcAdd(
            $this->importTotals['import_total_refund_apply_amount'],
            $formattedRow['refund_apply_amount'] ?? '0.00'
        );
    }

    /**
     * 处理汇总行。
     *
     * @param  array<int, string|null>  $labels
     * @param  array<int, string|null>  $values
     */
    private function processSummaryRow(array $labels, array $values): void
    {
        foreach ($labels as $index => $label) {
            $label = $this->normalizeValue($label);
            if ($label === null || $label === '') {
                continue;
            }

            $handler = self::SUMMARY_HANDLERS[$label] ?? null;
            if ($handler === null) {
                continue;
            }

            $value = $values[$index] ?? null;
            $this->{$handler}($value);
        }
    }

    private function handleSummaryInteger(?string $value): void
    {
        $this->summaryStats['bill_summary_transaction_count'] = (int) ($value ?? 0);
    }

    private function handleSummarySettlement(?string $value): void
    {
        $this->summaryStats['bill_summary_settlement_amount'] = $this->formatAmount($value);
    }

    private function handleSummaryRefund(?string $value): void
    {
        $this->summaryStats['bill_summary_refund_amount'] = $this->formatAmount($value);
    }

    private function handleSummaryRefundVoucher(?string $value): void
    {
        $this->summaryStats['bill_summary_refund_voucher_amount'] = $this->formatAmount($value);
    }

    private function handleSummaryFee(?string $value): void
    {
        $this->summaryStats['bill_summary_fee_amount'] = $this->formatAmount($value);
    }

    private function handleSummaryOrder(?string $value): void
    {
        $this->summaryStats['bill_summary_order_amount'] = $this->formatAmount($value);
    }

    private function handleSummaryRefundApply(?string $value): void
    {
        $this->summaryStats['bill_summary_refund_apply_amount'] = $this->formatAmount($value);
    }

    /**
     * 构建表头索引映射。
     *
     * @param  array<int, string|null>  $headers
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $header = $this->normalizeValue($header);
            if ($header === null) {
                continue;
            }

            $normalized = $this->normalizeHeader($header);
            if ($normalized === '') {
                continue;
            }

            $map[$normalized] = $index;
        }

        return $map;
    }

    /**
     * 格式化数据行。
     *
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $headerMap
     * @return array<string, mixed>
     */
    private function formatRow(array $row, array $headerMap): array
    {
        $data = [
            'payment_channel_id' => $this->paymentChannelId,
        ];

        foreach (self::HEADER_MAP as $header => $attribute) {
            $normalizedHeader = $this->normalizeHeader($header);
            $index = $headerMap[$normalizedHeader] ?? null;
            $value = $index !== null ? ($row[$index] ?? null) : null;

            if (in_array($attribute, ['settlement_amount', 'total_amount', 'voucher_amount', 'refund_amount', 'refund_voucher_amount', 'fee_amount', 'refund_apply_amount'], true)) {
                $data[$attribute] = $this->formatAmount($value);
                continue;
            }

            if ($attribute === 'trade_time') {
                $data[$attribute] = $this->formatTradeTime($value);
                continue;
            }

            if ($attribute === 'goods_info') {
                $data[$attribute] = $value !== null ? (string) $value : null;
                continue;
            }

            $data[$attribute] = $value !== null ? (string) $value : null;
        }

        $outTradeNo = $data['out_trade_no'] ?? null;
        $tradeTime = $data['trade_time'] ?? null;

        if (empty($outTradeNo) || empty($tradeTime)) {
            return [];
        }

        // 微信账单没有直接提供是否关联订单的字段，这里统一设置为 null，后续流程可更新。
        $data['is_order_associated'] = null;

        return $data;
    }

    /**
     * 判断是否为汇总标签行。
     *
     * @param  array<int, string|null>  $row
     */
    private function isSummaryLabelRow(array $row): bool
    {
        $first = $this->normalizeValue($row[0] ?? null);
        if ($first === null) {
            return false;
        }

        return str_contains($first, '总交易单数');
    }

    /**
     * 判断是否为描述/说明类的元数据行。
     *
     * @param  array<int, string|null>  $row
     */
    private function isMetadataRow(array $row): bool
    {
        $first = $this->normalizeValue($row[0] ?? null);
        if ($first === null) {
            return true;
        }

        $keywords = [
            '微信支付账单明细列表',
            '微信支付账单',
            '说明',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($first, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 读取下一个非空行。
     */
    private function readNextNonEmptyRow(SplFileObject $file): ?array
    {
        while (!$file->eof()) {
            $row = $file->fgetcsv();
            if ($row === false || $this->isEmptyRow($row)) {
                continue;
            }

            return $this->normalizeRow($row);
        }

        return null;
    }

    /**
     * 判断行是否为空。
     *
     * @param  array<int, mixed>|false|null  $row
     */
    private function isEmptyRow($row): bool
    {
        if (!is_array($row)) {
            return true;
        }

        foreach ($row as $value) {
            if ($this->normalizeValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * 对行中的每个值做编码与空白处理。
     *
     * @param  array<int, string|null>  $row
     * @return array<int, string|null>
     */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $index => $value) {
            $row[$index] = $this->normalizeValue($value);
        }

        return $row;
    }

    /**
     * 归一化单个单元格的值。
     *
     * @param  string|null  $value
     */
    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = ltrim($value, "\xEF\xBB\xBF");
        $value = trim($value);
        $value = trim($value, "`'\"");
        if ($value === '') {
            return null;
        }

        $detected = mb_detect_encoding($value, ['UTF-8', 'GB18030', 'GBK', 'GB2312'], true);
        if ($detected && $detected !== 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8', $detected);
        }

        return trim($value);
    }

    /**
     * 将金额格式化为两位小数。
     */
    private function formatAmount(?string $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $cleaned = str_replace(['¥', ',', ' '], '', $value);
        if ($cleaned === '') {
            return '0.00';
        }

        return number_format((float) $cleaned, 2, '.', '');
    }

    /**
     * 格式化交易时间。
     */
    private function formatTradeTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $throwable) {
            Log::warning('无法解析交易时间', [
                'value' => $value,
                'exception' => $throwable,
            ]);

            return null;
        }
    }

/**
     * 将标题统一为无空白、无单位形式。
     */
    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/（[^）]*）/u', '', $header) ?? $header;
        $header = str_replace([' ', "\t"], '', $header);

        return mb_strtolower($header, 'UTF-8');
    }

    /**
     * 解析 CSV 文件的实际绝对路径。
     */
    private function resolveAbsolutePath(): string
    {
        $disk = Storage::disk($this->storageDisk);

        if (method_exists($disk, 'path')) {
            $absolute = $disk->path($this->relativePath);
            if (is_file($absolute)) {
                return $absolute;
            }
        }

        $fallback = storage_path('app/'.$this->relativePath);
        if (!is_file($fallback)) {
            throw new RuntimeException('账单文件不存在：'.$this->relativePath);
        }

        return $fallback;
    }

    /**
     * 执行高精度加法。
     */
    private function bcAdd(string $left, string $right): string
    {
        return bcadd($left ?? '0.00', $right ?? '0.00', 2);
    }
}
