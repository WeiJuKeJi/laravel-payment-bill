<?php

namespace WeiJuKeJi\PaymentBill\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use WeiJuKeJi\PaymentBill\Services\BillDownloadService;

class BillDownloadServiceTest extends TestCase
{
    public function test_wechat_no_statement_response_is_not_treated_as_failure(): void
    {
        $service = (new ReflectionClass(BillDownloadService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($service, 'isWechatNoStatement');
        $channel = new PaymentChannel(['channel' => 'wechat']);

        $result = $method->invoke(
            $service,
            $channel,
            '微信返回状态码异常 - code: NO_STATEMENT_EXIST, message: 请求的账单文件不存在'
        );

        self::assertTrue($result);
    }

    public function test_non_wechat_no_statement_response_remains_a_failure(): void
    {
        $service = (new ReflectionClass(BillDownloadService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($service, 'isWechatNoStatement');
        $channel = new PaymentChannel(['channel' => 'alipay']);

        self::assertFalse($method->invoke($service, $channel, 'NO_STATEMENT_EXIST'));
    }
}
