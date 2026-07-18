<?php

namespace WeiJuKeJi\PaymentBill\Http\Requests\AlipayBill;

use Illuminate\Foundation\Http\FormRequest;

class AlipayBillFilterRequest extends FormRequest
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
            'payment_channel_id' => ['sometimes', 'nullable'],
            'bill_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_between' => ['sometimes', 'array', 'size:2'],
            'date_between.*' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'biz_type' => ['sometimes', 'nullable'],
            'transaction_kind' => ['sometimes', 'nullable'],
            'reconciliation_status' => ['sometimes', 'nullable'],
            'alipay_trade_no' => ['sometimes', 'nullable', 'string'],
            'merchant_order_no' => ['sometimes', 'nullable', 'string'],
            'keywords' => ['sometimes', 'nullable', 'string'],
            'project_id' => ['sometimes', 'nullable', 'integer'],
            'has_project' => ['sometimes', 'nullable', 'boolean'],
            'order' => ['sometimes', 'nullable', 'in:asc,desc'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'with_channel' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $inputs = $this->all();

        $nullableFields = [
            'payment_channel_id',
            'bill_date',
            'date_from',
            'date_to',
            'biz_type',
            'transaction_kind',
            'reconciliation_status',
            'alipay_trade_no',
            'merchant_order_no',
            'keywords',
            'project_id',
            'has_project',
            'order',
            'per_page',
            'with_channel',
        ];

        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $inputs) && $this->isBlank($inputs[$field])) {
                $inputs[$field] = null;
            }
        }

        if (array_key_exists('with_channel', $inputs) && $inputs['with_channel'] !== null) {
            $normalized = filter_var($inputs['with_channel'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized === null) {
                unset($inputs['with_channel']);
            } else {
                $inputs['with_channel'] = $normalized;
            }
        }

        if (array_key_exists('has_project', $inputs) && $inputs['has_project'] !== null) {
            $normalized = filter_var($inputs['has_project'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalized === null) {
                unset($inputs['has_project']);
            } else {
                $inputs['has_project'] = $normalized;
            }
        }

        if (array_key_exists('date_between', $inputs)) {
            $value = $inputs['date_between'];

            if ($this->isBlank($value)) {
                unset($inputs['date_between']);
            } elseif (is_string($value)) {
                $segments = array_filter(array_map('trim', explode(',', $value)));
                if (count($segments) === 2) {
                    $inputs['date_between'] = array_values($segments);
                } else {
                    unset($inputs['date_between']);
                }
            }
        }

        $this->replace($inputs);
    }

    protected function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return empty($value);
        }

        if (is_string($value)) {
            return $value === '' || strtolower($value) === 'undefined';
        }

        return $value === null;
    }
}
