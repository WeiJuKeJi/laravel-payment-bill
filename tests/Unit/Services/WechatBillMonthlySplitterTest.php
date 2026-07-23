<?php

namespace WeiJuKeJi\PaymentBill\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SplFileObject;
use WeiJuKeJi\PaymentBill\Services\WechatBillMonthlySplitter;

class WechatBillMonthlySplitterTest extends TestCase
{
    public function test_split_file_preserves_columns_when_goods_name_ends_with_backslash(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'wechat_bill_source_');
        self::assertNotFalse($sourcePath);

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

        $source = new SplFileObject($sourcePath, 'wb');
        $source->fputcsv($headers, ',', '"', '');
        $source->fputcsv($row, ',', '"', '');
        unset($source);

        $dailyFiles = (new WechatBillMonthlySplitter())->split($sourcePath, 'wechat-monthly.csv');

        try {
            self::assertCount(1, $dailyFiles);

            $daily = new SplFileObject($dailyFiles[0]['path'], 'rb');
            $daily->setCsvControl(',', '"', '');
            $dailyHeaders = $daily->fgetcsv();
            $dailyRow = $daily->fgetcsv();

            self::assertCount(count($headers), $dailyHeaders);
            self::assertCount(count($headers), $dailyRow);
            self::assertSame('4200059276202405087900654345', $dailyRow[5]);
            self::assertSame('如梦集市收银<上上签.烧烤>菜类[10份]\\', $dailyRow[20]);
            self::assertSame('0.00', $dailyRow[25]);
        } finally {
            @unlink($sourcePath);
            foreach ($dailyFiles as $dailyFile) {
                @unlink($dailyFile['path']);
            }
        }
    }
}
