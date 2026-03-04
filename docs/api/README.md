# Payment Bill API 文档索引

本目录包含所有 Payment Bill 扩展包的 API 文档,基于 OpenAPI 3.0.3 规范。

## API 文档列表

| 文档文件 | 描述 | Controller | 路由前缀 |
|---------|------|-----------|---------|
| [支付渠道管理.json](./支付渠道管理.json) | 支付渠道配置管理 | PaymentChannelController | /payment-bill/payment-channels |
| [账单下载管理.json](./账单下载管理.json) | 账单下载任务管理 | BillDownloadController | /payment-bill/bill-downloads |
| [本地账单文件导入.json](./本地账单文件导入.json) | 本地账单文件批量导入 | LocalBillFileImportController | /payment-bill/local-bill-files |
| [微信账单管理.json](./微信账单管理.json) | 微信账单数据查询 | WechatBillController | /payment-bill/wechat-bills |
| [支付宝账单管理.json](./支付宝账单管理.json) | 支付宝账单数据查询 | AlipayBillController | /payment-bill/alipay-bills |

## Controller 详情

### PaymentChannelController

**路由前缀**: `/api/payment-bill/payment-channels`

| 方法 | 路由 | Operation ID | 描述 |
|-----|------|--------------|------|
| GET | / | getPaymentChannels | 获取支付渠道列表 |
| POST | / | createPaymentChannel | 创建支付渠道 |
| GET | /{payment_channel} | getPaymentChannel | 查看支付渠道详情 |
| PUT | /{payment_channel} | updatePaymentChannel | 更新支付渠道 |
| DELETE | /{payment_channel} | deletePaymentChannel | 删除支付渠道 |

### BillDownloadController

**路由前缀**: `/api/payment-bill/bill-downloads`

| 方法 | 路由 | Operation ID | 描述 |
|-----|------|--------------|------|
| GET | / | getBillDownloads | 获取账单下载记录列表 |
| POST | / | createBillDownload | 手动触发账单下载 |
| GET | /{billDownload} | getBillDownload | 查看账单下载详情 |
| GET | /{billDownload}/download | downloadBillFile | 下载账单文件 |

### LocalBillFileImportController

**路由前缀**: `/api/payment-bill/local-bill-files`

| 方法 | 路由 | Operation ID | 描述 |
|-----|------|--------------|------|
| POST | /import | importLocalBillFiles | 批量导入本地账单文件 |

### WechatBillController

**路由前缀**: `/api/payment-bill/wechat-bills`

| 方法 | 路由 | Operation ID | 描述 |
|-----|------|--------------|------|
| GET | / | getWechatBills | 获取微信账单列表 |
| GET | /{wechatBill} | getWechatBill | 查看微信账单详情 |
| POST | /{wechatBill}/reconciliation/mark-as-matched | markWechatBillAsMatched | 标记为已匹配 |
| POST | /{wechatBill}/reconciliation/mark-as-mismatched | markWechatBillAsMismatched | 标记为不匹配 |
| POST | /{wechatBill}/reconciliation/mark-as-manual | markWechatBillAsManual | 标记为人工处理 |
| POST | /{wechatBill}/reconciliation/mark-as-ignored | markWechatBillAsIgnored | 标记为已忽略 |
| POST | /{wechatBill}/reconciliation/mark-as-pending | markWechatBillAsPending | 重置为待对账 |

### AlipayBillController

**路由前缀**: `/api/payment-bill/alipay-bills`

| 方法 | 路由 | Operation ID | 描述 |
|-----|------|--------------|------|
| GET | / | getAlipayBills | 获取支付宝账单列表 |
| GET | /{alipayBill} | getAlipayBill | 查看支付宝账单详情 |
| POST | /{alipayBill}/reconciliation/mark-as-matched | markAlipayBillAsMatched | 标记为已匹配 |
| POST | /{alipayBill}/reconciliation/mark-as-mismatched | markAlipayBillAsMismatched | 标记为不匹配 |
| POST | /{alipayBill}/reconciliation/mark-as-manual | markAlipayBillAsManual | 标记为人工处理 |
| POST | /{alipayBill}/reconciliation/mark-as-ignored | markAlipayBillAsIgnored | 标记为已忽略 |
| POST | /{alipayBill}/reconciliation/mark-as-pending | markAlipayBillAsPending | 重置为待对账 |

## 认证方式

所有 API 接口使用 Bearer Token 认证（Laravel Sanctum）。

请求头示例：
```
Authorization: Bearer {your-token}
Accept: application/json
```

## 响应格式

所有 API 响应遵循统一格式：

### 成功响应
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    // 响应数据
  }
}
```

### 错误响应
```json
{
  "code": 400,
  "msg": "错误描述",
  "data": null
}
```

### 验证错误
```json
{
  "code": 422,
  "msg": "数据校验失败",
  "data": {
    "errors": {
      "field_name": ["错误信息"]
    }
  }
}
```

## 日期格式

所有日期时间字段使用 **ISO 8601** 格式：
- 日期时间: `2025-11-14T10:30:00.000Z`
- 日期: `2025-11-14`

## 分页参数

列表接口支持统一的分页参数：
- `page`: 页码（默认：1）
- `per_page`: 每页数量（默认值因接口而异，一般为 15-20）

## 对账功能

### 对账状态

对账功能使用 [laravel-enum-options](https://github.com/weijukeji/laravel-enum-options) 扩展包，所有状态字段返回包含 `{value, label, color, icon}` 的对象。

**对账状态枚举值：**
- `pending` - 待对账
- `matched` - 已匹配
- `mismatched` - 不匹配
- `manual` - 人工处理
- `ignored` - 已忽略

### 对账接口参数说明

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

### 对账接口示例

**请求示例：**
```bash
# 标记为已忽略（最常用）
POST /api/payment-bill/wechat-bills/123/reconciliation/mark-as-ignored
Authorization: Bearer {token}
Content-Type: application/json

{
  "remark": "测试订单，已忽略"
}
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
      "reconciled_at": "2026-01-08T14:30:00.000Z",
      "amount_diff": "0.00",
      "remark": "测试订单，已忽略",
      "reconciled_by": "user_1"
    }
  }
}
```

## 更新日志

- 2026-01-08: 添加对账功能接口（mark-as-matched, mark-as-mismatched, mark-as-manual, mark-as-ignored, mark-as-pending）
- 2026-01-03: 修正所有路由前缀为 `/payment-bill/`，修正所有 tags 为 `PaymentBill/*`
- 2025-11-14: 初始版本
