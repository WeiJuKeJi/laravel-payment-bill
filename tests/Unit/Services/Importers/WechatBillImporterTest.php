<?php

namespace WeiJuKeJi\PaymentBill\Tests\Unit\Services\Importers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WeiJuKeJi\PaymentBill\Services\Importers\WechatBillImporter;

class WechatBillImporterTest extends TestCase
{
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
