# Service 开发规范

## 核心原则

**Service 层是为了封装复杂业务逻辑，而非简单的数据访问。**

避免过度设计，只在真正需要时使用 Service 层。

## 何时使用 Service

### ✅ **应该使用 Service 的场景**

1. **复杂业务逻辑**
   - 涉及多个模型的协调操作
   - 需要多步骤处理的业务流程
   - 包含复杂计算或数据转换

2. **外部 API 调用**
   - 调用第三方服务（OCR、支付、短信等）
   - HTTP 请求到外部系统
   - 需要处理外部 API 的重试、超时、错误

3. **数据库事务**
   - 需要在一个事务中操作多个表
   - 需要保证数据一致性的复杂操作

4. **文件处理**
   - 文件上传、下载、转换
   - 图片处理、PDF 生成等

5. **需要复用的业务逻辑**
   - 同一业务逻辑需要在多个控制器中使用
   - 需要在队列任务、命令行中复用

6. **需要独立测试的业务逻辑**
   - 复杂算法需要单元测试
   - 业务规则需要独立验证

### ❌ **不应该使用 Service 的场景**

1. **简单 CRUD 操作**
   ```php
   // ❌ 不好：为简单查询创建 Service
   class UserService {
       public function getUsers(array $filters) {
           return User::filter($filters)->paginate();
       }
   }

   // ✅ 好：直接在 Controller 中使用
   public function index(Request $request): JsonResponse {
       $params = $request->only(['status', 'per_page']);
       $perPage = $this->resolvePerPage($params);
       $users = User::filter($params)->paginate($perPage);
       return $this->respondWithPagination($users, UserResource::class);
   }
   ```

2. **单表查询**
   ```php
   // ❌ 不好：简单的 find 操作不需要 Service
   class UserService {
       public function getUser(int $id) {
           return User::find($id);
       }
   }

   // ✅ 好：使用路由模型绑定
   public function show(User $user): JsonResponse {
       return $this->respondWithResource($user, UserResource::class);
   }
   ```

3. **简单的数据转换**
   ```php
   // ❌ 不好：简单格式化用 Service
   class UserService {
       public function formatUser(User $user) {
           return [
               'id' => $user->id,
               'name' => $user->name
           ];
       }
   }

   // ✅ 好：使用 Resource
   class UserResource extends JsonResource {
       public function toArray($request) {
           return [
               'id' => $this->id,
               'name' => $this->name,
           ];
       }
   }
   ```

4. **仅仅是调用模型方法**
   ```php
   // ❌ 不好：没有额外逻辑的包装
   class OrderService {
       public function cancelOrder(Order $order) {
           $order->cancel();
       }
   }

   // ✅ 好：直接在 Controller 中调用
   public function cancel(Order $order): JsonResponse {
       $order->cancel();
       return $this->success(null, '订单已取消');
   }
   ```

## 实际案例分析

### 案例 1: OCR 识别（✅ 应该用 Service）

```php
// ✅ 好：复杂业务逻辑适合用 Service
class InvoiceOcrService {
    public function recognize(
        UploadedFile $file,
        ?string $ocrDriver = null,
        array $options = []
    ): InvoiceOcrRecord {
        // 1. 计算文件哈希
        $fileHash = hash_file('md5', $file->getRealPath());

        // 2. 保存文件到存储
        $filePath = $this->saveFile($file);

        // 3. 获取 OCR 驱动（可能是航信、阿里云等）
        $driver = $this->ocrDriverManager->driver($ocrDriver);

        // 4. 创建 OCR 记录
        $ocrRecord = $this->createOcrRecord(...);

        try {
            // 5. 调用外部 OCR API
            $result = $driver->recognize($filePath, $options);

            // 6. 处理识别结果
            $ocrRecord->markAsCompleted($result);

        } catch (\Exception $e) {
            // 7. 错误处理和日志
            $ocrRecord->markAsFailed($e->getMessage());
            $this->logger->error('OCR failed', [...]);
        }

        return $ocrRecord;
    }
}
```

**为什么需要 Service？**
- 涉及文件处理（上传、保存）
- 调用外部 OCR API
- 多步骤业务流程
- 需要错误处理和日志
- 逻辑可能在队列任务中复用

### 案例 2: OCR 记录列表（❌ 不应该用 Service）

```php
// ❌ 不好：简单查询不需要 Service
class InvoiceOcrService {
    public function getRecords(array $filters = []): LengthAwarePaginator {
        return InvoiceOcrRecord::filter($filters)
            ->orderBy('created_at', 'desc')
            ->paginate();
    }
}

// ✅ 好：直接在 Controller 中处理
class InvoiceOcrController extends Controller {
    public function index(Request $request): JsonResponse {
        $params = $request->only(['status', 'ocr_driver', 'per_page']);
        $perPage = $this->resolvePerPage($params);

        $records = InvoiceOcrRecord::filter($params)
            ->with(['invoice'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->respondWithPagination($records, InvoiceOcrRecordResource::class);
    }
}
```

**为什么不需要 Service？**
- 只是简单的查询和筛选
- 使用模型的 `filter` scope 已足够
- 没有复杂业务逻辑
- 不涉及外部调用

## Service 标准模板

```php
<?php
namespace Modules\{Module}\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\{Module}\Models\{Model};

/**
 * {业务名称} 服务类
 *
 * 负责处理 {具体业务描述}
 */
class {Business}Service
{
    /**
     * 构造函数 - 注入必要的依赖
     */
    public function __construct(
        protected ExternalApiClient $apiClient,
        protected FileStorage $storage
    ) {}

    /**
     * 核心业务方法
     *
     * @param array $data 业务数据
     * @return mixed 返回结果
     * @throws \Exception 异常说明
     */
    public function performBusinessLogic(array $data): mixed
    {
        // 使用事务（如果需要）
        return DB::transaction(function () use ($data) {
            // 1. 数据验证和准备
            $validated = $this->validateData($data);

            // 2. 执行业务逻辑
            $result = $this->executeLogic($validated);

            // 3. 调用外部服务（如果需要）
            $externalResult = $this->apiClient->call($result);

            // 4. 保存结果
            $model = $this->saveResult($externalResult);

            // 5. 触发事件或通知（如果需要）
            event(new BusinessCompleted($model));

            return $model;
        });
    }

    // 私有辅助方法...
    private function validateData(array $data): array { ... }
    private function executeLogic(array $data): array { ... }
    private function saveResult(array $data): Model { ... }
}
```

## 文件位置

```
Modules/{ModuleName}/app/Services/
├── {Business}Service.php          # 主要业务服务
├── External/                       # 外部 API 集成
│   ├── OcrDriverManager.php
│   └── PaymentGateway.php
└── Support/                        # 辅助服务
    ├── FileUploader.php
    └── ImageProcessor.php
```

## 命名规范

- Service 类名：`{Business}Service`（如 `InvoiceOcrService`、`PaymentService`）
- 方法名：使用动词开头，描述业务动作（如 `recognize`、`process`、`calculate`）
- 避免使用 `get*` 作为 Service 方法名（`get` 通常是简单查询，应在 Controller/Model 中处理）

## 最佳实践

### 1. Controller 直接使用模型

```php
// ✅ 简单操作直接在 Controller
class OrderController extends Controller {
    public function index(Request $request): JsonResponse {
        $orders = Order::filter($request->only(['status', 'date']))
            ->with(['customer', 'items'])
            ->paginate($this->resolvePerPage($request));
        return $this->respondWithPagination($orders, OrderResource::class);
    }
}
```

### 2. 使用模型 Scope 封装查询逻辑

```php
// ✅ 在 Model 中定义 scope
class Order extends Model {
    public function scopeFilter($query, array $filters) {
        return $query
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['date'] ?? null, fn($q, $v) => $q->whereDate('created_at', $v));
    }
}
```

### 3. Service 只处理复杂逻辑

```php
// ✅ Service 处理复杂的发票开具流程
class InvoiceIssueService {
    public function issue(array $invoiceData): InvoiceIssueRecord {
        return DB::transaction(function () use ($invoiceData) {
            // 1. 创建发票记录
            $invoice = Invoice::create($invoiceData);

            // 2. 调用税务系统 API 开票
            $taxResult = $this->taxApiClient->issueInvoice($invoice);

            // 3. 创建开票记录
            $issueRecord = InvoiceIssueRecord::create([...]);

            // 4. 更新发票状态
            $invoice->markAsIssued($taxResult);

            // 5. 发送通知
            event(new InvoiceIssued($invoice));

            return $issueRecord;
        });
    }
}
```

## 总结

| 场景 | 使用位置 | 说明 |
|------|---------|------|
| 简单查询列表 | Controller + Model Filter | 使用模型的 `filter` scope |
| 单条记录查询 | Controller + 路由模型绑定 | Laravel 自动注入 |
| 创建/更新记录 | Controller + FormRequest | 简单的增删改 |
| 数据格式化 | Resource | 使用 API Resource |
| 复杂业务流程 | Service | 多步骤、事务、外部调用 |
| 外部 API 集成 | Service | 第三方服务调用 |
| 文件处理 | Service | 上传、转换、存储 |

**核心思想：Controller 薄，Model 胖，Service 少而精。**
