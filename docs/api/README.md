# Payment Bill API 文档索引

本目录包含所有 Payment Bill 扩展包的 API 文档,基于 OpenAPI 3.0.3 规范。

## API 文档列表

| 文档文件 | 描述 | Controller | 路由前缀 |
|---------|------|-----------|---------|
| [支付渠道管理.json](./支付渠道管理.json) | 支付渠道配置管理 | PaymentChannelController | /payment-bill/payment-channels |
| [账单下载管理.json](./账单下载管理.json) | 账单下载任务管理 | BillDownloadController | /payment-bill/bill-downloads |
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

### WechatBillController

**路由前缀**: `/api/payment-bill/wechat-bills`

| 方法 | 路由 | Operation ID | 描述 |
|-----|------|--------------|------|
| GET | / | getWechatBills | 获取微信账单列表 |
| GET | /{wechatBill} | getWechatBill | 查看微信账单详情 |

### AlipayBillController

**路由前缀**: `/api/payment-bill/alipay-bills`

| 方法 | 路由 | Operation ID | 描述 |
|-----|------|--------------|------|
| GET | / | getAlipayBills | 获取支付宝账单列表 |
| GET | /{alipayBill} | getAlipayBill | 查看支付宝账单详情 |

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

## 更新日志

- 2026-01-03: 修正所有路由前缀为 `/payment-bill/`，修正所有 tags 为 `PaymentBill/*`
- 2025-11-14: 初始版本
