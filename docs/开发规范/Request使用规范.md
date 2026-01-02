# Request 使用规范

## 文件位置

`Modules/{ModuleName}/app/Http/Requests/{ModelName}/{ModelName}{Action}Request.php`

## Store Request 模板

```php
<?php
namespace Modules\{Module}\Http\Requests\{Model};

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class {Model}StoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'nsrsbh' => ['required', 'string', 'size:18', Rule::unique('table', 'nsrsbh')],
            'type' => ['required', Rule::in(['a', 'b', 'c'])],
            'parent_id' => ['nullable', 'integer', 'exists:table,id'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '名称',
            'nsrsbh' => '纳税人识别号',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('is_default')) {
            $this->merge(['is_default' => $this->boolean('is_default')]);
        }
    }
}
```

## Update Request 模板

```php
<?php
namespace Modules\{Module}\Http\Requests\{Model};

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class {Model}UpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('model')->id;
        return [
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'nsrsbh' => ['sometimes', 'required', 'string', 'size:18', Rule::unique('table', 'nsrsbh')->ignore($id)],
        ];
    }

    protected function prepareForValidation()
    {
        // ⚠️ 移除不应被修改的字段
        if ($this->has('company_id')) {
            $this->request->remove('company_id');
        }
    }
}
```

## 核心规则

| 场景 | 规则 |
|------|------|
| Store 必填字段 | `required` |
| Update 可选字段 | `sometimes`, `required` |
| 唯一性验证 (Update) | `Rule::unique()->ignore($id)` |
| 枚举验证 | `Rule::in([...])` |
| 外键验证 | `exists:table,id` |

## 常用验证规则

```php
'field' => ['required', 'string', 'max:100'],
'email' => ['required', 'email', Rule::unique('table', 'email')],
'status' => ['required', Rule::in(['active', 'inactive'])],
'parent_id' => ['nullable', 'integer', 'exists:table,id'],
'is_default' => ['sometimes', 'boolean'],
```

## ⚠️ 注意事项

- 所有接口必须使用 FormRequest
- 使用 `attributes()` 定义字段中文名
- Update 时在 `prepareForValidation()` 中移除 `company_id` 等不可修改字段
