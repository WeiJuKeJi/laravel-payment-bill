# Filter 使用规范

## 使用场景

✅ 用于：Controller 的 `index()` 列表方法，处理多个可选查询参数
❌ 不用于：Service 层查询、固定条件查询（使用 Model Scope）

## 文件位置

`Modules/{ModuleName}/app/ModelFilters/{ModelName}Filter.php`

## ⚠️ ID 字段方法命名规则（重要）

EloquentFilter 对 `_id` 后缀参数有特殊处理：

| URL 参数 | Filter 方法名 |
|----------|--------------|
| `company_id` | `company()` |
| `parent_id` | `parent()` |
| `status` | `status()` |
| `config_type` | `configType()` |

```php
// ❌ 错误 - 不会被调用
public function companyId(int $id) { }

// ✅ 正确 - 去掉 _id 后缀
public function company(int|string $companyId)
{
    return $this->where('company_id', (int)$companyId);
}
```

## Filter 类模板

```php
<?php
namespace Modules\{Module}\ModelFilters;

use EloquentFilter\ModelFilter;

class {Model}Filter extends ModelFilter
{
    public $relations = [];

    // ID 字段 - 方法名去掉 _id
    public function company(int|string $id)
    {
        return $this->where('company_id', (int)$id);
    }

    // 模糊搜索
    public function name(string $value)
    {
        return $this->where('name', 'like', "%{$value}%");
    }

    // 枚举筛选
    public function status(string $status)
    {
        return $this->where('status', $status);
    }

    // 日期范围
    public function startDate(string $date)
    {
        return $this->whereDate('created_at', '>=', $date);
    }

    // JSON 字段 (PostgreSQL)
    public function buyerName(string $name)
    {
        return $this->whereRaw("buyer_info->>'buyer_name' ILIKE ?", ["%{$name}%"]);
    }
}
```

## Model 配置

```php
use EloquentFilter\Filterable;

class Model extends BaseModel
{
    use Filterable;

    public function modelFilter()
    {
        return $this->provideFilter(\Modules\{Module}\ModelFilters\{Model}Filter::class);
    }
}
```

## Controller 使用

```php
public function index(Request $request): JsonResponse
{
    $params = $request->only(['company_id', 'status', 'name', 'per_page', 'page']);
    $perPage = $this->resolvePerPage($params);
    $records = Model::filter($params)->with(['relation'])->orderBy('created_at', 'desc')->paginate($perPage);
    return $this->respondWithPagination($records, ModelResource::class);
}
```

## 注意事项

- ❌ 禁止使用 `input()` 作为方法名（与父类冲突）
- ✅ 方法参数必须有类型声明
- ✅ ID 字段参数类型用 `int|string`（URL 传递的是字符串）
