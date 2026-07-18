<?php

namespace WeiJuKeJi\PaymentBill\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use WeiJuKeJi\PaymentBill\Enums\ReconciliationStatusEnum;

/**
 * 支付宝账单明细资源。
 *
 * @mixin \WeiJuKeJi\PaymentBill\Models\AlipayBill
 */
class AlipayBillResource extends JsonResource
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
            'bill_date' => $this->bill_date?->toDateString(),
            'alipay_trade_no' => $this->alipay_trade_no,
            'merchant_order_no' => $this->merchant_order_no,
            'biz_type' => $this->biz_type,
            'goods_name' => $this->goods_name,
            'created_time' => $this->created_time?->toDateTimeString(),
            'completed_time' => $this->completed_time?->toDateTimeString(),
            'store_id' => $this->store_id,
            'store_name' => $this->store_name,
            'operator' => $this->operator,
            'terminal_id' => $this->terminal_id,
            'counterparty_account' => $this->counterparty_account,
            'amounts' => [
                'order_amount' => $this->order_amount,
                'merchant_settlement_amount' => $this->merchant_settlement_amount,
                'alipay_red_envelope_amount' => $this->alipay_red_envelope_amount,
                'point_deduction_amount' => $this->point_deduction_amount,
                'alipay_discount_amount' => $this->alipay_discount_amount,
                'merchant_discount_amount' => $this->merchant_discount_amount,
                'coupon_amount' => $this->coupon_amount,
                'merchant_red_envelope_amount' => $this->merchant_red_envelope_amount,
                'card_amount' => $this->card_amount,
                'service_fee_amount' => $this->service_fee_amount,
                'profit_sharing_amount' => $this->profit_sharing_amount,
            ],
            'coupon_name' => $this->coupon_name,
            'refund_request_no' => $this->refund_request_no,
            'remark' => $this->remark,
            'project' => $this->getAttribute('resolved_project'),
            'reconciliation' => $this->formatReconciliation(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * 格式化对账信息
     */
    protected function formatReconciliation(): array
    {
        // 获取枚举类
        $enumClass = config(
            'payment-bill.enums.reconciliation_status',
            ReconciliationStatusEnum::class
        );

        return [
            'status' => $enumClass::toArraySafe($this->reconciliation_status),
            'business_type' => $this->business_type,
            'business_id' => $this->business_id,
            'reconciled_at' => $this->reconciled_at?->toDateTimeString(),
            'amount_diff' => $this->reconciliation_amount_diff,
            'remark' => $this->reconciliation_remark,
            'reconciled_by' => $this->reconciled_by,
        ];
    }
}
