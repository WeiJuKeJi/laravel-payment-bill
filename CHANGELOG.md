# Changelog

All notable changes to `laravel-payment-bill` will be documented in this file.

## [Unreleased]

## [1.5.0] - 2026-07-18

### Added
- 新增账单下载状态 `no_statement`，用于表示支付平台明确确认指定日期没有账单文件。
- 新增年度账单下载渠道统计接口 `bill-download-calendar-stats`，一次返回全部支付渠道的状态数量，避免日历切换年份时逐渠道请求。
- 微信、支付宝账单新增无外键的 `resolved_project_id` 项目归属缓存字段与索引。
- 微信账单商品名称、商品信息新增独立 pg_trgm GIN 索引，用户标识新增 B-tree 索引。
- PostgreSQL 安装 zhparser 时可创建中文全文搜索配置和 GIN 索引。

### Changed
- 微信返回 `NO_STATEMENT_EXIST` 时记录为“当日无账单”终态，与正常完成和真正下载失败区分，并保持不进入失败重试。
- `project_id`、`has_project` 筛选在缓存字段可用时改为单表索引查询；未迁移环境继续回退应用提供的项目解析器。
- 对账 Concern 支持调用应用项目解析器写入或清空项目归属缓存。
- 新增 `PAYMENT_BILL_PROJECT_CACHE_FILTERING_ENABLED` 开关，历史回填核对完成后才启用缓存筛选。
- 微信账单 `keywords` 对交易号、商户订单号、用户标识使用精确匹配，对商品名称和商品信息保留前后模糊匹配，分别命中 B-tree 与 GIN 索引。
- 新增 `PAYMENT_BILL_WECHAT_KEYWORD_SEARCH_DRIVER=zhparser` 中文搜索模式，默认 `ilike` 保持其他环境兼容。

### Fixed
- 微信退款流水优先使用微信退款单号、其次使用商户退款单号作为导入幂等键，避免同一支付订单在同一秒发生多笔退款时互相覆盖。

## [1.4.4] - 2026-06-24

### Fixed
- `payment-bill:import` 默认导入微信和支付宝账单，定时自动导入与失败重试同步使用 `--bill-type=all`，避免启用多渠道时支付宝账单被跳过且调度返回失败。
- 微信返回 `NO_STATEMENT_EXIST` 时按空账单完成处理，避免无账单日期持续进入下载失败重试；该版本仍使用 `completed` 状态表示空账单。

## [1.4.2] - 2026-06-22

### Fixed
- 微信账单导入检测到日期范围文件名时，即使请求未传 `bill_period=month`，也会自动按月账单拆分导入。

## [1.4.1] - 2026-06-22

### Fixed
- 修复日账单文件名解析会误将商户号中的 8 位数字归一化为异常账单日期的问题。
- 日账单导入遇到疑似月账单日期范围文件名时，提示使用 `bill_period=month` 或 `--bill-period=month`。

## [1.4.0] - 2026-06-22

### Added
- 新增微信月账单导入支持，可按 `交易时间` 自动拆分为每日账单文件和下载记录。
- 本地账单导入 API 新增 `bill_period` 参数，支持 `day` 与 `month` 两种账单周期。
- `payment-bill:import-local-files` 命令新增 `--bill-period=month` 选项。

### Changed
- 拆分后的微信日账单缺少汇总行时，导入器会使用明细合计回填账单汇总统计。
- 本地账单上传单文件大小限制从 10MB 调整为 50MB。

## [1.2.0] - 2026-05-05

### Changed
- 新增 Laravel 13 依赖约束支持。
- 扩展 Testbench 与 PHPUnit 开发依赖矩阵以覆盖 Laravel 13。

## [1.1.3] - 2026-04-26

### Added
- 新增账单下载日历接口，支持按支付渠道、年份和账单类型聚合日历数据与月度汇总
- 新增微信账单列表汇总统计，返回支付、退款、结算和手续费合计

### Changed
- 微信账单筛选新增 `transaction_kind` 和 `reconciliation_status`
- 调整账单与微信账单 Filter 的支付渠道方法命名，兼容规范化筛选参数
- 扩展 API 响应结构，支持分页列表同时返回 summary 汇总数据

## [1.1.1] - 2026-03-05

### Added
- 新增 `PaymentChannelSeeder`，提供测试环境种子数据
- 新增本地历史账单文件导入功能（`payment-bill:import-local-files` 命令）
- 新增账单下载、重新下载和重新导入 API
- 新增对账功能，支持标记已匹配、不匹配、人工处理、已忽略、重置等状态
- 新增 `ReconciliationStatusEnum` 枚举及 `Reconcilable` Concern
- 新增前端开发指南文档（Vue/React 集成示例）
- 新增服务器配置指南文档
- `.gitignore` 忽略支付证书目录（`storage/app/payment/`）

### Changed
- 定时任务新增下载失败重试和导入失败重试，各自每 2 小时执行一次，默认追溯近 3 天
- 重试配置结构调整：原顶级 `schedules.retry` 拆分并内嵌至 `schedules.download.retry` 和 `schedules.import.retry`，结构完全对称
- 迁移文件整理：合并冗余版本，删除重复迁移文件

## [1.1.0] - 2025-10-12

### Added
- 账单下载、导入定时任务支持独立时区配置
- 支持定时任务失败重试机制

## [1.0.0] - 2025-01-02

### Added
- 初始版本发布
- 支持微信支付账单下载与导入
- 支持支付宝账单下载与导入
- 支付渠道管理 API
- 账单下载管理 API
- 账单数据查询 API
- 自动定时任务（每天凌晨 2:00 下载，2:30 导入）
- 完整的错误处理与重试机制
- 基于 yansongda/pay 3.x 实现
- 支持 Laravel 10.x、11.x、12.x、13.x
