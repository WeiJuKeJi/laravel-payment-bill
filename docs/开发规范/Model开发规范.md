# Model 开发规范

## 7.1 标准 Model 模板

```php
<?php

namespace Modules\{ModuleName}\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use EloquentFilter\Filterable;

/**
 * {ModelName} 模型
 *
 * @property int $id
 * @property string $name
 * @property string $status
 */
class {ModelName} extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $table = '{module_prefix}_{table_name}';

    protected $fillable = [
        'field1',
        'field2',
        // ⚠️ 不包含 company_id 等不应批量赋值的字段
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * 配置过滤器
     */
    public function modelFilter()
    {
        return $this->provideFilter(\Modules\{ModuleName}\ModelFilters\{ModelName}Filter::class);
    }

    /**
     * 关联关系
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * 作用域 - 启用状态
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 访问器 - 状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'active' => '启用',
            'inactive' => '停用',
            default => '未知',
        };
    }
}
```

## 7.2 Model 规则

**✅ 必须遵守**:
- 使用 `Filterable` trait
- 实现 `modelFilter()` 方法
- `$fillable` 不包含不应批量赋值的字段（如 company_id）
- 使用 `$casts` 定义类型转换
- 关联关系使用返回类型声明
- 作用域方法使用 `scope` 前缀
- 访问器提供枚举字段的文本值
