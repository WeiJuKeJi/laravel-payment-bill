<?php

namespace WeiJuKeJi\PaymentBill\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;

class PaymentChannelCertificateManager
{
    /**
     * 按渠道类型写入证书文件，并返回需要更新到模型的字段。
     */
    public function storeCertificates(PaymentChannel $channel, array $files): array
    {
        $updates = [];
        $basePath = sprintf('payment/certs/%s/%d', $channel->channel, $channel->getKey());

        if ($channel->channel === 'wechat') {
            $updates = array_merge($updates, $this->handleWechatCertificates($files, $basePath));
        }

        if ($channel->channel === 'alipay') {
            $updates = array_merge($updates, $this->handleAlipayCertificates($files, $basePath));
        }

        return $updates;
    }

    public function removeCertificates(PaymentChannel $channel): void
    {
        $basePath = sprintf('payment/certs/%s/%d', $channel->channel, $channel->getKey());

        if (Storage::disk('local')->exists($basePath)) {
            Storage::disk('local')->deleteDirectory($basePath);
        }
    }

    protected function handleWechatCertificates(array $files, string $basePath): array
    {
        $updates = [];

        /** @var UploadedFile|null $publicCert */
        $publicCert = Arr::get($files, 'wechat_mch_public_cert_file');
        if ($publicCert instanceof UploadedFile) {
            $updates['wechat_mch_public_cert_path'] = $this->storeFile($publicCert, $basePath, 'apiclient_cert.pem');
        }

        /** @var UploadedFile|null $secretCert */
        $secretCert = Arr::get($files, 'wechat_mch_secret_cert_file');
        if ($secretCert instanceof UploadedFile) {
            $updates['wechat_mch_secret_cert_path'] = $this->storeFile($secretCert, $basePath, 'apiclient_key.pem');
        }

        /** @var UploadedFile|null $platformCert */
        $platformCert = Arr::get($files, 'wechat_public_cert_file');
        if ($platformCert instanceof UploadedFile) {
            $updates['wechat_public_cert_path'] = $this->storeFile($platformCert, $basePath, 'platform_cert.pem');
        }

        return $updates;
    }

    protected function handleAlipayCertificates(array $files, string $basePath): array
    {
        $updates = [];

        /** @var UploadedFile|null $appCert */
        $appCert = Arr::get($files, 'alipay_app_cert_file');
        if ($appCert instanceof UploadedFile) {
            $updates['alipay_app_public_cert_path'] = $this->storeFile($appCert, $basePath, 'alipay_app_cert.crt');
        }

        /** @var UploadedFile|null $publicCert */
        $publicCert = Arr::get($files, 'alipay_public_cert_file');
        if ($publicCert instanceof UploadedFile) {
            $updates['alipay_public_cert_path'] = $this->storeFile($publicCert, $basePath, 'alipay_public_cert.crt');
        }

        /** @var UploadedFile|null $rootCert */
        $rootCert = Arr::get($files, 'alipay_root_cert_file');
        if ($rootCert instanceof UploadedFile) {
            $updates['alipay_root_cert_path'] = $this->storeFile($rootCert, $basePath, 'alipay_root_cert.crt');
        }

        return $updates;
    }

    protected function storeFile(UploadedFile $file, string $basePath, string $filename): string
    {
        $path = $basePath.'/'.$filename;

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        Storage::disk('local')->makeDirectory($basePath);
        Storage::disk('local')->putFileAs($basePath, $file, $filename);

        return $path;
    }
}
