<?php

namespace WeiJuKeJi\PaymentBill\Services\Downloaders;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Yansongda\Artful\Rocket;
use Yansongda\Pay\Pay;
use Yansongda\Pay\Plugin\Wechat\V3\Pay\Jsapi\GetTradeBillPlugin;
use Yansongda\Supports\Collection;
use Yansongda\Artful\Exception\InvalidResponseException;

/**
 * 微信账单下载适配器。
 */
class WechatBillDownloader
{
    /**
     * @var array<int, string>
     */
    private const SUMMARY_KEYS = ['code', 'message', 'reason'];

    /**
     * 返回当前下载器支持的渠道标识。
     */
    public function channel(): string
    {
        return 'wechat';
    }

    /**
     * 执行微信账单下载主流程：初始化支付实例、获取下载链接并拉取账单文件。
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
        $response = $this->fetchBillResponse($downloadUrl, $channel, $config);

        return [
            'response' => $this->toResponse($response),
            'filename' => $this->extractFilename($downloadUrl),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return mixed
     */
    protected function configureProvider(array &$config)
    {
        $config['_force'] = true;

        Pay::config($config);

        return Pay::wechat();
    }

    /**
     * 构建微信账单接口所需的请求参数（过滤空值，设置默认账单类型）。
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function buildRequestParams(array $params): array
    {
        return array_filter([
            'bill_date' => Arr::get($params, 'bill_date'),
            'bill_type' => Arr::get($params, 'bill_type', 'ALL'),
            'tar_type' => Arr::get($params, 'tar_type'),
            'sub_mchid' => Arr::get($params, 'sub_mchid'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  mixed  $provider
     * @param  array<string, mixed>  $requestParams
     */
    protected function fetchDownloadUrl($provider, array $requestParams): string
    {
        $plugins = $provider->mergeCommonPlugins([
            GetTradeBillPlugin::class,
        ]);

        try {
            $collection = $this->toCollection(
                $provider->pay($plugins, $requestParams)
            );
        } catch (InvalidResponseException $exception) {
            throw new RuntimeException($this->formatInvalidResponse($exception), 0, $exception);
        }

        $downloadUrl = $collection->get('download_url');
        if (!$downloadUrl) {
            throw new InvalidArgumentException('微信返回结果缺少 download_url，无法继续下载账单');
        }

        return $downloadUrl;
    }

    /**
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

        throw new InvalidArgumentException('无法解析微信账单申请响应');
    }

    /**
     * 将 yansongda/pay 返回的多态结果解析为 HTTP 响应对象，便于读取账单文件。
     *
     * @param  ResponseInterface|Rocket|null  $result
     */
    protected function toResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if ($result instanceof Rocket) {
            $destination = $result->getDestination();
            if ($destination instanceof ResponseInterface) {
                return $destination;
            }
        }

        throw new InvalidArgumentException('无法解析微信账单下载响应');
    }

    protected function fetchBillResponse(string $downloadUrl, PaymentChannel $channel, array $config): ResponseInterface
    {
        $authorization = $this->generateAuthorizationHeader('GET', $downloadUrl, $config);

        $httpConfig = Arr::get($config, 'http', []);

        $client = new GuzzleClient([
            'timeout' => Arr::get($httpConfig, 'timeout', 30.0),
            'connect_timeout' => Arr::get($httpConfig, 'connect_timeout', 10.0),
            'verify' => (bool) Arr::get($httpConfig, 'verify', true),
        ]);

        try {
            $response = $client->request('GET', $downloadUrl, [
                'http_errors' => false,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $authorization,
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
     * 根据微信 V3 鉴权规范生成下载请求所需的 Authorization 头。
     */
    protected function generateAuthorizationHeader(string $method, string $url, array $config): string
    {
        $wechat = Arr::get($config, 'wechat.default', []);

        $mchId = Arr::get($wechat, 'mch_id');
        $publicCertPath = Arr::get($wechat, 'mch_public_cert_path');
        $privateKeyPath = Arr::get($wechat, 'mch_secret_cert');

        if (!$mchId || !$publicCertPath || !$privateKeyPath) {
            throw new InvalidArgumentException('微信账单下载缺少必要配置');
        }

        if (!is_file($publicCertPath) || !is_readable($publicCertPath)) {
            throw new InvalidArgumentException('无法读取微信商户公钥证书：'.$publicCertPath);
        }

        if (!is_file($privateKeyPath) || !is_readable($privateKeyPath)) {
            throw new InvalidArgumentException('无法读取微信商户私钥：'.$privateKeyPath);
        }

        $certificate = openssl_x509_parse(file_get_contents($publicCertPath));
        $serialNo = Arr::get($certificate, 'serialNumberHex');
        if (empty($serialNo)) {
            throw new InvalidArgumentException('无法解析微信商户证书序列号');
        }

        $nonce = Str::random(32);
        $timestamp = time();

        $parsedUrl = parse_url($url);
        $canonicalUrl = ($parsedUrl['path'] ?? '').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');

        $message = sprintf(
            "%s\n%s\n%d\n%s\n\n",
            strtoupper($method),
            $canonicalUrl,
            $timestamp,
            $nonce
        );

        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));
        if (!$privateKey) {
            throw new InvalidArgumentException('加载微信商户私钥失败');
        }

        $signature = '';
        if (!openssl_sign($message, $signature, $privateKey, 'sha256WithRSAEncryption')) {
            openssl_free_key($privateKey);
            throw new InvalidArgumentException('生成微信账单下载签名失败');
        }

        openssl_free_key($privateKey);

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $mchId,
            $nonce,
            $timestamp,
            $serialNo,
            base64_encode($signature)
        );
    }

    /**
     * 从微信返回的 download_url 中提取建议文件名。
     */
    protected function extractFilename(string $downloadUrl): ?string
    {
        $path = parse_url($downloadUrl, PHP_URL_PATH) ?? '';
        $filename = basename($path);

        return $filename !== '' ? $filename : null;
    }

    /**
     * 统一格式化请求账单链接阶段抛出的异常信息。
     */
    protected function formatInvalidResponse(InvalidResponseException $exception): string
    {
        return $this->composeMessage(
            $exception->getMessage(),
            $this->summarizeResponse($exception->response ?? null)
        );
    }

    /**
     * 将下载接口的 HTTP 错误响应解析为易读的中文提示。
     */
    protected function formatHttpErrorResponse(ResponseInterface $response): string
    {
        $status = $response->getStatusCode();
        $reason = $response->getReasonPhrase();

        $summary = $this->summarizeResponseBody(trim($this->readResponseContents($response)));

        return $this->composeMessage(sprintf(
            '微信账单文件下载失败 | HTTP %d %s',
            $status,
            $reason
        ), $summary);
    }

    /**
     * 将 Guzzle 异常转译为统一的下载失败信息。
     */
    protected function formatHttpRequestException(GuzzleException $exception): string
    {
        if ($exception instanceof RequestException && $exception->getResponse() instanceof ResponseInterface) {
            return $this->formatHttpErrorResponse($exception->getResponse());
        }

        return '微信账单文件下载请求失败：'.$exception->getMessage();
    }

    /**
     * 尝试从响应 body 中提取 code/message/detail 等信息形成摘要。
     */
    protected function summarizeResponseBody(string $content): ?string
    {
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $parts = [];

            foreach (['code', 'message', 'detail', 'reason'] as $key) {
                if (!empty($decoded[$key])) {
                    $parts[] = "{$key}: {$decoded[$key]}";
                }
            }

            if (!empty($parts)) {
                return implode(', ', $parts);
            }
        }

        return Str::limit($content, 500);
    }

    /**
     * 读取响应体内容，自动处理可回绕流，避免影响后续读取。
     */
    protected function readResponseContents(ResponseInterface $response): string
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
            $contents = $body->getContents();
            $body->rewind();

            return $contents;
        }

        return $body->getContents();
    }

    /**
     * 针对多态的响应结构（Response/Rocket/数组等）提取统一的错误摘要。
     */
    protected function summarizeResponse(mixed $response): ?string
    {
        if ($response instanceof ResponseInterface) {
            $status = $response->getStatusCode();
            $reason = $response->getReasonPhrase();

            $content = trim($this->readResponseContents($response));
            $summary = $this->summarizeResponseBody($content);
            $prefix = sprintf('HTTP %d %s', $status, $reason);

            return $summary ? $prefix.' - '.$summary : $prefix;
        }

        if ($response instanceof Rocket) {
            $summary = $this->summarizeResponse($response->getDestination());
            if ($summary !== null) {
                return $summary;
            }

            return $this->summarizeResponse($response->getDestinationOrigin());
        }

        if ($response instanceof Collection) {
            return $this->summarizeResponseArray($response->toArray());
        }

        if (is_array($response)) {
            return $this->summarizeResponseArray($response);
        }

        if (is_string($response)) {
            $trimmed = trim($response);
            if ($trimmed === '') {
                return null;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $summary = $this->summarizeResponseArray($decoded);
                if ($summary !== null) {
                    return $summary;
                }
            }

            return Str::limit($trimmed, 500);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * 从数组或集合结构中提取 code/message/detail 等字段组成摘要。
     *
     * @param  array<string, mixed>  $data
     */
    protected function summarizeResponseArray(array $data): ?string
    {
        $parts = [];

        foreach (self::SUMMARY_KEYS as $key) {
            if (isset($data[$key]) && $this->isDisplayableScalar($data[$key])) {
                $parts[] = "{$key}: ".$this->stringifyScalar($data[$key]);
            }
        }

        if (isset($data['detail'])) {
            $detailSummary = $this->stringifyDetail($data['detail']);

            if ($detailSummary !== null) {
                $parts[] = 'detail: '.$detailSummary;
            }
        }

        if (isset($data['body'])) {
            $body = $data['body'];

            if (is_string($body)) {
                $decoded = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $nested = $this->summarizeResponseArray($decoded);
                    if ($nested !== null) {
                        $parts[] = $nested;
                    }
                } elseif ($body !== '') {
                    $parts[] = Str::limit(trim($body), 500);
                }
            } elseif (is_array($body)) {
                $nested = $this->summarizeResponseArray($body);
                if ($nested !== null) {
                    $parts[] = $nested;
                }
            }
        }

        if (empty($parts)) {
            return null;
        }

        return implode(', ', array_unique(array_filter($parts)));
    }

    /**
     * 将基础错误信息与解析出的摘要拼接成最终输出文案。
     */
    protected function composeMessage(string $baseMessage, ?string $summary): string
    {
        $base = trim($baseMessage);

        if ($summary === null || $summary === '') {
            return $base;
        }

        if ($base === '') {
            return $summary;
        }

        return $base.' - '.$summary;
    }

    /**
     * 递归地把 detail 字段（可能是数组/集合）转成形如 key:value 的短语。
     */
    protected function stringifyDetail(mixed $detail): ?string
    {
        if ($detail instanceof Collection) {
            $detail = $detail->toArray();
        }

        if ($this->isDisplayableScalar($detail)) {
            return $this->stringifyScalar($detail);
        }

        if (is_array($detail)) {
            $segments = [];

            foreach ($detail as $key => $value) {
                $stringified = $this->stringifyDetail($value);
                if ($stringified === null) {
                    continue;
                }

                if (is_string($key) && $key !== '') {
                    $segments[] = "{$key}: {$stringified}";
                } else {
                    $segments[] = $stringified;
                }
            }

            return empty($segments) ? null : implode('; ', $segments);
        }

        return null;
    }

    /**
     * 判断给定值是否为适合直接展示的标量。
     */
    protected function isDisplayableScalar(mixed $value): bool
    {
        return is_string($value) || is_numeric($value) || is_bool($value);
    }

    /**
     * 将标量值转成字符串表示，布尔值输出 true/false。
     */
    protected function stringifyScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
