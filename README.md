# Laravel Payment Bill

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weijukeji/laravel-payment-bill.svg?style=flat-square)](https://packagist.org/packages/weijukeji/laravel-payment-bill)
[![Total Downloads](https://img.shields.io/packagist/dt/weijukeji/laravel-payment-bill.svg?style=flat-square)](https://packagist.org/packages/weijukeji/laravel-payment-bill)
[![License](https://img.shields.io/packagist/l/weijukeji/laravel-payment-bill.svg?style=flat-square)](https://packagist.org/packages/weijukeji/laravel-payment-bill)

Laravel 支付账单管理扩展包，支持微信支付、支付宝账单自动下载与数据同步。

## 功能特性

- ✅ 支持微信支付、支付宝账单下载
- ✅ 自动解析账单数据并导入数据库
- ✅ 支持多支付渠道管理
- ✅ 定时任务自动下载与导入
- ✅ RESTful API 接口
- ✅ 完整的错误处理与重试机制
- ✅ 基于 yansongda/pay 3.x
- ✅ 支持 Laravel 10.x、11.x、12.x

## 安装

### 通过 Composer 安装

```bash
composer require weijukeji/laravel-payment-bill
```

### 发布配置和迁移文件

```bash
# 发布配置文件
php artisan vendor:publish --tag=payment-bill-config

# 发布迁移文件
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

# 下载账单文件
GET /api/payment-bill/bill-downloads/{id}/download
```

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

### 定时任务

扩展包已自动注册定时任务，无需额外配置。

- **每天凌晨 2:00** - 自动下载前一天的账单
- **每天凌晨 2:30** - 自动导入已下载的账单

确保在 `crontab` 中添加 Laravel 调度器：

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 数据库表结构

### payment_bill_channels

支付渠道配置表，存储微信支付、支付宝的渠道配置信息。

### payment_bill_downloads

账单下载记录表，跟踪账单下载和导入状态。

### payment_bill_wechat_bills

微信账单明细表，存储从微信下载的交易账单数据。

### payment_bill_alipay_bills

支付宝账单明细表，存储从支付宝下载的交易账单数据。

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

## 依赖

- PHP ^8.1
- Laravel ^10.0|^11.0|^12.0
- yansongda/pay ^3.0
- tucker-eric/eloquentfilter ^3.0

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

## 作者

**Ruihua**
Email: ruihuachen@qq.com
