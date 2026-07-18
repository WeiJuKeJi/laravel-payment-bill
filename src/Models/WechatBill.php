<?php

namespace WeiJuKeJi\PaymentBill\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use WeiJuKeJi\PaymentBill\Concerns\Reconcilable;
use WeiJuKeJi\PaymentBill\ModelFilters\WechatBillFilter;

/**
 * 微信账单明细模型。
 */
class WechatBill extends Model
{
    use Filterable;
    use HasFactory;
    use Reconcilable;

    protected $table = 'payment_bill_wechat_bills';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_channel_id',
        'trade_time',
        'appid',
        'mch_id',
        'special_mch_id',
        'device_id',
        'wechat_transaction_id',
        'out_trade_no',
        'user_open_id',
        'trade_type',
        'trade_state',
        'payment_bank',
        'currency',
        'total_amount',
        'settlement_amount',
        'voucher_amount',
        'wechat_refund_no',
        'out_refund_no',
        'refund_amount',
        'refund_voucher_amount',
        'refund_type',
        'refund_status',
        'goods_name',
        'goods_info',
        'fee_amount',
        'fee_rate',
        'refund_apply_amount',
        // 对账相关字段
        'reconciliation_status',
        'business_type',
        'business_id',
        'resolved_project_id',
        'reconciled_at',
        'reconciliation_amount_diff',
        'reconciliation_remark',
        'reconciled_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'trade_time' => 'datetime',
        'total_amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'voucher_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_voucher_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'refund_apply_amount' => 'decimal:2',
        // 对账相关字段
        'reconciled_at' => 'datetime',
        'resolved_project_id' => 'integer',
        'reconciliation_amount_diff' => 'decimal:2',
    ];

    /**
     * 关联支付渠道。
     */
    public function paymentChannel(): BelongsTo
    {
        return $this->belongsTo(PaymentChannel::class);
    }

    /**
     * 多态关联业务订单。
     */
    public function business(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 挂载筛选器。
     */
    public function modelFilter()
    {
        return $this->provideFilter(WechatBillFilter::class);
    }
}
