# Changelog

All notable changes to `laravel-payment-bill` will be documented in this file.

## [Unreleased]

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
- 支持 Laravel 10.x、11.x、12.x
