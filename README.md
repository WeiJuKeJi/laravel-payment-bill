# Laravel Payment Bill

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weijukeji/laravel-payment-bill.svg?style=flat-square)](https://packagist.org/packages/weijukeji/laravel-payment-bill)
[![Total Downloads](https://img.shields.io/packagist/dt/weijukeji/laravel-payment-bill.svg?style=flat-square)](https://packagist.org/packages/weijukeji/laravel-payment-bill)
[![License](https://img.shields.io/packagist/l/weijukeji/laravel-payment-bill.svg?style=flat-square)](https://packagist.org/packages/weijukeji/laravel-payment-bill)

Laravel 支付账单管理扩展包，支持微信支付、支付宝账单自动下载与数据同步。

## 功能特性

- ✅ 支持微信支付、支付宝账单下载
- ✅ 自动解析账单数据并导入数据库
- ✅ 支持多支付渠道管理
- ✅ 账单对账功能，支持自动对账和人工对账
- ✅ 定时任务自动下载与导入
- ✅ RESTful API 接口
- ✅ 完整的错误处理与重试机制
- ✅ 集成 laravel-enum-options，前端友好的枚举支持
- ✅ 基于 yansongda/pay 3.x
- ✅ 支持 Laravel 10.x、11.x、12.x、13.x

## 安装

### 通过 Composer 安装

```bash
composer require weijukeji/laravel-payment-bill
```

### 发布配置文件（必需）

```bash
# 发布配置文件
php artisan vendor:publish --tag=payment-bill-config

# 运行迁移（扩展包会自动加载迁移文件，无需发布）
php artisan migrate
```

### 发布迁移文件（可选）

**注意：迁移文件会自动从扩展包目录加载，通常不需要发布。**

仅在以下情况需要发布迁移文件：
- 需要自定义表名、字段或索引
- 需要查看完整的数据库结构
- 需要调整迁移执行顺序

```bash
# 发布迁移文件（可选）
php artisan vendor:publish --tag=payment-bill-migrations

# 运行迁移
php artisan migrate
```

## 配置

### 配置文件

发布配置文件后，你可以在 `config/payment-bill.php` 中修改所有配置项：

```bash
php artisan vendor:publish --tag=payment-bill-config
```

**重要配置项：**

- `storage_disk` - 账单文件存储磁盘（默认：local）
- `http` - HTTP 客户端超时配置
- `logger` - 日志记录配置
- `queues` - 队列连接和队列名称配置
- `route_prefix` - API 路由前缀（默认：api/payment-bill）
- `guard` - 认证守卫（默认：sanctum）
- `schedules` - 定时任务配置
  - `enabled` - 是否启用自动定时任务（默认：true）
  - `download` - 账单下载任务配置
    - `enabled` - 是否启用（默认：true）
    - `time` - 执行时间（默认：09:00）
    - `timezone` - 时区（默认：使用 app.timezone）
    - `retry` - 下载失败重试配置
      - `enabled` - 是否启用（默认：true）
      - `every_hours` - 重试间隔小时数（默认：2）
      - `days` - 往前追溯的天数（默认：3）
      - `timezone` - 时区（默认：使用 app.timezone）
  - `import` - 账单导入任务配置
    - `enabled` - 是否启用（默认：true）
    - `time` - 执行时间（默认：09:30）
    - `timezone` - 时区（默认：使用 app.timezone）
    - `retry` - 导入失败重试配置
      - `enabled` - 是否启用（默认：true）
      - `every_hours` - 重试间隔小时数（默认：2）
      - `days` - 往前追溯的天数（默认：3）
      - `timezone` - 时区（默认：使用 app.timezone）
- `enums` - 枚举类配置
  - `reconciliation_status` - 对账状态枚举类
- `reconciliation` - 对账配置
  - `amount_tolerance` - 金额差异容差（默认：0.01 元）
  - `default_reconciler` - 默认对账操作人（默认：system）

### 支付渠道配置

#### 微信支付

1. 创建支付渠道记录
2. 上传商户证书文件（apiclient_cert.pem、apiclient_key.pem、platform_cert.pem）
3. 配置商户号、商户密钥、AppID 等信息

#### 支付宝

1. 创建支付渠道记录
2. 上传应用证书文件（alipay_app_cert.crt、alipay_public_cert.crt、alipay_root_cert.crt）
3. 配置应用 ID、应用私钥等信息

## 使用

### API 接口

所有 API 接口默认需要 Laravel Sanctum 认证。

#### 支付渠道管理

```bash
# 获取支付渠道列表
GET /api/payment-bill/payment-channels

# 创建支付渠道
POST /api/payment-bill/payment-channels

# 查看支付渠道详情
GET /api/payment-bill/payment-channels/{id}

# 更新支付渠道
PUT /api/payment-bill/payment-channels/{id}

# 删除支付渠道
DELETE /api/payment-bill/payment-channels/{id}
```

#### 账单下载管理

```bash
# 获取账单下载记录列表
GET /api/payment-bill/bill-downloads

# 手动触发账单下载
POST /api/payment-bill/bill-downloads

# 查看账单下载详情
GET /api/payment-bill/bill-downloads/{id}

# 下载账单文件到浏览器
GET /api/payment-bill/bill-downloads/{id}/file

# 重新下载账单（从支付平台重新下载）
POST /api/payment-bill/bill-downloads/{id}/download

# 重新导入账单数据（解析文件并导入数据库）
POST /api/payment-bill/bill-downloads/{id}/import
```

**手动触发账单下载示例：**

```bash
# 微信账单下载（无需指定压缩格式）
curl -X POST /api/payment-bill/bill-downloads \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_channel_id": 1,
    "bill_date": "2026-01-15",
    "bill_type": "ALL"
  }'

# 支付宝账单下载（可选指定压缩格式，默认为 GZIP）
curl -X POST /api/payment-bill/bill-downloads \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_channel_id": 2,
    "bill_date": "2026-01-15",
    "bill_type": "ALL",
    "tar_type": "ZIP"
  }'
```

**参数说明：**
- `payment_channel_id`（必填）：支付渠道ID
- `bill_date`（必填）：账单日期（YYYY-MM-DD）
- `bill_type`（可选）：账单类型，默认 ALL
- `force`（可选）：是否强制重新下载，默认 false
- `tar_type`（可选）：压缩格式（仅支付宝），可选值：GZIP、ZIP，默认 GZIP

**注意：** `tar_type` 参数主要用于支付宝账单，微信账单无需指定此参数。

#### 账单数据查询

```bash
# 微信账单列表
GET /api/payment-bill/wechat-bills

# 微信账单详情
GET /api/payment-bill/wechat-bills/{id}

# 支付宝账单列表
GET /api/payment-bill/alipay-bills

# 支付宝账单详情
GET /api/payment-bill/alipay-bills/{id}
```

#### 对账管理

```bash
# 微信账单对账
POST /api/payment-bill/wechat-bills/{id}/reconciliation/mark-as-matched     # 标记为已匹配
POST /api/payment-bill/wechat-bills/{id}/reconciliation/mark-as-mismatched  # 标记为不匹配
POST /api/payment-bill/wechat-bills/{id}/reconciliation/mark-as-manual      # 标记为人工处理
POST /api/payment-bill/wechat-bills/{id}/reconciliation/mark-as-ignored     # 标记为已忽略
POST /api/payment-bill/wechat-bills/{id}/reconciliation/mark-as-pending     # 重置为待对账

# 支付宝账单对账
POST /api/payment-bill/alipay-bills/{id}/reconciliation/mark-as-matched     # 标记为已匹配
POST /api/payment-bill/alipay-bills/{id}/reconciliation/mark-as-mismatched  # 标记为不匹配
POST /api/payment-bill/alipay-bills/{id}/reconciliation/mark-as-manual      # 标记为人工处理
POST /api/payment-bill/alipay-bills/{id}/reconciliation/mark-as-ignored     # 标记为已忽略
POST /api/payment-bill/alipay-bills/{id}/reconciliation/mark-as-pending     # 重置为待对账
```

**请求参数说明：**

| 接口 | 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|------|
| mark-as-matched | business_id | integer | 是 | 业务订单ID |
| | amount_diff | decimal | 否 | 金额差异（默认0） |
| | remark | string | 否 | 备注 |
| mark-as-mismatched | business_id | integer | 是 | 业务订单ID |
| | amount_diff | decimal | 是 | 金额差异 |
| | remark | string | 是 | 备注说明差异原因 |
| mark-as-manual | remark | string | 是 | 备注 |
| | business_id | integer | 否 | 业务订单ID |
| mark-as-ignored | remark | string | 是 | 备注说明忽略原因 |
| mark-as-pending | - | - | - | 无参数 |

**请求示例：**

```bash
# 标记为已匹配
curl -X POST /api/payment-bill/wechat-bills/123/reconciliation/mark-as-matched \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "business_id": 456,
    "amount_diff": 0.00,
    "remark": "自动对账成功"
  }'

# 标记为已忽略（人工操作最常用）
curl -X POST /api/payment-bill/wechat-bills/123/reconciliation/mark-as-ignored \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "remark": "测试订单，已忽略"
  }'

# 重置为待对账
curl -X POST /api/payment-bill/wechat-bills/123/reconciliation/mark-as-pending \
  -H "Authorization: Bearer {token}"
```

**响应示例：**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "id": 123,
    "out_trade_no": "ORDER123",
    "total_amount": "100.00",
    "reconciliation": {
      "status": {
        "value": "ignored",
        "label": "已忽略",
        "color": "info",
        "icon": "eye-slash"
      },
      "business_type": null,
      "business_id": null,
      "reconciled_at": "2026-01-08 14:30:00",
      "amount_diff": "0.00",
      "remark": "测试订单，已忽略",
      "reconciled_by": "user_1"
    }
  }
}
```

### 对账功能

扩展包提供了完整的对账功能，支持与业务系统订单进行对账。

#### 对账状态

对账功能使用 [laravel-enum-options](https://github.com/weijukeji/laravel-enum-options) 扩展包，提供前端友好的枚举支持。

**对账状态枚举：**

- `pending` - 待对账
- `matched` - 已匹配（对账一致）
- `mismatched` - 不匹配（有差异）
- `manual` - 人工处理
- `ignored` - 已忽略

#### 代码示例

```php
use WeiJuKeJi\PaymentBill\Models\WechatBill;
use WeiJuKeJi\PaymentBill\Models\AlipayBill;

// 标记为已匹配
$bill = WechatBill::find(1);

// 方式1: 传入业务模型实例（推荐）
$order = Order::find(12345);
$bill->markAsMatched(
    business: $order,           // 业务订单模型实例
    amountDiff: 0.00,          // 金额差异
    reconciledBy: 'system',    // 对账操作人
    remark: '自动对账成功'      // 备注（可选）
);

// 方式2: 传入业务订单ID（不推荐，无法记录 business_type）
$bill->markAsMatched(
    business: 12345,           // 仅业务订单ID
    amountDiff: 0.00,
    reconciledBy: 'system',
    remark: '自动对账成功'
);

// 标记为不匹配
$bill->markAsMismatched(
    business: $order,
    amountDiff: -0.01,
    remark: '金额不一致：业务系统99.99元，账单100.00元',
    reconciledBy: 'system'
);

// 标记为人工处理
$bill->markAsManual(
    remark: '无法自动匹配，需人工处理',
    reconciledBy: 'admin_001',
    business: $order  // 可选
);

// 标记为已忽略（如测试订单）
$bill->markAsIgnored(
    remark: '测试订单，已忽略',
    reconciledBy: 'admin_001'
);

// 重置为待对账
$bill->markAsPending();

// 状态判断
if ($bill->isMatched()) {
    // 对账成功
}

if ($bill->isMismatched()) {
    // 有差异
}

if ($bill->isReconciled()) {
    // 已对账（不包括待对账状态）
}

// 查询待对账记录
$pendingBills = WechatBill::where('reconciliation_status', 'pending')
    ->get();

// 查询有差异的记录
$mismatchedBills = WechatBill::where('reconciliation_status', 'mismatched')
    ->orderBy('reconciled_at', 'desc')
    ->get();
```

#### 关联业务订单

扩展包内置了多态关系支持，可以关联任意业务模型。

**基本用法：**

```php
use WeiJuKeJi\PaymentBill\Models\WechatBill;
use App\Models\Order;

// 对账时传入模型实例，自动记录 business_type 和 business_id
$order = Order::find(123);
$bill = WechatBill::find(1);
$bill->markAsMatched(
    business: $order,
    amountDiff: 0.00,
    reconciledBy: 'system'
);

// 查询关联的业务订单
$bill = WechatBill::find(1);
$order = $bill->business; // 返回 Order 模型实例

// 预加载业务订单
$bills = WechatBill::with('business')->get();
foreach ($bills as $bill) {
    if ($bill->business) {
        echo $bill->business->order_no;
    }
}
```

**支持多种业务模型：**

```php
use App\Models\Order;
use App\Models\RechargeOrder;
use App\Models\RefundOrder;

// 商品订单
$order = Order::find(123);
$bill->markAsMatched(business: $order, amountDiff: 0, reconciledBy: 'system');

// 充值订单
$rechargeOrder = RechargeOrder::find(456);
$bill->markAsMatched(business: $rechargeOrder, amountDiff: 0, reconciledBy: 'system');

// 退款订单
$refundOrder = RefundOrder::find(789);
$bill->markAsMatched(business: $refundOrder, amountDiff: 0, reconciledBy: 'system');

// 查询时判断业务类型
$bill = WechatBill::with('business')->find(1);
if ($bill->business instanceof Order) {
    echo "商品订单: " . $bill->business->order_no;
} elseif ($bill->business instanceof RechargeOrder) {
    echo "充值订单: " . $bill->business->recharge_no;
}
```

**反向关联（在业务模型中定义）：**

```php
// app/Models/Order.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    /**
     * 关联微信账单
     */
    public function wechatBills(): MorphMany
    {
        return $this->morphMany(
            \WeiJuKeJi\PaymentBill\Models\WechatBill::class,
            'business'
        );
    }

    /**
     * 关联支付宝账单
     */
    public function alipayBills(): MorphMany
    {
        return $this->morphMany(
            \WeiJuKeJi\PaymentBill\Models\AlipayBill::class,
            'business'
        );
    }
}

// 使用
$order = Order::with(['wechatBills', 'alipayBills'])->find(123);
foreach ($order->wechatBills as $bill) {
    echo $bill->out_trade_no;
}
```

**仅使用 ID 对账（不推荐）：**

如果只传入 ID 而不是模型实例，`business_type` 将为 null，无法使用 `business` 关系查询：

```php
// 不推荐：仅传入 ID
$bill->markAsMatched(
    business: 12345,  // 仅 ID，business_type 为 null
    amountDiff: 0,
    reconciledBy: 'system'
);

// 此时 $bill->business 将返回 null
// 需要自行根据 business_id 查询对应的业务模型
```

**方式 3：根据业务类型关联不同模型**

```php
namespace App\Models;

use WeiJuKeJi\PaymentBill\Models\WechatBill as BaseWechatBill;

class WechatBill extends BaseWechatBill
{
    // 商品订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'business_id');
    }

    // 充值订单
    public function rechargeOrder()
    {
        return $this->belongsTo(RechargeOrder::class, 'business_id');
    }

    // 根据业务逻辑选择关联
    public function getBusinessOrder()
    {
        // 根据订单号前缀或其他标识判断
        if (str_starts_with($this->out_trade_no, 'RC')) {
            return $this->rechargeOrder;
        }
        return $this->order;
    }
}
```

#### 自动对账示例

```php
use WeiJuKeJi\PaymentBill\Models\WechatBill;
use App\Models\Order;

class ReconciliationService
{
    /**
     * 对账微信账单
     */
    public function reconcile(WechatBill $bill): void
    {
        // 1. 根据商户订单号查找业务订单
        $order = Order::where('order_no', $bill->out_trade_no)->first();

        if (!$order) {
            // 找不到订单，标记为人工处理
            $bill->markAsManual(
                remark: '未找到对应的业务订单',
                reconciledBy: 'system'
            );
            return;
        }

        // 2. 比对金额
        $tolerance = config('payment-bill.reconciliation.amount_tolerance', 0.01);
        $amountDiff = $order->amount - $bill->total_amount;

        if (abs($amountDiff) <= $tolerance) {
            // 金额一致，标记为已匹配
            $bill->markAsMatched(
                businessId: $order->id,
                amountDiff: $amountDiff,
                reconciledBy: 'system',
                remark: '自动对账成功'
            );
        } else {
            // 金额不一致，标记为不匹配
            $bill->markAsMismatched(
                businessId: $order->id,
                amountDiff: $amountDiff,
                remark: "金额差异：业务系统{$order->amount}元，账单{$bill->total_amount}元",
                reconciledBy: 'system'
            );
        }
    }

    /**
     * 批量对账
     */
    public function reconcileBatch(string $date): array
    {
        $bills = WechatBill::where('reconciliation_status', 'pending')
            ->whereDate('trade_time', $date)
            ->get();

        $stats = ['total' => 0, 'matched' => 0, 'mismatch' => 0, 'manual' => 0];

        foreach ($bills as $bill) {
            $this->reconcile($bill);
            $stats['total']++;

            if ($bill->isMatched()) {
                $stats['matched']++;
            } elseif ($bill->isMismatched()) {
                $stats['mismatch']++;
            } else {
                $stats['manual']++;
            }
        }

        return $stats;
    }
}
```

#### API 响应示例

```json
{
  "id": 1,
  "out_trade_no": "ORDER20260108001",
  "total_amount": "100.00",
  "reconciliation": {
    "status": {
      "value": "matched",
      "label": "已匹配",
      "color": "success",
      "icon": "check-circle"
    },
    "business_id": 12345,
    "reconciled_at": "2026-01-08 10:30:00",
    "amount_diff": "0.00",
    "remark": "自动对账成功",
    "reconciled_by": "system"
  }
}
```

#### 自定义对账状态枚举

如果需要自定义对账状态，可以创建自己的枚举类：

```php
// app/Enums/ReconciliationStatusEnum.php
namespace App\Enums;

use WeiJuKeJi\EnumOptions\Traits\EnumOptions;

enum ReconciliationStatusEnum: string
{
    use EnumOptions;

    case PENDING = 'pending';
    case MATCHED = 'matched';
    case MISMATCH = 'mismatched';
    case MANUAL = 'manual';
    case IGNORED = 'ignored';
    case CUSTOM_STATUS = 'custom';  // 自定义状态

    public function label(): string
    {
        return match($this) {
            self::PENDING => '待对账',
            self::MATCHED => '已匹配',
            self::MISMATCH => '不匹配',
            self::MANUAL => '人工处理',
            self::IGNORED => '已忽略',
            self::CUSTOM_STATUS => '自定义状态',
        };
    }

    // ... 其他方法
}
```

然后在配置文件中指定使用自定义枚举：

```php
// config/payment-bill.php
'enums' => [
    'reconciliation_status' => \App\Enums\ReconciliationStatusEnum::class,
],
```

### 命令行工具

#### 下载账单

```bash
# 自动下载所有启用渠道的前一天账单
php artisan payment-bill:download

# 指定日期
php artisan payment-bill:download --date=2025-01-01

# 指定支付渠道
php artisan payment-bill:download --channel=1

# 强制重新下载
php artisan payment-bill:download --force
```

#### 导入账单

```bash
# 导入所有已下载但未导入的账单
php artisan payment-bill:import

# 指定日期
php artisan payment-bill:import --date=2025-01-01

# 指定支付渠道
php artisan payment-bill:import --channel=1

# 强制重新导入
php artisan payment-bill:import --force
```

**Web API 操作：**

除了命令行工具，你还可以通过 Web API 对单个账单记录进行操作：

```bash
# 1. 重新下载账单（从支付平台重新下载）
curl -X POST /api/payment-bill/bill-downloads/1216/download \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# 2. 重新导入账单数据（解析文件并导入数据库）
curl -X POST /api/payment-bill/bill-downloads/1216/import \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# 3. 下载账单文件到浏览器
curl -X GET /api/payment-bill/bill-downloads/1216/file \
  -H "Authorization: Bearer {token}" \
  -O -J
```

**使用场景：**
- **重新下载**：账单下载失败，或需要更新账单文件
- **重新导入**：账单数据导入失败，或需要重新解析文件
- **下载文件**：将服务器上的账单文件下载到本地电脑

**响应示例：**

重新下载响应：
```json
{
  "code": 200,
  "msg": "账单下载任务已派发",
  "data": {
    "id": 1216,
    "payment_channel_id": 1,
    "bill_date": "2022-07-24",
    "bill_type": "ALL",
    "download_status": "pending"
  }
}
```

重新导入响应：
```json
{
  "code": 200,
  "msg": "微信账单导入任务已派发",
  "data": {
    "id": 1216,
    "payment_channel_id": 1,
    "bill_date": "2022-07-24",
    "bill_type": "ALL",
    "download_status": "completed",
    "import_status": "pending",
    "file_path": "payment/bills/wechat/1/20220724.csv",
    "file_size": 1024000,
    "downloaded_at": "2022-07-25 10:00:00",
    "imported_at": null,
    "payment_channel": {
      "id": 1,
      "name": "微信支付渠道",
      "channel": "wechat"
    }
  }
}
```

**注意：**
- 重新下载会强制覆盖已存在的账单文件
- 重新导入会强制覆盖已存在的账单数据
- 所有操作都是异步处理，通过队列执行

#### 导入本地历史账单文件

由于微信支付接口仅支持下载最近3个月的账单，扩展包提供了从本地目录批量导入历史账单文件的功能。

**基本用法：**

```bash
# 1. 预览模式，查看将要导入的文件
php artisan payment-bill:import-local-files \
    --directory=/path/to/bills \
    --channel=wechat \
    --dry-run

# 2. 交互模式，手动选择要导入的文件（支持多选）
php artisan payment-bill:import-local-files \
    --directory=/path/to/bills \
    --channel=wechat \
    --interactive

# 3. 批量导入所有文件
php artisan payment-bill:import-local-files \
    --directory=/path/to/bills \
    --channel=wechat

# 4. 批量导入并自动触发账单数据导入任务
php artisan payment-bill:import-local-files \
    --directory=/path/to/bills \
    --channel=wechat \
    --auto-import

# 5. 导入微信月账单，按交易时间自动拆分为日账单后导入
php artisan payment-bill:import-local-files \
    --directory=/path/to/monthly-bills \
    --channel=wechat \
    --bill-period=month \
    --auto-import

# 6. 强制覆盖已存在的记录
php artisan payment-bill:import-local-files \
    --directory=/path/to/bills \
    --channel=wechat \
    --force \
    --auto-import
```

**参数说明：**

| 参数 | 说明 | 必填 | 默认值 |
|------|------|------|--------|
| `--directory` | 本地账单文件目录路径 | 是 | - |
| `--channel` | 支付渠道ID或标识（wechat/alipay） | 是 | - |
| `--pattern` | 文件名匹配模式 | 否 | *.csv |
| `--bill-type` | 账单类型 | 否 | ALL |
| `--bill-period` | 账单周期，`day` 或 `month`；`month` 仅支持微信账单 | 否 | day |
| `--auto-import` | 导入文件后自动触发账单数据导入任务 | 否 | false |
| `--force` | 强制覆盖已存在的记录 | 否 | false |
| `--dry-run` | 预览模式，不实际执行操作 | 否 | false |
| `--interactive` | 交互模式，手动选择要导入的文件 | 否 | false |

**支持的文件名格式：**

- `2022-07-24_ALL.csv` → 日期：2022-07-24，类型：ALL
- `20220724.csv` → 日期：2022-07-24，类型：ALL（默认）
- 微信月账单使用 `--bill-period=month` 时不依赖文件名日期，会按 CSV 中的 `交易时间` 拆分为每日账单记录。

**使用场景：**

1. **导入历史账单**：微信支付仅支持下载最近3个月账单，通过此命令导入手动下载的历史账单
2. **批量补充数据**：将从微信后台批量下载的账单文件一次性导入系统
3. **数据迁移**：从其他系统迁移账单数据到当前系统

**交互模式示例：**

```bash
$ php artisan payment-bill:import-local-files --directory=/Users/oran/Downloads/bills --channel=wechat --interactive

支付渠道：微信支付(wechat，ID: 1)
找到 1212 个匹配的文件。
请选择要导入的文件（多选，使用空格分隔序号）：
  [1] 2022-07-24 - 2022-07-24_ALL.csv (1.58 MB)
  [2] 2022-07-29 - 2022-07-29_ALL.csv (772.46 KB)
  [3] 2022-07-30 - 2022-07-30_ALL.csv (1.63 MB)
  ...
  [0] 全部选择

请输入序号（如：1 3 5 或 0 表示全选）: 1 2 3

+------------+------------------------+------------+
| 账单日期   | 文件名                 | 大小       |
+------------+------------------------+------------+
| 2022-07-24 | 2022-07-24_ALL.csv     | 1.58 MB    |
| 2022-07-29 | 2022-07-29_ALL.csv     | 772.46 KB  |
| 2022-07-30 | 2022-07-30_ALL.csv     | 1.63 MB    |
+------------+------------------------+------------+

确认导入以上文件？ (yes/no) [yes]: yes

✅ [新建] 2022-07-24 - 2022-07-24_ALL.csv
✅ [新建] 2022-07-29 - 2022-07-29_ALL.csv
✅ [新建] 2022-07-30 - 2022-07-30_ALL.csv

========== 导入完成 ==========
总计：3 个文件
新建：3 条记录
更新：0 条记录
跳过：0 条记录
失败：0 条记录
==============================
```

### 定时任务

扩展包已自动注册定时任务，无需额外配置。共 4 个任务：

| 任务 | 频率 | 说明 |
|------|------|------|
| 自动下载 | 每天 09:00 | 下载昨日所有启用渠道的账单 |
| 自动导入 | 每天 09:30 | 导入昨日已下载的账单到数据库 |
| 下载失败重试 | 每 2 小时 | 重试近 3 天内下载状态为 failed 的记录 |
| 导入失败重试 | 每 2 小时 | 重试近 3 天内导入状态为 failed 的记录 |

重试任务的追溯天数和间隔可通过配置文件调整：

```php
// config/payment-bill.php
'schedules' => [
    'download' => [
        'retry' => [
            'every_hours' => 2, // 重试间隔
            'days' => 7,        // 改为追溯 7 天
        ],
    ],
    'import' => [
        'retry' => [
            'every_hours' => 2,
            'days' => 7,
        ],
    ],
],
```

确保在 `crontab` 中添加 Laravel 调度器：

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 服务器配置

如需批量上传大量历史账单文件，请参考[服务器配置指南](docs/服务器配置指南.md)调整 PHP 和 Web 服务器配置。

**重要配置项：**
- `max_file_uploads` - 同时上传的文件数量限制（默认 20）
- `upload_max_filesize` - 单个文件大小限制
- `post_max_size` - POST 请求总大小限制
- `max_execution_time` - 脚本执行超时时间

详见：📖 **[服务器配置指南](docs/服务器配置指南.md)**

## 数据库表结构

### payment_bill_channels

支付渠道配置表，存储微信支付、支付宝的渠道配置信息。

### payment_bill_downloads

账单下载记录表，跟踪账单下载和导入状态。

### payment_bill_wechat_bills

微信账单明细表，存储从微信下载的交易账单数据。

**对账相关字段：**
- `reconciliation_status` - 对账状态（pending/matched/mismatched/manual/ignored）
- `business_id` - 关联的业务订单ID
- `reconciled_at` - 对账完成时间
- `reconciliation_amount_diff` - 对账金额差异（业务系统金额 - 账单金额）
- `reconciliation_remark` - 对账备注
- `reconciled_by` - 对账操作人标识

### payment_bill_alipay_bills

支付宝账单明细表，存储从支付宝下载的交易账单数据。

**对账相关字段：**
- `reconciliation_status` - 对账状态（pending/matched/mismatched/manual/ignored）
- `business_id` - 关联的业务订单ID
- `reconciled_at` - 对账完成时间
- `reconciliation_amount_diff` - 对账金额差异（业务系统金额 - 账单金额）
- `reconciliation_remark` - 对账备注
- `reconciled_by` - 对账操作人标识

## 前端开发指南

扩展包提供了完整的前端集成文档，帮助你快速在 Web 应用中集成账单管理功能。

📖 **[查看完整前端开发指南](docs/前端开发指南/README.md)**

### 快速链接

- [历史账单文件导入](docs/前端开发指南/历史账单文件导入.md) - API 接口文档和使用示例
- [Vue 集成示例](docs/前端开发指南/Vue集成示例.md) - Vue 3 + Element Plus 完整代码
- [React 集成示例](docs/前端开发指南/React集成示例.md) - React + Ant Design 完整代码
- [常见问题](docs/前端开发指南/常见问题.md) - FAQ 和问题排查

### 主要功能

- ✅ 完整的 API 接口文档和示例
- ✅ Vue 3 + Element Plus 集成示例
- ✅ React + Ant Design 集成示例
- ✅ TypeScript 类型定义
- ✅ 错误处理和最佳实践
- ✅ 常见问题解答

## 高级用法

### 自定义路由前缀

在配置文件中修改路由前缀：

```php
// config/payment-bill.php
'route_prefix' => 'api/custom-bill',
```

访问路径将变为：`/api/custom-bill/payment-channels`

### 自定义认证守卫

如果你使用其他认证方式，可以修改守卫配置：

```php
// config/payment-bill.php
'guard' => 'api', // 或其他自定义守卫
```

### 自定义队列配置

修改队列连接和队列名称：

```php
// config/payment-bill.php
'queues' => [
    'connection' => 'redis', // 使用 redis 队列
    'wechat_bill_import' => 'high-priority',
    'alipay_bill_import' => 'high-priority',
],
```

### 自定义定时任务配置

修改定时任务执行时间或禁用自动任务：

```php
// config/payment-bill.php
'schedules' => [
    // 完全禁用自动定时任务
    'enabled' => false,

    // 或者单独配置每个任务
    'download' => [
        'enabled' => true,
        'time' => '03:00', // 改为凌晨3点执行
        'timezone' => 'Asia/Shanghai',
        'retry' => [
            'enabled' => true,
            'every_hours' => 4,
            'days' => 7,
        ],
    ],
    'import' => [
        'enabled' => false, // 禁用自动导入，仅手动触发
        'time' => '03:30',
        'timezone' => null,
        'retry' => [
            'enabled' => false,
        ],
    ],
],
```

## 依赖

- PHP ^8.1
- Laravel ^10.0|^11.0|^12.0|^13.0
- yansongda/pay ^3.0
- tucker-eric/eloquentfilter ^3.0
- weijukeji/laravel-enum-options ^1.0 (对账功能)

## 更新日志

请查看 [CHANGELOG](CHANGELOG.md) 了解最近的变更。

## 贡献

欢迎提交 Pull Request 或 Issue。

## 安全漏洞

如果发现安全漏洞，请发送邮件至 ruihuachen@qq.com。

## 许可证

MIT 许可证。详情请查看 [License File](LICENSE.md)。

## 致谢

- [yansongda/pay](https://github.com/yansongda/pay) - 支付 SDK
- [tucker-eric/eloquentfilter](https://github.com/tucker-eric/eloquentfilter) - Eloquent 过滤器
- [weijukeji/laravel-enum-options](https://github.com/weijukeji/laravel-enum-options) - 枚举选项扩展包

## 作者

**Ruihua**
Email: ruihuachen@qq.com
