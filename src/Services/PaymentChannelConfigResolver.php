<?php

namespace WeiJuKeJi\PaymentBill\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use Throwable;
use Yansongda\Pay\Pay;

/**
 * 根据支付渠道配置生成 yansongda pay 可用的动态配置。
 */
class PaymentChannelConfigResolver
{
    protected string $storageDisk;

    protected array $fallbackDisks = ['private', 'local'];

    /**
     * 初始化解析器，读取默认的存储磁盘配置。
     */
    public function __construct()
    {
        $this->storageDisk = config('finance.payment.storage_disk', 'local');
    }

    /**
     * 生成指定渠道的配置数组。
     *
     * @return array<string, mixed>
     */
    public function resolve(PaymentChannel $channel): array
    {
        return match ($channel->channel) {
            'wechat' => $this->buildWechatConfig($channel),
            'alipay' => $this->buildAlipayConfig($channel),
            default => throw new InvalidArgumentException('暂不支持的支付渠道：'.$channel->channel),
        };
    }

    /**
     * 构建微信支付配置。
     *
     * @return array<string, mixed>
     */
    protected function buildWechatConfig(PaymentChannel $channel): array
    {
        $certConfig = $this->buildWechatCertificates($channel);

        ray($channel);

        return array_filter(
            [
                'wechat' => [
                    'default' => tap(array_filter([
                        'mch_id' => $channel->wechat_mch_id,
                        'mch_secret_key_v2' => $channel->wechat_mch_secret_key_v2,
                        'mch_secret_key' => $channel->wechat_mch_secret_key,
                        'mch_secret_cert' => $certConfig['mch_secret_cert'] ?? null,
                        'mch_public_cert_path' => $certConfig['mch_public_cert_path'] ?? null,
                        'wechat_public_cert_path' => $certConfig['wechat_public_cert_path'] ?? null,
                        'notify_url' => $channel->notify_url,
                        'mp_app_id' => $channel->wechat_mp_app_id,
                        'mini_app_id' => $channel->wechat_mini_app_id,
                        'app_id' => $channel->wechat_app_id,
                        'sub_mp_app_id' => $channel->wechat_sub_mp_app_id,
                        'sub_app_id' => $channel->wechat_sub_app_id,
                        'sub_mini_app_id' => $channel->wechat_sub_mini_app_id,
                        'sub_mch_id' => $channel->wechat_sub_mch_id,
                        'mode' => $this->mapMode($channel->mode),
                    ], static fn ($value) => $value !== null && $value !== ''), function ($config) use ($channel) {
                        Log::debug('生成微信渠道配置', [
                            'channel_id' => $channel->getKey(),
                            'config' => $config,
                        ]);
                    }),
                ],
                'http' => $this->httpConfig(),
                'logger' => $this->loggerConfig(),
            ],
            static fn ($value) => ! empty($value)
        );
    }

    /**
     * 构建支付宝配置。
     *
     * @return array<string, mixed>
     */
    protected function buildAlipayConfig(PaymentChannel $channel): array
    {
        $appPublicCert = $this->absoluteStoragePath($channel->alipay_app_public_cert_path);
        $alipayPublicCert = $this->absoluteStoragePath($channel->alipay_public_cert_path);
        $alipayRootCert = $this->absoluteStoragePath($channel->alipay_root_cert_path);

        return array_filter(
            [
                'alipay' => [
                    'default' => array_filter([
                        'app_id' => $channel->alipay_app_id,
                        'app_secret_cert' => $channel->alipay_app_secret_cert,
                        'app_public_cert_path' => $appPublicCert,
                        'alipay_public_cert_path' => $alipayPublicCert,
                        'alipay_root_cert_path' => $alipayRootCert,
                        'return_url' => $channel->return_url,
                        'notify_url' => $channel->notify_url,
                        'service_provider_id' => $channel->alipay_service_provider_id,
                        'app_auth_token' => $channel->alipay_app_auth_token,
                        'mode' => $this->mapMode($channel->mode),
                    ], static fn ($value) => $value !== null && $value !== ''),
                ],
                'http' => $this->httpConfig(),
                'logger' => $this->loggerConfig(),
            ],
            static fn ($value) => ! empty($value)
        );
    }

    /**
     * 构建微信证书配置。
     *
     * @return array<string, mixed>
     */
    protected function buildWechatCertificates(PaymentChannel $channel): array
    {
        $secretCertPath = $this->absoluteStoragePath($channel->wechat_mch_secret_cert_path);
        $publicCertPath = $this->absoluteStoragePath($channel->wechat_mch_public_cert_path);

        $platformCert = $this->absoluteStoragePath($channel->wechat_public_cert_path);
        $platformMap = [];

        if ($platformCert) {
            $serial = $this->readCertificateSerial($platformCert);
            $platformMap = $serial ? [$serial => $platformCert] : [];
        }

        return array_filter([
            'mch_secret_cert' => $secretCertPath,
            'mch_public_cert_path' => $publicCertPath,
            'wechat_public_cert_path' => $platformMap,
        ], static fn ($value) => ! empty($value));
    }

    /**
     * 根据 Storage 路径生成绝对路径。
     */
    protected function absoluteStoragePath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $relativePath = ltrim(trim($relativePath), '/\\');

        if ($this->isAbsolutePath($relativePath) && is_file($relativePath)) {
            return realpath($relativePath) ?: $relativePath;
        }

        $diskCandidates = array_unique(array_filter(array_merge([$this->storageDisk], $this->fallbackDisks)));
        foreach ($diskCandidates as $diskName) {
            if (! config("filesystems.disks.$diskName")) {
                continue;
            }

            $disk = Storage::disk($diskName);

            $path = method_exists($disk, 'path')
                ? $disk->path($relativePath)
                : storage_path('app/'.$relativePath);

            if (is_file($path)) {
                return realpath($path) ?: $path;
            }

            // 如果文件不存在也返回构造路径，交由调用方决定
            return $path;
        }

        // 兜底尝试 storage/app/private 与 storage/app 目录
        $paths = [
            storage_path('app/'.$relativePath),
            storage_path('app/private/'.$relativePath),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return realpath($path) ?: $path;
            }
        }

        Log::warning('证书文件未找到', [
            'relative' => $relativePath,
            'checked_paths' => $paths,
            'disk' => $this->storageDisk,
        ]);

        // 返回首个候选路径，交由后续组件决定是否有效
        return $paths[0];
    }

    /**
     * 判断给定路径是否为绝对路径，兼容 *nix 与 Windows。
     */
    protected function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '\\'])
            || preg_match('/^[A-Z]:[\\\\\\/]/i', $path) === 1;
    }

    /**
     * 读取证书序列号。
     */
    protected function readCertificateSerial(string $path): ?string
    {
        try {
            $content = file_get_contents($path);
            if ($content === false) {
                return null;
            }

            $resource = openssl_x509_read($content);
            if ($resource === false) {
                return null;
            }

            $details = openssl_x509_parse($resource) ?: [];
            openssl_x509_free($resource);

            $serial = $details['serialNumberHex'] ?? null;
            if (empty($serial) && isset($details['serialNumber'])) {
                $serial = strtoupper(dechex((int) $details['serialNumber']));
            }

            return $serial ? strtoupper($serial) : null;
        } catch (Throwable $exception) {
            Log::warning('读取证书序列号失败：'.$exception->getMessage(), ['path' => $path]);

            return null;
        }
    }

    /**
     * 映射渠道模式到 yansongda Pay 常量。
     */
    protected function mapMode(?string $mode): int
    {
        return match ($mode) {
            'service' => Pay::MODE_SERVICE,
            'sandbox' => Pay::MODE_SANDBOX,
            default => Pay::MODE_NORMAL,
        };
    }

    /**
     * 提供 HTTP 调用超时配置。
     *
     * @return array<string, float|int>
     */
    protected function httpConfig(): array
    {
        $defaults = config('finance.payment.http', []);

        return array_filter([
            'timeout' => Arr::get($defaults, 'timeout', 30.0),
            'connect_timeout' => Arr::get($defaults, 'connect_timeout', 10.0),
        ]);
    }

    /**
     * 提供日志配置，默认沿用项目 debug 设置。
     *
     * @return array<string, mixed>
     */
    protected function loggerConfig(): array
    {
        $logger = config('finance.payment.logger', []);

        $channel = Arr::get($logger, 'channel');
        if ($channel) {
            return array_merge(['enable' => true, 'channel' => $channel], Arr::only($logger, ['file', 'level', 'type', 'max_file']));
        }

        return array_filter([
            'enable' => config('app.debug', false),
            'level' => Arr::get($logger, 'level', 'info'),
            'type' => Arr::get($logger, 'type', 'daily'),
            'max_file' => Arr::get($logger, 'max_file', 14),
        ], static fn ($value) => $value !== null);
    }
}
