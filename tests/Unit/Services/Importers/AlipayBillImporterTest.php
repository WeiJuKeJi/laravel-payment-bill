<?php

namespace WeiJuKeJi\PaymentBill\Tests\Unit\Services\Importers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use WeiJuKeJi\PaymentBill\Services\Importers\AlipayBillImporter;

class AlipayBillImporterTest extends TestCase
{
    #[DataProvider('feeCases')]
    public function test_service_fee_is_normalized_by_business_type(string $bizType, string $rawFee, string $expected): void
    {
        $importer = $this->importerWithoutConstructor();
        $method = new ReflectionMethod($importer, 'normalizeServiceFee');

        self::assertSame($expected, $method->invoke($importer, $bizType, $rawFee));
    }

    public function test_summary_fee_uses_normalized_detail_total(): void
    {
        $importer = $this->importerWithoutConstructor();

        $summaryStats = new ReflectionProperty($importer, 'summaryStats');
        $summaryStats->setValue($importer, [
            'bill_summary_refund_amount' => '0.00',
            'bill_summary_order_amount' => '0.00',
            'bill_summary_fee_amount' => '-35.11',
        ]);

        $importTotals = new ReflectionProperty($importer, 'importTotals');
        $importTotals->setValue($importer, [
            'import_total_order_amount' => '9017.90',
            'import_total_refund_amount' => '3170.00',
            'import_total_fee_amount' => '35.11',
        ]);

        (new ReflectionMethod($importer, 'normalizeSummaryTotalsFromDetails'))->invoke($importer);

        self::assertSame([
            'bill_summary_refund_amount' => '3170.00',
            'bill_summary_order_amount' => '9017.90',
            'bill_summary_fee_amount' => '35.11',
        ], $summaryStats->getValue($importer));
    }

    public static function feeCases(): array
    {
        return [
            'legacy payment negative' => ['交易', '-0.41', '0.41'],
            'current payment positive' => ['交易', '0.41', '0.41'],
            'legacy refund positive' => ['退款', '0.96', '-0.96'],
            'current refund negative' => ['退款', '-0.96', '-0.96'],
            'payment zero' => ['交易', '-0.00', '0.00'],
            'refund zero' => ['退款', '0.00', '0.00'],
        ];
    }

    private function importerWithoutConstructor(): AlipayBillImporter
    {
        return (new ReflectionClass(AlipayBillImporter::class))->newInstanceWithoutConstructor();
    }
}
