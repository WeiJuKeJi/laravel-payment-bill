<?php

namespace WeiJuKeJi\PaymentBill\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 微信账单明细资源。
 *
 * @mixin \Modules\Finance\Models\WechatBill
 */
class WechatBillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'payment_channel_id' => $this->payment_channel_id,
            'payment_channel' => $this->whenLoaded('paymentChannel', fn () => [
                'id' => $this->paymentChannel->id,
                'name' => $this->paymentChannel->name,
                'channel' => $this->paymentChannel->channel,
                'mode' => $this->paymentChannel->mode,
            ]),
            'trade_time' => $this->trade_time?->toDateTimeString(),
            'appid' => $this->appid,
            'mch_id' => $this->mch_id,
            'special_mch_id' => $this->special_mch_id,
            'device_id' => $this->device_id,
            'wechat_transaction_id' => $this->wechat_transaction_id,
            'out_trade_no' => $this->out_trade_no,
            'user_open_id' => $this->user_open_id,
            'trade_type' => $this->trade_type,
            'trade_state' => $this->trade_state,
            'payment_bank' => $this->payment_bank,
            'currency' => $this->currency,
            'amounts' => [
                'total_amount' => $this->total_amount,
                'settlement_amount' => $this->settlement_amount,
                'voucher_amount' => $this->voucher_amount,
                'refund_amount' => $this->refund_amount,
                'refund_voucher_amount' => $this->refund_voucher_amount,
                'refund_apply_amount' => $this->refund_apply_amount,
                'fee_amount' => $this->fee_amount,
                'fee_rate' => $this->fee_rate,
            ],
            'refund' => [
                'wechat_refund_no' => $this->wechat_refund_no,
                'out_refund_no' => $this->out_refund_no,
                'refund_type' => $this->refund_type,
                'refund_status' => $this->refund_status,
            ],
            'goods_name' => $this->goods_name,
            'goods_info' => $this->goods_info,
            'is_order_associated' => $this->is_order_associated,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
