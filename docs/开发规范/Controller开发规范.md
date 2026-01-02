# Controller 开发规范

## 文件位置

`Modules/{ModuleName}/app/Http/Controllers/{ModelName}Controller.php`

## 标准模板

```php
<?php
namespace Modules\{Module}\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\{Module}\Models\{Model};
use Modules\{Module}\Http\Requests\{Model}\{Model}StoreRequest;
use Modules\{Module}\Http\Requests\{Model}\{Model}UpdateRequest;
use Modules\{Module}\Http\Resources\{Model}Resource;

class {Model}Controller extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $params = $request->only(['筛选参数...', 'per_page', 'page']);
        $perPage = $this->resolvePerPage($params);
        $records = {Model}::filter($params)->with(['relation'])->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->respondWithPagination($records, {Model}Resource::class);
    }

    public function store({Model}StoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $model = {Model}::create($data);
        $model->load(['relation']);
        return $this->success({Model}Resource::make($model)->toArray($request), '创建成功');
    }

    public function show(Request $request, {Model} $model): JsonResponse
    {
        $model->load(['relation']);
        return $this->respondWithResource($model, {Model}Resource::class);
    }

    public function update({Model}UpdateRequest $request, {Model} $model): JsonResponse
    {
        $model->update($request->validated());
        $model->refresh()->load(['relation']);
        return $this->success({Model}Resource::make($model)->toArray($request), '更新成功');
    }

    public function destroy({Model} $model): JsonResponse
    {
        $model->delete();
        return $this->success(null, '删除成功');
    }
}
```

## 方法返回速查

| 方法 | 返回方式 |
|------|----------|
| index | `$this->respondWithPagination($records, Resource::class)` |
| store | `$this->success(Resource::make($model)->toArray($request), '创建成功')` |
| show | `$this->respondWithResource($model, Resource::class)` |
| update | `$this->success(Resource::make($model)->toArray($request), '更新成功')` |
| destroy | `$this->success(null, '删除成功')` |

## 必须遵守

- 继承 `App\Http\Controllers\Controller`
- 使用类型提示 `JsonResponse`
- 使用路由模型绑定（参数名与路由参数匹配）
- 使用 `resolvePerPage()` 处理分页
- 加载必要的关联关系
