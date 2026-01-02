<?php

namespace WeiJuKeJi\PaymentBill\Services\Downloaders;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
use Yansongda\Artful\Rocket;
use Yansongda\Pay\Pay;
use Yansongda\Pay\Plugin\Alipay\V2\Pay\Web\QueryBillUrlPlugin;
use Yansongda\Supports\Collection;

/**
 * 支付宝账单下载适配器。
 */
class AlipayBillDownloader
{
    /**
     * 返回当前下载器支持的渠道标识。
     */
    public function channel(): string
    {
        return 'alipay';
    }

    /**
     * 执行支付宝账单下载主流程：初始化支付实例、获取下载链接并拉取账单文件。
     *
     * @param  PaymentChannel  $channel  支付渠道实体，包含证书等配置
     * @param  array<string, mixed>  $config  yansongda/pay 所需配置
     * @param  array<string, mixed>  $params  下载参数（日期、账单类型等）
     * @return array{response: ResponseInterface, filename: string|null}
     */
    public function download(PaymentChannel $channel, array $config, array $params): array
    {
        $provider = $this->configureProvider($config);
        $requestParams = $this->buildRequestParams($params);
        $downloadUrl = $this->fetchDownloadUrl($provider, $requestParams);
        $response = $this->fetchBillResponse($downloadUrl, $config);

        return [
            'response' => $response,
            'filename' => $this->extractFilename($response),
        ];
    }

    /**
     * 初始化 yansongda/pay 支付宝实例。
     *
     * @param  array<string, mixed>  $config
     * @return mixed
     */
    protected function configureProvider(array &$config)
    {
        $config['_force'] = true;

        Pay::config($config);

        return Pay::alipay();
    }

    /**
     * 构建支付宝账单接口所需的请求参数。
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function buildRequestParams(array $params): array
    {
        return array_filter([
            'bill_type' => Arr::get($params, 'bill_type', 'trade'),
            'bill_date' => Arr::get($params, 'bill_date'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * 申请账单下载链接。
     *
     * @param  mixed  $provider
     * @param  array<string, mixed>  $requestParams
     */
    protected function fetchDownloadUrl($provider, array $requestParams): string
    {
        $plugins = $provider->mergeCommonPlugins([
            QueryBillUrlPlugin::class,
        ]);

        try {
            $collection = $this->toCollection(
                $provider->pay($plugins, $requestParams)
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                '支付宝账单下载链接申请失败：'.$exception->getMessage(),
                0,
                $exception
            );
        }

        $downloadUrl = $collection->get('bill_download_url');
        if (!$downloadUrl) {
            throw new InvalidArgumentException('支付宝返回结果缺少 bill_download_url，无法继续下载账单');
        }

        return $downloadUrl;
    }

    /**
     * 拉取账单文件响应。
     *
     * @param  array<string, mixed>  $config
     */
    protected function fetchBillResponse(string $downloadUrl, array $config): ResponseInterface
    {
        $httpConfig = Arr::get($config, 'http', []);

        $client = new GuzzleClient([
            'timeout' => Arr::get($httpConfig, 'timeout', 60.0),
            'connect_timeout' => Arr::get($httpConfig, 'connect_timeout', 10.0),
            'verify' => (bool) Arr::get($httpConfig, 'verify', true),
        ]);

        try {
            $response = $client->request('GET', $downloadUrl, [
                'http_errors' => false,
                'headers' => [
                    'Accept' => 'application/octet-stream',
                    'User-Agent' => 'FinanceBillDownloader/1.0',
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException(
                $this->formatHttpRequestException($exception),
                0,
                $exception
            );
        }

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException($this->formatHttpErrorResponse($response));
        }

        return $response;
    }

    /**
     * 解析下载接口返回的数据结构。
     *
     * @param  Collection|Rocket|null  $result
     */
    protected function toCollection(mixed $result): Collection
    {
        if ($result instanceof Collection) {
            return $result;
        }

        if ($result instanceof Rocket && $result->getDestination() instanceof Collection) {
            return $result->getDestination();
        }

        throw new InvalidArgumentException('无法解析支付宝账单申请响应');
    }

    /**
     * 解析 Content-Disposition 头中的文件名。
     */
    protected function extractFilename(ResponseInterface $response): ?string
    {
        $disposition = $response->getHeaderLine('Content-Disposition');
        if (!$disposition) {
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
     * 将 Guzzle 异常格式化为可阅读描述。
     */
    protected function formatHttpRequestException(GuzzleException $exception): string
    {
        return sprintf('请求第三方 API 出错：%s', $exception->getMessage());
    }

    /**
     * 格式化非 2xx 响应体，按需解析 JSON 返回的错误码。
     */
    protected function formatHttpErrorResponse(ResponseInterface $response): string
    {
        $status = $response->getStatusCode();
        $body = trim((string) $response->getBody());

        if ($body === '') {
            return sprintf('支付宝账单下载失败，HTTP 状态码 %d', $status);
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $message = $decoded['sub_msg'] ?? $decoded['msg'] ?? $decoded['error_description'] ?? '';
            $code = $decoded['sub_code'] ?? $decoded['code'] ?? '';

            return sprintf(
                '支付宝账单下载失败，HTTP %d，错误码 %s，信息：%s',
                $status,
                $code ?: '未知',
                $message ?: '未提供'
            );
        }

        return sprintf(
            '支付宝账单下载失败，HTTP %d，响应体：%s',
            $status,
            $body
        );
    }
}
