<?php

namespace WeiJuKeJi\PaymentBill\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;

class BillDownloadStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_channel_id' => ['required', 'integer', Rule::exists(PaymentChannel::class, 'id')],
            'bill_date' => ['required', 'date', 'before_or_equal:today'],
            'bill_type' => ['nullable', 'string', 'max:64'],
            'force' => ['sometimes', 'boolean'],
            'tar_type' => ['sometimes', 'string', Rule::in(['GZIP', 'ZIP', 'CSV', 'NONE'])],
        ];
    }

    /**
     * 中文字段名，方便返回校验提示。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment_channel_id' => '支付渠道',
            'bill_date' => '账单日期',
            'bill_type' => '账单类型',
            'tar_type' => '压缩格式',
            'force' => '强制重试标记',
        ];
    }
}
