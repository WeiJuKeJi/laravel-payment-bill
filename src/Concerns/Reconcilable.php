<?php

namespace WeiJuKeJi\PaymentBill\Concerns;

use Illuminate\Database\Eloquent\Model;
use WeiJuKeJi\PaymentBill\Enums\ReconciliationStatusEnum;

trait Reconcilable
{
    /**
     * 标记为已匹配
     */
    public function markAsMatched(
        Model|int $business,
        float $amountDiff = 0.00,
        ?string $reconciledBy = 'system',
        ?string $remark = null
    ): bool {
        [$businessType, $businessId] = $this->resolveBusinessReference($business);

        return $this->updateReconciliation(
            status: ReconciliationStatusEnum::MATCHED->value,
            businessType: $businessType,
            businessId: $businessId,
            amountDiff: $amountDiff,
            reconciledBy: $reconciledBy,
            remark: $remark
        );
    }

    /**
     * 标记为不匹配
     */
    public function markAsMismatched(
        Model|int $business,
        float $amountDiff,
        string $remark,
        ?string $reconciledBy = 'system'
    ): bool {
        [$businessType, $businessId] = $this->resolveBusinessReference($business);

        return $this->updateReconciliation(
            status: ReconciliationStatusEnum::MISMATCH->value,
            businessType: $businessType,
            businessId: $businessId,
            amountDiff: $amountDiff,
            reconciledBy: $reconciledBy,
            remark: $remark
        );
    }

    /**
     * 标记为人工处理
     */
    public function markAsManual(
        string $remark,
        string $reconciledBy,
        Model|int|null $business = null
    ): bool {
        [$businessType, $businessId] = $business !== null
            ? $this->resolveBusinessReference($business)
            : [null, null];

        return $this->updateReconciliation(
            status: ReconciliationStatusEnum::MANUAL->value,
            businessType: $businessType,
            businessId: $businessId,
            reconciledBy: $reconciledBy,
            remark: $remark
        );
    }

    /**
     * 标记为已忽略
     */
    public function markAsIgnored(
        string $remark,
        string $reconciledBy
    ): bool {
        return $this->updateReconciliation(
            status: ReconciliationStatusEnum::IGNORED->value,
            reconciledBy: $reconciledBy,
            remark: $remark
        );
    }

    /**
     * 重置为待对账
     */
    public function markAsPending(): bool
    {
        return $this->update([
            'reconciliation_status' => ReconciliationStatusEnum::PENDING->value,
            'business_type' => null,
            'business_id' => null,
            'reconciled_at' => null,
            'reconciliation_amount_diff' => 0,
            'reconciliation_remark' => null,
            'reconciled_by' => null,
        ]);
    }

    /**
     * 解析业务引用（支持 Model 实例或 ID）
     */
    protected function resolveBusinessReference(Model|int $business): array
    {
        if ($business instanceof Model) {
            return [get_class($business), $business->getKey()];
        }

        return [null, $business];
    }

    /**
     * 更新对账信息
     */
    protected function updateReconciliation(
        string $status,
        ?string $businessType = null,
        ?int $businessId = null,
        float $amountDiff = 0.00,
        ?string $reconciledBy = null,
        ?string $remark = null
    ): bool {
        return $this->update([
            'reconciliation_status' => $status,
            'business_type' => $businessType,
            'business_id' => $businessId,
            'reconciled_at' => now(),
            'reconciliation_amount_diff' => $amountDiff,
            'reconciliation_remark' => $remark,
            'reconciled_by' => $reconciledBy,
        ]);
    }

    /**
     * 获取对账状态枚举对象
     */
    public function getReconciliationStatusEnum(): ?ReconciliationStatusEnum
    {
        $enumClass = config(
            'payment-bill.enums.reconciliation_status',
            ReconciliationStatusEnum::class
        );

        return $enumClass::tryFrom($this->reconciliation_status);
    }

    /**
     * 判断是否已对账（不包括待对账状态）
     */
    public function isReconciled(): bool
    {
        return $this->reconciliation_status !== ReconciliationStatusEnum::PENDING->value;
    }

    /**
     * 判断是否匹配成功
     */
    public function isMatched(): bool
    {
        return $this->reconciliation_status === ReconciliationStatusEnum::MATCHED->value;
    }

    /**
     * 判断是否有差异
     */
    public function isMismatched(): bool
    {
        return $this->reconciliation_status === ReconciliationStatusEnum::MISMATCH->value;
    }

    /**
     * 判断是否被忽略
     */
    public function isIgnored(): bool
    {
        return $this->reconciliation_status === ReconciliationStatusEnum::IGNORED->value;
    }
}
