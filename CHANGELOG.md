# Changelog

All notable changes to `laravel-payment-bill` will be documented in this file.

## [Unreleased]

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
