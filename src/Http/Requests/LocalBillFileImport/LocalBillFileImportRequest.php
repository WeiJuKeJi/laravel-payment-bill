<?php

namespace WeiJuKeJi\PaymentBill\Http\Requests\LocalBillFileImport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;

class LocalBillFileImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 准备验证数据，转换字符串形式的布尔值。
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('force')) {
            $data['force'] = $this->convertToBoolean($this->input('force'));
        }

        if ($this->has('auto_import')) {
            $data['auto_import'] = $this->convertToBoolean($this->input('auto_import'));
        }

        if ($this->has('bill_period')) {
            $data['bill_period'] = strtolower((string) $this->input('bill_period'));
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * 将各种形式的布尔值转换为真正的布尔类型。
     *
     * @param  mixed  $value
     */
    private function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_channel_id' => ['required', 'integer', Rule::exists(PaymentChannel::class, 'id')],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'bill_type' => ['nullable', 'string', 'max:64'],
            'bill_period' => ['nullable', 'string', Rule::in(['day', 'month'])],
            'force' => ['sometimes', 'boolean'],
            'auto_import' => ['sometimes', 'boolean'],
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
            'files' => '账单文件',
            'files.*' => '账单文件',
            'bill_type' => '账单类型',
            'bill_period' => '账单周期',
            'force' => '强制覆盖标记',
            'auto_import' => '自动导入标记',
        ];
    }

    /**
     * 自定义错误消息。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => '请上传至少一个账单文件',
            'files.min' => '请上传至少一个账单文件',
            'files.*.required' => '账单文件不能为空',
            'files.*.file' => '上传的必须是有效的文件',
            'files.*.mimes' => '账单文件必须是 CSV 或 TXT 格式',
            'files.*.max' => '单个文件大小不能超过 50MB',
            'bill_period.in' => '账单周期仅支持 day 或 month',
        ];
    }
}
