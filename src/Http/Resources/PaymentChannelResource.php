<?php

namespace WeiJuKeJi\PaymentBill\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentChannelResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'channel_text' => $this->channel_text,
            'mode' => $this->mode,
            'is_enabled' => $this->is_enabled,
            'is_enabled_text' => $this->is_enabled_text,
            'is_bill_download_enabled' => $this->is_bill_download_enabled,
            'is_bill_download_enabled_text' => $this->is_bill_download_enabled_text,
            'remark' => $this->remark,
            // 支付宝配置
            'alipay_app_id' => $this->alipay_app_id,
            'alipay_app_secret_cert' => $this->alipay_app_secret_cert,
            'alipay_app_public_cert_path' => $this->alipay_app_public_cert_path,
            'alipay_public_cert_path' => $this->alipay_public_cert_path,
            'alipay_root_cert_path' => $this->alipay_root_cert_path,
            'alipay_service_provider_id' => $this->alipay_service_provider_id,
            'alipay_app_auth_token' => $this->alipay_app_auth_token,
            // 微信配置
            'wechat_mch_id' => $this->wechat_mch_id,
            'wechat_mch_secret_key' => $this->wechat_mch_secret_key,
            'wechat_mch_secret_key_v2' => $this->wechat_mch_secret_key_v2,
            'wechat_mch_secret_cert_path' => $this->wechat_mch_secret_cert_path,
            'wechat_mch_public_cert_path' => $this->wechat_mch_public_cert_path,
            'wechat_public_cert_path' => $this->wechat_public_cert_path,
            'wechat_app_id' => $this->wechat_app_id,
            'wechat_mp_app_id' => $this->wechat_mp_app_id,
            'wechat_mini_app_id' => $this->wechat_mini_app_id,
            'wechat_sub_mch_id' => $this->wechat_sub_mch_id,
            'wechat_sub_app_id' => $this->wechat_sub_app_id,
            'wechat_sub_mp_app_id' => $this->wechat_sub_mp_app_id,
            'wechat_sub_mini_app_id' => $this->wechat_sub_mini_app_id,
            'extra' => $this->extra,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
