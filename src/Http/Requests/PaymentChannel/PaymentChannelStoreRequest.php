<?php

namespace WeiJuKeJi\PaymentBill\Http\Requests\PaymentChannel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentChannelStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $jsonFields = ['extra'];

        foreach ($jsonFields as $field) {
            if (is_string($this->{$field})) {
                $decoded = json_decode($this->{$field}, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }

        // 处理布尔字段
        $booleanFields = ['is_enabled', 'is_bill_download_enabled'];
        foreach ($booleanFields as $field) {
            if ($this->has($field) && is_string($this->{$field})) {
                $this->merge([$field => filter_var($this->{$field}, FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        if (! $this->has('mode')) {
            $this->merge(['mode' => 'normal']);
        }

        if (! $this->has('is_enabled')) {
            $this->merge(['is_enabled' => true]);
        }

        if (! $this->has('is_bill_download_enabled')) {
            $this->merge(['is_bill_download_enabled' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', 'string', Rule::in(['wechat', 'alipay'])],
            'mode' => ['required', 'string', Rule::in(['normal', 'service'])],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_bill_download_enabled' => ['sometimes', 'boolean'],
            'remark' => ['nullable', 'string'],
            'alipay_app_id' => ['nullable', 'string', 'max:64'],
            'alipay_app_secret_cert' => ['nullable', 'string'],
            'alipay_app_public_cert_path' => ['nullable', 'string', 'max:512'],
            'alipay_public_cert_path' => ['nullable', 'string', 'max:512'],
            'alipay_root_cert_path' => ['nullable', 'string', 'max:512'],
            'alipay_service_provider_id' => ['nullable', 'string', 'max:64'],
            'alipay_app_auth_token' => ['nullable', 'string', 'max:255'],
            'wechat_mch_id' => ['nullable', 'string', 'max:64'],
            'wechat_mch_secret_key_v2' => ['nullable', 'string'],
            'wechat_mch_secret_key' => ['nullable', 'string', 'max:128'],
            'wechat_app_id' => ['nullable', 'string', 'max:64'],
            'wechat_mp_app_id' => ['nullable', 'string', 'max:64'],
            'wechat_mini_app_id' => ['nullable', 'string', 'max:64'],
            'wechat_sub_mch_id' => ['nullable', 'string', 'max:64'],
            'wechat_sub_app_id' => ['nullable', 'string', 'max:64'],
            'wechat_sub_mp_app_id' => ['nullable', 'string', 'max:64'],
            'wechat_sub_mini_app_id' => ['nullable', 'string', 'max:64'],
            'extra' => ['nullable', 'array'],
            'wechat_mch_public_cert_file' => ['required_if:channel,wechat', 'file', 'max:4096'],
            'wechat_mch_secret_cert_file' => ['required_if:channel,wechat', 'file', 'max:4096'],
            'wechat_public_cert_file' => ['required_if:channel,wechat', 'file', 'max:4096'],
            'alipay_app_cert_file' => ['required_if:channel,alipay', 'file', 'max:4096'],
            'alipay_public_cert_file' => ['required_if:channel,alipay', 'file', 'max:4096'],
            'alipay_root_cert_file' => ['required_if:channel,alipay', 'file', 'max:4096'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '支付方式名称',
            'channel' => '支付渠道',
            'mode' => '支付模式',
            'is_enabled' => '是否启用',
            'is_bill_download_enabled' => '是否开启下载账单',
            'remark' => '备注',
            'alipay_app_id' => '支付宝应用ID',
            'alipay_app_secret_cert' => '支付宝应用私钥',
            'alipay_app_public_cert_path' => '支付宝应用公钥证书路径',
            'alipay_public_cert_path' => '支付宝公钥证书路径',
            'alipay_root_cert_path' => '支付宝根证书路径',
            'alipay_service_provider_id' => '支付宝服务商ID',
            'alipay_app_auth_token' => '支付宝第三方应用授权token',
            'wechat_mch_id' => '微信商户号',
            'wechat_mch_secret_key_v2' => '微信v2商户私钥',
            'wechat_mch_secret_key' => '微信v3商户密钥',
            'wechat_mch_secret_cert_path' => '微信商户私钥证书路径',
            'wechat_mch_public_cert_path' => '微信商户公钥证书路径',
            'wechat_public_cert_path' => '微信平台公钥证书路径',
            'wechat_app_id' => '微信APP的AppID',
            'wechat_mp_app_id' => '微信公众号AppID',
            'wechat_mini_app_id' => '微信小程序AppID',
            'wechat_sub_mch_id' => '微信子商户号',
            'wechat_sub_app_id' => '微信子应用AppID',
            'wechat_sub_mp_app_id' => '微信子公众号AppID',
            'wechat_sub_mini_app_id' => '微信子小程序AppID',
            'extra' => '扩展字段',
            'wechat_mch_public_cert_file' => '微信商户公钥证书文件',
            'wechat_mch_secret_cert_file' => '微信商户私钥证书文件',
            'wechat_public_cert_file' => '微信平台公钥证书文件',
            'alipay_app_cert_file' => '支付宝应用公钥证书文件',
            'alipay_public_cert_file' => '支付宝公钥证书文件',
            'alipay_root_cert_file' => '支付宝根证书文件',
        ];
    }
}
