# Finance API 文档索引

本目录包含 Finance 模块的所有 API 接口文档（OpenAPI 3.0.3 格式）。

## 如何使用

1. 使用 Apifox 导入对应的 JSON 文件
2. 根据下表快速定位控制器代码位置
3. 参考控制器方法表了解接口实现细节

---

## API 文档列表

| API 文档 | 对应控制器 | 说明 |
|---------|-----------|------|
| [支付渠道管理.json](./支付渠道管理.json) | PaymentChannelController | 支付渠道配置管理,包括微信和支付宝证书上传 |
| [支付宝账单管理.json](./支付宝账单管理.json) | AlipayBillController | 支付宝账单流水查询 |
| [微信账单管理.json](./微信账单管理.json) | WechatBillController | 微信支付账单流水查询 |
| [账单下载管理.json](./账单下载管理.json) | BillDownloadController | 账单下载任务管理 |

---

## 控制器详情

### PaymentChannelController

**文件路径**: `app/Http/Controllers/PaymentChannelController.php`
**API 文档**: [支付渠道管理.json](./支付渠道管理.json)

| 控制器方法 | HTTP 方法 | 路由 | 接口说明 | operationId |
|-----------|----------|------|---------|-------------|
| index() | GET | /finances/payment-channels | 获取支付渠道列表 | getPaymentChannels |
| store() | POST | /finances/payment-channels | 创建支付渠道 | createPaymentChannel |
| show() | GET | /finances/payment-channels/{payment_channel} | 查看支付渠道详情 | getPaymentChannel |
| update() | PUT | /finances/payment-channels/{payment_channel} | 更新支付渠道 | updatePaymentChannel |
| destroy() | DELETE | /finances/payment-channels/{payment_channel} | 删除支付渠道 | deletePaymentChannel |

**特殊功能**:
- 支持证书文件上传（multipart/form-data）
- 自动管理证书文件存储和删除
- 支持微信支付和支付宝两种渠道类型

---

### AlipayBillController

**文件路径**: `app/Http/Controllers/AlipayBillController.php`
**API 文档**: [支付宝账单管理.json](./支付宝账单管理.json)

| 控制器方法 | HTTP 方法 | 路由 | 接口说明 | operationId |
|-----------|----------|------|---------|-------------|
| index() | GET | /finances/alipay-bills | 获取支付宝账单列表 | getAlipayBills |
| show() | GET | /finances/alipay-bills/{alipayBill} | 查看支付宝账单详情 | getAlipayBill |

**筛选功能**:
- 按账单日期范围筛选
- 按支付宝商户号、交易号筛选
- 按业务类型、收支类型筛选
- 支持关联查询支付渠道信息

---

### WechatBillController

**文件路径**: `app/Http/Controllers/WechatBillController.php`
**API 文档**: [微信账单管理.json](./微信账单管理.json)

| 控制器方法 | HTTP 方法 | 路由 | 接口说明 | operationId |
|-----------|----------|------|---------|-------------|
| index() | GET | /finances/wechat-bills | 获取微信账单列表 | getWechatBills |
| show() | GET | /finances/wechat-bills/{wechatBill} | 查看微信账单详情 | getWechatBill |

**筛选功能**:
- 按交易时间范围筛选
- 按微信商户号、交易号筛选
- 按交易类型、交易状态筛选
- 支持关联查询支付渠道信息

---

### BillDownloadController

**文件路径**: `app/Http/Controllers/BillDownloadController.php`
**API 文档**: [账单下载管理.json](./账单下载管理.json)

| 控制器方法 | HTTP 方法 | 路由 | 接口说明 | operationId |
|-----------|----------|------|---------|-------------|
| index() | GET | /finances/bill-downloads | 获取账单下载记录列表 | getBillDownloads |
| store() | POST | /finances/bill-downloads | 手动触发账单下载 | createBillDownload |
| show() | GET | /finances/bill-downloads/{billDownload} | 查看账单下载详情 | getBillDownload |

**特殊功能**:
- 手动触发账单下载任务（异步执行）
- 查看下载和导入进度
- 支持强制重新下载
- 下载成功后自动导入账单数据

---

## 统一规范

### 请求头

所有接口都需要包含以下请求头:

```
Accept: application/json
Authorization: Bearer {token}
```

### 响应格式

所有接口统一返回格式:

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    // 响应数据
  }
}
```

### 分页响应

列表接口统一返回格式:

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "list": [...],
    "total": 100
  }
}
```

### 错误响应

| HTTP 状态码 | code | 说明 |
|-----------|------|------|
| 401 | 401 | 未授权访问 |
| 404 | 404 | 资源不存在 |
| 422 | 422 | 数据校验失败 |

---

## 枚举值说明

### 支付渠道类型 (channel)

- `wechat` - 微信支付
- `alipay` - 支付宝

### 支付模式 (mode)

- `normal` - 普通模式
- `sandbox` - 沙箱模式
- `service` - 服务商模式

### 账单类型 (bill_type)

- `ALL` - 全量账单
- `SUCCESS` - 成功账单
- `REFUND` - 退款账单

### 下载/导入状态

- `pending` - 待处理
- `processing` - 处理中
- `completed` - 已完成
- `failed` - 失败

### 微信交易类型 (trade_type)

- `NATIVE` - 扫码支付
- `JSAPI` - 公众号支付
- `APP` - APP支付
- `H5` - H5支付
- `MICROPAY` - 付款码支付

### 微信交易状态 (trade_state)

- `SUCCESS` - 支付成功
- `REFUND` - 转入退款
- `NOTPAY` - 未支付
- `CLOSED` - 已关闭
- `REVOKED` - 已撤销
- `USERPAYING` - 用户支付中
- `PAYERROR` - 支付失败

---

## 版本历史

| 版本 | 日期 | 说明 |
|-----|------|------|
| 1.0.0 | 2026-01-01 | 初始版本,包含支付渠道、账单查询、账单下载管理功能 |
