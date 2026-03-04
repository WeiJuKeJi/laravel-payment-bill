<?php

namespace WeiJuKeJi\PaymentBill\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WeiJuKeJi\PaymentBill\Enums\ReconciliationStatusEnum;

class ReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_id' => ['nullable', 'integer', 'min:1'],
            'remark' => ['required', 'string', 'max:1000'],
            'amount_diff' => ['nullable', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return [
            'business_id' => '业务订单ID',
            'remark' => '备注',
            'amount_diff' => '金额差异',
        ];
    }

    public function messages(): array
    {
        return [
            'remark.required' => '请填写对账备注',
            'remark.max' => '备注内容不能超过1000个字符',
        ];
    }
}
