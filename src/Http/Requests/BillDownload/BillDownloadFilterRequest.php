<?php

namespace WeiJuKeJi\PaymentBill\Http\Requests\BillDownload;

use Illuminate\Foundation\Http\FormRequest;

class BillDownloadFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 统一校验账单下载列表的查询参数，配合 EloquentFilter 使用。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_channel_id' => ['sometimes', 'nullable'],
            'download_status' => ['sometimes', 'nullable'],
            'import_status' => ['sometimes', 'nullable'],
            'bill_type' => ['sometimes', 'nullable'],
            'bill_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_between' => ['sometimes', 'array', 'size:2'],
            'date_between.*' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'keywords' => ['sometimes', 'nullable', 'string'],
            'order' => ['sometimes', 'nullable', 'in:asc,desc'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'only_failed' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $inputs = $this->all();

        $nullableFields = [
            'payment_channel_id',
            'download_status',
            'import_status',
            'bill_type',
            'bill_date',
            'date_from',
            'date_to',
            'keywords',
            'order',
            'per_page',
            'only_failed',
        ];

        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $inputs) && $this->isBlank($inputs[$field])) {
                $inputs[$field] = null;
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

        $booleanFields = ['only_failed'];

        foreach ($booleanFields as $field) {
            if (! array_key_exists($field, $inputs) || $inputs[$field] === null) {
                continue;
            }

            $normalized = filter_var($inputs[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized === null) {
                unset($inputs[$field]);
            } else {
                $inputs[$field] = $normalized;
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
