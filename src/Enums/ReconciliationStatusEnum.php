<?php

namespace WeiJuKeJi\PaymentBill\Enums;

use WeiJuKeJi\EnumOptions\Traits\EnumOptions;

enum ReconciliationStatusEnum: string
{
    use EnumOptions;

    case PENDING = 'pending';
    case MATCHED = 'matched';
    case MISMATCH = 'mismatched';
    case MANUAL = 'manual';
    case IGNORED = 'ignored';

    /**
     * 获取状态标签
     */
    public function label(): string
    {
        return $this->trans($this->value, match ($this) {
            self::PENDING => '待对账',
            self::MATCHED => '已匹配',
            self::MISMATCH => '不匹配',
            self::MANUAL => '人工处理',
            self::IGNORED => '已忽略',
        });
    }

    /**
     * 获取状态颜色
     */
    public function color(): string
    {
        // 优先从配置读取
        $configColor = config("enum-options.color_overrides.reconciliation_status.{$this->value}");
        if ($configColor) {
            return $configColor;
        }

        return match ($this) {
            self::PENDING => 'default',    // 灰色 - 待处理
            self::MATCHED => 'success',    // 绿色 - 成功匹配
            self::MISMATCH => 'danger',    // 红色 - 有问题
            self::MANUAL => 'warning',     // 橙色 - 需要关注
            self::IGNORED => 'info',       // 蓝色 - 已忽略
        };
    }

    /**
     * 获取状态图标（可选）
     */
    public function icon(): ?string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::MATCHED => 'check-circle',
            self::MISMATCH => 'x-circle',
            self::MANUAL => 'user',
            self::IGNORED => 'eye-slash',
            default => null,
        };
    }
}
