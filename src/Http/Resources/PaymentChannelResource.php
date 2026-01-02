<?php

namespace WeiJuKeJi\PaymentBill\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentChannelResource extends JsonResource
{
    /**
     * 注意：为确保安全，此 Resource 不返回任何敏感字段（密钥、私钥、证书路径、auth_token）。
     * 仅返回用于展示和配置管理的公开信息。
     *
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
            'remark' => $this->remark,
            'alipay_app_id' => $this->alipay_app_id,
            'alipay_service_provider_id' => $this->alipay_service_provider_id,
            'wechat_mch_id' => $this->wechat_mch_id,
            'wechat_app_id' => $this->wechat_app_id,
            'wechat_mp_app_id' => $this->wechat_mp_app_id,
            'wechat_mini_app_id' => $this->wechat_mini_app_id,
            'wechat_sub_mch_id' => $this->wechat_sub_mch_id,
            'wechat_sub_app_id' => $this->wechat_sub_app_id,
            'wechat_sub_mp_app_id' => $this->wechat_sub_mp_app_id,
            'wechat_sub_mini_app_id' => $this->wechat_sub_mini_app_id,
            'extra' => $this->extra,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
