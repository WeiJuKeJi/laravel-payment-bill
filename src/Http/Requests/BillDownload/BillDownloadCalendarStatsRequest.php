<?php

namespace WeiJuKeJi\PaymentBill\Http\Requests\BillDownload;

use Illuminate\Foundation\Http\FormRequest;

class BillDownloadCalendarStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
