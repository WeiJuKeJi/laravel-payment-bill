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
     * 准备验证数据，将空字符串转换为 null。
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // 将空字符串的 tar_type 转换为 null
        if ($this->has('tar_type') && $this->input('tar_type') === '') {
            $data['tar_type'] = null;
        }

        if (! empty($data)) {
            $this->merge($data);
        }
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
            'tar_type' => ['nullable', 'string', Rule::in(['GZIP', 'ZIP', 'CSV', 'NONE'])],
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
