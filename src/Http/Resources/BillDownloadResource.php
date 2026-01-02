<?php

namespace WeiJuKeJi\PaymentBill\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 账单下载记录输出资源。
 *
 * @mixin \Modules\Finance\Models\BillDownload
 */
class BillDownloadResource extends JsonResource
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
            'bill_type' => $this->bill_type,
            'download_status' => $this->download_status,
            'import_status' => $this->import_status,
            'file_path' => $this->file_path,
            'download_error' => $this->download_error,
            'import_error' => $this->import_error,
            'download_started_at' => $this->download_started_at?->toDateTimeString(),
            'download_completed_at' => $this->download_completed_at?->toDateTimeString(),
            'download_retry_count' => $this->download_retry_count,
            'download_last_attempt_at' => $this->download_last_attempt_at?->toDateTimeString(),
            'import_totals' => [
                'transaction_count' => $this->import_total_transaction_count,
                'new_count' => $this->import_total_new_count,
                'updated_count' => $this->import_total_updated_count,
                'settlement_amount' => $this->import_total_settlement_amount,
                'refund_amount' => $this->import_total_refund_amount,
                'refund_voucher_amount' => $this->import_total_refund_voucher_amount,
                'fee_amount' => $this->import_total_fee_amount,
                'order_amount' => $this->import_total_order_amount,
                'refund_apply_amount' => $this->import_total_refund_apply_amount,
                'started_at' => $this->import_started_at?->toDateTimeString(),
                'completed_at' => $this->import_completed_at?->toDateTimeString(),
                'duration' => $this->import_duration,
                'retry_count' => $this->import_retry_count,
                'last_attempt_at' => $this->import_last_attempt_at?->toDateTimeString(),
            ],
            'bill_summary' => [
                'transaction_count' => $this->bill_summary_transaction_count,
                'settlement_amount' => $this->bill_summary_settlement_amount,
                'refund_amount' => $this->bill_summary_refund_amount,
                'refund_voucher_amount' => $this->bill_summary_refund_voucher_amount,
                'fee_amount' => $this->bill_summary_fee_amount,
                'order_amount' => $this->bill_summary_order_amount,
                'refund_apply_amount' => $this->bill_summary_refund_apply_amount,
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
