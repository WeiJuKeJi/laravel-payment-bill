<?php

namespace WeiJuKeJi\PaymentBill\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;
use SplFileObject;
use Throwable;

/**
 * 将微信月账单 CSV 按交易时间拆分为日账单 CSV。
 */
class WechatBillMonthlySplitter
{
    /**
     * @return array<array{path: string, filename: string, date: Carbon, size: int, source_filename: string, temporary: bool}>
     */
    public function split(string $sourcePath, string $sourceFilename): array
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new InvalidArgumentException("账单文件不存在或无法读取：{$sourceFilename}");
        }

        $file = new SplFileObject($sourcePath, 'rb');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $header = null;
        $tradeTimeIndex = null;
        $writers = [];
        $dailyFiles = [];

        try {
            while (! $file->eof()) {
                $row = $file->fgetcsv();
                if ($row === false || $this->isEmptyRow($row)) {
                    continue;
                }

                $row = $this->normalizeRow($row);

                if ($header === null) {
                    if (! $this->isHeaderRow($row)) {
                        continue;
                    }

                    $header = $row;
                    $tradeTimeIndex = $this->findTradeTimeIndex($row);
                    continue;
                }

                if ($this->isSummaryRow($row)) {
                    break;
                }

                $tradeTime = $this->parseTradeTime($row[$tradeTimeIndex] ?? null);
                if (! $tradeTime) {
                    continue;
                }

                $dateKey = $tradeTime->format('Y-m-d');
                if (! isset($writers[$dateKey])) {
                    $dailyFiles[$dateKey] = $this->makeDailyFile($dateKey, $sourceFilename);
                    $writers[$dateKey] = new SplFileObject($dailyFiles[$dateKey]['path'], 'wb');
                    $writers[$dateKey]->fputcsv($header);
                }

                $writers[$dateKey]->fputcsv($row);
            }
        } finally {
            $writers = [];
        }

        if (empty($dailyFiles)) {
            throw new InvalidArgumentException("未能从月账单中拆分出有效日账单：{$sourceFilename}");
        }

        ksort($dailyFiles);

        foreach ($dailyFiles as $dateKey => $dailyFile) {
            $dailyFiles[$dateKey]['size'] = filesize($dailyFile['path']) ?: 0;
        }

        return array_values($dailyFiles);
    }

    /**
     * @param  array<int, mixed>|false|null  $row
     */
    private function isEmptyRow($row): bool
    {
        if (! is_array($row)) {
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
     * @param  array<int, string|null>  $row
     */
    private function isHeaderRow(array $row): bool
    {
        return $this->findTradeTimeIndex($row) !== null
            && $this->containsHeader($row, '商户订单号');
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function findTradeTimeIndex(array $row): ?int
    {
        foreach ($row as $index => $value) {
            if ($this->normalizeHeader((string) $value) === $this->normalizeHeader('交易时间')) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function containsHeader(array $row, string $header): bool
    {
        $expected = $this->normalizeHeader($header);
        foreach ($row as $value) {
            if ($this->normalizeHeader((string) $value) === $expected) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/（[^）]*）/u', '', $header) ?? $header;
        $header = str_replace([' ', "\t"], '', $header);

        return mb_strtolower($header, 'UTF-8');
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isSummaryRow(array $row): bool
    {
        $first = $this->normalizeValue($row[0] ?? null);

        return $first !== null && str_contains($first, '总交易单数');
    }

    private function parseTradeTime(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{path: string, filename: string, date: Carbon, size: int, source_filename: string, temporary: bool}
     */
    private function makeDailyFile(string $dateKey, string $sourceFilename): array
    {
        $date = Carbon::createFromFormat('Y-m-d', $dateKey);
        $path = tempnam(sys_get_temp_dir(), 'wechat_bill_'.$date->format('Ymd').'_');

        if ($path === false) {
            throw new RuntimeException('创建月账单拆分临时文件失败');
        }

        return [
            'path' => $path,
            'filename' => $date->format('Ymd').'.csv',
            'date' => $date,
            'size' => 0,
            'source_filename' => $sourceFilename,
            'temporary' => true,
        ];
    }
}
