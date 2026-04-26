<?php

namespace WeiJuKeJi\PaymentBill\Http\Requests\BillDownload;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;

class BillDownloadCalendarRequest extends FormRequest
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
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'bill_type' => ['sometimes', 'nullable', 'string'],
            'with_channel' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $inputs = $this->all();

        foreach (['payment_channel_id', 'year', 'bill_type', 'with_channel'] as $field) {
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

        $this->replace($inputs);
    }

    protected function isBlank(mixed $value): bool
    {
        if (is_string($value)) {
            return $value === '' || strtolower($value) === 'undefined';
        }

        return $value === null;
    }
}
