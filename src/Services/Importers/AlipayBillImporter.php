<?php

namespace WeiJuKeJi\PaymentBill\Services\Importers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use WeiJuKeJi\PaymentBill\Models\AlipayBill;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use RuntimeException;
use SplFileObject;
use ZipArchive;

/**
 * 支付宝账单导入器：负责解压、解析 CSV 并写入数据库。
 */
class AlipayBillImporter
{
    private const DETAIL_KEYWORDS = '业务明细';
    private const SUMMARY_KEYWORDS = '业务汇总';

    /**
     * @var array<string, string>
     */
    private const DETAIL_MAP = [
        '支付宝交易号' => 'alipay_trade_no',
        '商户订单号' => 'merchant_order_no',
        '业务类型' => 'biz_type',
        '商品名称' => 'goods_name',
        '创建时间' => 'created_time',
        '完成时间' => 'completed_time',
        '门店编号' => 'store_id',
        '门店名称' => 'store_name',
        '操作员' => 'operator',
        '终端号' => 'terminal_id',
        '对方账户' => 'counterparty_account',
        '订单金额（元）' => 'order_amount',
        '商家实收（元）' => 'merchant_settlement_amount',
        '支付宝红包（元）' => 'alipay_red_envelope_amount',
        '集分宝抵扣（元）' => 'point_deduction_amount',
        '支付宝优惠' => 'alipay_discount_amount',
        '商家优惠（元）' => 'merchant_discount_amount',
        '券核销金额（元）' => 'coupon_amount',
        '券名称' => 'coupon_name',
        '商家红包消费金额（元）' => 'merchant_red_envelope_amount',
        '卡消费金额（元）' => 'card_amount',
        '退款批次号/请求号' => 'refund_request_no',
        '服务费（元）' => 'service_fee_amount',
        '分润（元）' => 'profit_sharing_amount',
        '备注' => 'remark',
    ];

    private BillDownload $billDownload;

    private string $storageDisk;

    private string $extractedDirectory;

    /**
     * 导入统计。
     *
     * @var array<string, int|string>
     */
    private array $importTotals = [];

    /**
     * 汇总统计。
     *
     * @var array<string, int|string>
     */
    private array $summaryStats = [];

    public function __construct(BillDownload $billDownload, string $storageDisk)
    {
        $this->billDownload = $billDownload;
        $this->storageDisk = $storageDisk;

        $this->resetImportTotals();
        $this->resetSummaryStats();
    }

    /**
     * 执行导入过程。
     *
     * @return array{import_totals: array<string, int|string>, summary: array<string, int|string>}
     */
    public function import(): array
    {
        $this->extractArchive();

        $detailFile = $this->locateCsv(static::DETAIL_KEYWORDS, ['支付宝交易号', '业务明细列表']);
        $summaryFile = $this->locateCsv(static::SUMMARY_KEYWORDS, ['业务汇总列表', '交易订单总笔数']);

        if ($detailFile === null) {
            throw new RuntimeException('未找到支付宝业务明细 CSV 文件');
        }

        DB::beginTransaction();

        try {
            $this->importDetails($detailFile);
            $this->parseDetailTrailerForRefund($detailFile);

            if ($summaryFile !== null) {
                $this->importSummary($summaryFile);
            }

            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();

            Log::error('支付宝账单导入失败', [
                'bill_download_id' => $this->billDownload->getKey(),
                'file_path' => $this->billDownload->file_path,
                'exception' => $throwable,
            ]);

            throw new RuntimeException($throwable->getMessage(), 0, $throwable);
        }

        return [
            'import_totals' => $this->importTotals,
            'summary' => $this->summaryStats,
        ];
    }

    /**
     * 解压账单 zip。
     */
    private function extractArchive(): void
    {
        $disk = Storage::disk($this->storageDisk);

        if (! $disk->exists($this->billDownload->file_path)) {
            throw new RuntimeException('账单压缩包不存在：'.$this->billDownload->file_path);
        }

        $absoluteZip = $disk->path($this->billDownload->file_path);

        $directory = pathinfo($absoluteZip, PATHINFO_DIRNAME);
        $folderName = pathinfo($absoluteZip, PATHINFO_FILENAME);
        $targetPath = $directory.DIRECTORY_SEPARATOR.$folderName;

        if (!is_dir($targetPath)) {
            if (!mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
                throw new RuntimeException('无法创建解压目录：'.$targetPath);
            }

            $zip = new ZipArchive();
            if ($zip->open($absoluteZip) !== true) {
                throw new RuntimeException('解压支付宝账单失败：无法打开压缩包。');
            }

            if (!$zip->extractTo($targetPath)) {
                $zip->close();
                throw new RuntimeException('解压支付宝账单失败：无法写入文件。');
            }

            $zip->close();
        }

        $this->extractedDirectory = $targetPath;
    }

    /**
     * 定位 CSV 文件。
     */
    private function locateCsv(string $keyword, array $contentKeywords = []): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->extractedDirectory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            if (!str_ends_with(strtolower($filename), '.csv')) {
                continue;
            }

            $candidates = [$filename];

            foreach (['UTF-8', 'GB18030', 'GBK', 'CP936', 'CP437'] as $encoding) {
                $converted = @iconv($encoding, 'UTF-8//IGNORE', $filename);
                if ($converted && !in_array($converted, $candidates, true)) {
                    $candidates[] = $converted;
                }
            }

            foreach ($candidates as $candidate) {
                if ($candidate && str_contains($candidate, $keyword)) {
                    return $fileInfo->getPathname();
                }
            }

            if (!empty($contentKeywords) && $this->matchCsvContent($fileInfo->getPathname(), $contentKeywords)) {
                return $fileInfo->getPathname();
            }
        }

        return null;
    }

    private function matchCsvContent(string $path, array $keywords): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $lineCount = 0;
            while (($line = fgets($handle)) !== false && $lineCount < 10) {
                $lineCount++;
                $normalized = $this->normalizeValue($line);
                if ($normalized === null) {
                    continue;
                }

                foreach ($keywords as $keyword) {
                    if (str_contains($normalized, $keyword)) {
                        return true;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    /**
     * 导入明细数据。
     */
    private function importDetails(string $csvPath): void
    {
        $file = new SplFileObject($csvPath, 'rb');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '\\');

        $headers = null;
        $headerMap = [];

        while (!$file->eof()) {
            $row = $file->fgetcsv();
            if ($row === false || $this->isEmptyRow($row)) {
                continue;
            }

            $row = $this->normalizeRow($row);
            if ($row === []) {
                continue;
            }

            $first = $row[0];
            if ($first !== null && str_starts_with($first, '#')) {
                continue;
            }

            if ($headers === null) {
                if ($this->isHeaderRow($row)) {
                    $headers = $row;
                    $headerMap = $this->buildHeaderMap($headers);
                }

                continue;
            }

            $formatted = $this->formatDetailRow($row, $headerMap);
            if (empty($formatted)) {
                continue;
            }

            $this->persistDetail($formatted);
            $this->accumulateTotals($formatted);
        }
    }

    /**
     * 业务汇总 CSV 缺少退款金额列，从业务明细尾部注释行解析。
     * 形如：#退款合计：2笔，商家实收退款共-310.00元，商家优惠退款共0.00元
     */
    private function parseDetailTrailerForRefund(string $csvPath): void
    {
        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $decoded = $this->normalizeValue($line);
                if ($decoded === null || $decoded === '' || ! str_contains($decoded, '退款合计')) {
                    continue;
                }

                if (preg_match('/退款合计[^共]*共\s*(-?[\d,]+(?:\.\d+)?)\s*元/u', $decoded, $matches)) {
                    $amount = str_replace(',', '', $matches[1]);
                    $amount = ltrim($amount, '+');
                    $amount = ltrim($amount, '-');
                    $this->summaryStats['bill_summary_refund_amount'] = $this->formatAmount($amount);

                    return;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function importSummary(?string $csvPath): void
    {
        if ($csvPath === null) {
            return;
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('无法读取支付宝汇总文件：'.$csvPath);
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $decoded = $this->normalizeValue($line);
                if ($decoded === null || $decoded === '') {
                    continue;
                }

                if (str_starts_with($decoded, '#')) {
                    continue;
                }

                $normalizedLine = str_replace("\t", '', $decoded);
                $columns = str_getcsv($normalizedLine);

                if (empty($columns) || str_contains($columns[0], '门店编号')) {
                    continue;
                }

                $this->processSummaryColumns($columns);
            }
        } finally {
            fclose($handle);
        }
    }

    private function processSummaryColumns(array $columns): void
    {
        $columns = array_map(fn ($value) => $this->normalizeValue($value), $columns);

        if (count($columns) < 6) {
            return;
        }

        if ($columns[0] !== null && str_contains($columns[0], '合计')) {
            $transactionCount = $this->toInteger($columns[2] ?? null) + $this->toInteger($columns[3] ?? null);
            $orderAmount = $this->formatAmount($columns[4] ?? null);
            $settlementAmount = $this->formatAmount($columns[5] ?? null);
            $serviceFee = $this->formatAmount($columns[9] ?? null);

            $this->summaryStats['bill_summary_transaction_count'] = $transactionCount;
            $this->summaryStats['bill_summary_order_amount'] = $orderAmount;
            $this->summaryStats['bill_summary_settlement_amount'] = $settlementAmount;
            $this->summaryStats['bill_summary_fee_amount'] = $serviceFee;

            return;
        }

        // 若没有合计行，则累加每行
        $this->summaryStats['bill_summary_transaction_count'] += $this->toInteger($columns[2] ?? null) + $this->toInteger($columns[3] ?? null);
        $this->summaryStats['bill_summary_order_amount'] = $this->bcAdd(
            $this->summaryStats['bill_summary_order_amount'],
            $this->formatAmount($columns[4] ?? null)
        );
        $this->summaryStats['bill_summary_settlement_amount'] = $this->bcAdd(
            $this->summaryStats['bill_summary_settlement_amount'],
            $this->formatAmount($columns[5] ?? null)
        );
        $this->summaryStats['bill_summary_fee_amount'] = $this->bcAdd(
            $this->summaryStats['bill_summary_fee_amount'],
            $this->formatAmount($columns[9] ?? null)
        );
    }

    private function persistDetail(array $formatted): void
    {
        $unique = [
            'payment_channel_id' => $formatted['payment_channel_id'],
            'bill_date' => $formatted['bill_date'],
            'alipay_trade_no' => $formatted['alipay_trade_no'],
            'merchant_order_no' => $formatted['merchant_order_no'],
            'biz_type' => $formatted['biz_type'],
            'refund_request_no' => $formatted['refund_request_no'],
        ];

        /** @var AlipayBill $bill */
        $bill = AlipayBill::updateOrCreate($unique, $formatted);

        if ($bill->wasRecentlyCreated) {
            $this->importTotals['import_total_new_count']++;
        } else {
            $this->importTotals['import_total_updated_count']++;
        }
    }

    private function accumulateTotals(array $formatted): void
    {
        $this->importTotals['import_total_transaction_count']++;

        $orderAmount = $formatted['order_amount'] ?? '0.00';
        $settlementAmount = $formatted['merchant_settlement_amount'] ?? '0.00';
        $refundAmount = '0.00';

        $bizType = $formatted['biz_type'] ?? '';
        $orderAmountValue = $this->formatAmount($orderAmount);
        $settlementAmountValue = $this->formatAmount($settlementAmount);

        if (str_contains((string) $bizType, '退款')) {
            $refundAmount = $orderAmountValue;
            $refundAmount = ltrim($refundAmount, '+');
            $refundAmount = ltrim($refundAmount, '-');
        }

        $this->importTotals['import_total_order_amount'] = $this->bcAdd(
            $this->importTotals['import_total_order_amount'],
            $orderAmountValue
        );

        $this->importTotals['import_total_settlement_amount'] = $this->bcAdd(
            $this->importTotals['import_total_settlement_amount'],
            $settlementAmountValue
        );

        $this->importTotals['import_total_refund_amount'] = $this->bcAdd(
            $this->importTotals['import_total_refund_amount'],
            $refundAmount
        );

        $this->importTotals['import_total_fee_amount'] = $this->bcAdd(
            $this->importTotals['import_total_fee_amount'],
            $this->formatAmount($formatted['service_fee_amount'] ?? '0.00')
        );
    }

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
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $value) {
            $normalized[] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $value = str_replace(["\r", "\n"], '', $value);
        $value = trim($value);
        $value = trim($value, "`'\"");

        if ($value === '') {
            return null;
        }

        $detected = mb_detect_encoding($value, ['UTF-8', 'GB2312', 'GBK', 'GB18030'], true);
        if ($detected && $detected !== 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8', $detected);
        }

        return trim($value);
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $header = $this->normalizeValue($header);
            if ($header === null) {
                continue;
            }

            $map[$header] = $index;
        }

        return $map;
    }

    private function formatDetailRow(array $row, array $headerMap): array
    {
        $billDate = $this->billDownload->bill_date;
        $data = [
            'payment_channel_id' => $this->billDownload->payment_channel_id,
            'bill_date' => $billDate instanceof Carbon ? $billDate->toDateString() : (string) $billDate,
        ];

        foreach (self::DETAIL_MAP as $header => $attribute) {
            $index = $headerMap[$header] ?? null;
            $value = $index !== null ? ($row[$index] ?? null) : null;

            if (in_array($attribute, [
                'order_amount',
                'merchant_settlement_amount',
                'alipay_red_envelope_amount',
                'point_deduction_amount',
                'alipay_discount_amount',
                'merchant_discount_amount',
                'coupon_amount',
                'merchant_red_envelope_amount',
                'card_amount',
                'service_fee_amount',
                'profit_sharing_amount',
            ], true)) {
                $data[$attribute] = $this->formatAmount($value);
                continue;
            }

            if (in_array($attribute, ['created_time', 'completed_time'], true)) {
                $data[$attribute] = $this->formatDateTime($value);
                continue;
            }

            $data[$attribute] = $value !== null ? (string) $value : null;
        }

        if (empty($data['alipay_trade_no']) && empty($data['merchant_order_no'])) {
            return [];
        }

        return $data;
    }

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

    private function formatDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isHeaderRow(array $row): bool
    {
        $line = implode('', array_filter($row, fn ($item) => $item !== null));

        return str_contains($line, '支付宝交易号');
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function toInteger(?string $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) preg_replace('/[^0-9\-]/', '', $value);
    }

    private function bcAdd(string $left, string $right): string
    {
        return bcadd($left ?? '0.00', $right ?? '0.00', 2);
    }
}
