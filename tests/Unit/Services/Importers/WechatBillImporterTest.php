<?php

namespace WeiJuKeJi\PaymentBill\Tests\Unit\Services\Importers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplFileObject;
use WeiJuKeJi\PaymentBill\Services\Importers\WechatBillImporter;

class WechatBillImporterTest extends TestCase
{
    public function test_csv_reader_preserves_amount_columns_when_goods_name_ends_with_backslash(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wechat_bill_daily_');
        self::assertNotFalse($path);

        $headers = [
            '交易时间', '公众账号ID', '商户号', '特约商户号', '设备号', '微信订单号', '商户订单号',
            '用户标识', '交易类型', '交易状态', '付款银行', '货币种类', '应结订单金额', '代金券金额',
            '微信退款单号', '商户退款单号', '退款金额', '充值券退款金额', '退款类型', '退款状态',
            '商品名称', '商户数据包', '手续费', '费率', '订单金额', '申请退款金额', '费率备注',
        ];
        $row = [
            '2024-05-08 19:35:12', 'wx-app-id', '1609502409', '1610072814', null,
            '4200059276202405087900654345', 'DR_2024050880901220', 'openid', 'MICROPAY', 'SUCCESS',
            'CCB_DEBIT', 'CNY', '54.00', '0.00', '0', '0', '0.00', '0.00', null, null,
            '如梦集市收银<上上签.烧烤>菜类[10份]\\', null, '0.32000', '0.60%', '54.00', '0.00', null,
        ];

        $writer = new SplFileObject($path, 'wb');
        $writer->fputcsv($headers, ',', '"', '');
        $writer->fputcsv($row, ',', '"', '');
        unset($writer);

        try {
            $importer = new WechatBillImporter(1, 'local', 'unused.csv');
            $method = new ReflectionMethod($importer, 'openCsvFile');
            /** @var SplFileObject $reader */
            $reader = $method->invoke($importer, $path);
            $reader->fgetcsv();
            $parsedRow = $reader->fgetcsv();

            self::assertCount(count($headers), $parsedRow);
            self::assertSame('4200059276202405087900654345', $parsedRow[5]);
            self::assertSame('如梦集市收银<上上签.烧烤>菜类[10份]\\', $parsedRow[20]);
            self::assertSame('0.00', $parsedRow[25]);
        } finally {
            @unlink($path);
        }
    }

    public function test_refunds_for_same_payment_and_second_use_different_identities(): void
    {
        $importer = new WechatBillImporter(1, 'local', 'unused.csv');

        $firstRefund = $this->resolveUniqueAttributes($importer, [
            'payment_channel_id' => 1,
            'trade_time' => '2026-04-04 17:37:25',
            'wechat_transaction_id' => '4200003121202604044509504428',
            'out_trade_no' => '2026040457804449792',
            'wechat_refund_no' => '50302806822026040416696838390',
            'out_refund_no' => '20260404018654553',
            'refund_amount' => '40.00',
        ]);

        $secondRefund = $this->resolveUniqueAttributes($importer, [
            'payment_channel_id' => 1,
            'trade_time' => '2026-04-04 17:37:25',
            'wechat_transaction_id' => '4200003121202604044509504428',
            'out_trade_no' => '2026040457804449792',
            'wechat_refund_no' => '50302806822026040490502072023',
            'out_refund_no' => '20260404098880838',
            'refund_amount' => '60.00',
        ]);

        self::assertSame([
            'payment_channel_id' => 1,
            'wechat_refund_no' => '50302806822026040416696838390',
        ], $firstRefund);
        self::assertSame([
            'payment_channel_id' => 1,
            'wechat_refund_no' => '50302806822026040490502072023',
        ], $secondRefund);
        self::assertNotSame($firstRefund, $secondRefund);
    }

    public function test_refund_identity_falls_back_to_merchant_refund_number(): void
    {
        $importer = new WechatBillImporter(1, 'local', 'unused.csv');

        $unique = $this->resolveUniqueAttributes($importer, [
            'payment_channel_id' => 1,
            'trade_time' => '2026-04-04 17:37:25',
            'wechat_transaction_id' => '4200003121202604044509504428',
            'out_trade_no' => '2026040457804449792',
            'wechat_refund_no' => '0',
            'out_refund_no' => '20260404018654553',
            'refund_amount' => '40.00',
        ]);

        self::assertSame([
            'payment_channel_id' => 1,
            'out_refund_no' => '20260404018654553',
        ], $unique);
    }

    public function test_payment_identity_keeps_existing_transaction_fields(): void
    {
        $importer = new WechatBillImporter(1, 'local', 'unused.csv');

        $unique = $this->resolveUniqueAttributes($importer, [
            'payment_channel_id' => 1,
            'trade_time' => '2026-04-04 16:00:00',
            'wechat_transaction_id' => '4200003121202604044509504428',
            'out_trade_no' => '2026040457804449792',
            'wechat_refund_no' => '',
            'out_refund_no' => '',
            'refund_amount' => '0.00',
        ]);

        self::assertSame([
            'payment_channel_id' => 1,
            'trade_time' => '2026-04-04 16:00:00',
            'out_trade_no' => '2026040457804449792',
            'wechat_transaction_id' => '4200003121202604044509504428',
        ], $unique);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function resolveUniqueAttributes(WechatBillImporter $importer, array $row): array
    {
        $method = new ReflectionMethod($importer, 'resolveUniqueAttributes');

        return $method->invoke($importer, $row);
    }
}
