# Resource 使用规范

## 3.1 创建 Resource

**位置**: `Modules/{ModuleName}/app/Http/Resources/{ModelName}Resource.php`

**示例**:
```php
<?php

namespace Modules\ScanInvoice\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ScanInvoiceRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,

            // 枚举字段返回值和文本
            'status' => $this->status,
            'status_text' => $this->status_text,

            // 日期时间使用 ISO 8601 格式
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // 条件加载关联资源
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),

            // 或使用嵌套 Resource
            'business_scenario' => $this->whenLoaded('businessScenario',
                fn() => new BusinessScenarioResource($this->businessScenario)
            ),
        ];
    }
}
```

## 3.2 Resource 使用规则

**✅ 必须遵守**:
- 所有 API 返回必须使用 Resource 包裹
- 字段命名使用 snake_case
- 枚举字段返回原始值 + 文本值（如 `status` 和 `status_text`）
- 日期时间使用 `toISOString()` 格式
- 使用 `whenLoaded()` 条件加载关联资源
- 不要暴露敏感字段（如密码、token）

**❌ 禁止做法**:
- ❌ 直接返回模型实例
- ❌ 直接返回数组
- ❌ 在 Controller 中手动构建响应数组
