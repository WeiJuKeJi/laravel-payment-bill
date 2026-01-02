<?php

namespace WeiJuKeJi\PaymentBill\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 账单下载记录模型，描述下载与导入的全生命周期状态。
 */
class BillDownload extends Model
{
    use Filterable;
    use HasFactory;

    public const DOWNLOAD_STATUS_PENDING = 'pending';

    public const DOWNLOAD_STATUS_PROCESSING = 'processing';

    public const DOWNLOAD_STATUS_COMPLETED = 'completed';

    public const DOWNLOAD_STATUS_FAILED = 'failed';

    public const IMPORT_STATUS_PENDING = 'pending';

    public const IMPORT_STATUS_PROCESSING = 'processing';

    public const IMPORT_STATUS_COMPLETED = 'completed';

    public const IMPORT_STATUS_FAILED = 'failed';

    protected $table = 'payment_bill_downloads';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_channel_id',
        'bill_date',
        'bill_type',
        'file_path',
        'download_status',
        'download_error',
        'download_started_at',
        'download_completed_at',
        'download_retry_count',
        'download_last_attempt_at',
        'bill_summary_transaction_count',
        'bill_summary_settlement_amount',
        'bill_summary_refund_amount',
        'bill_summary_refund_voucher_amount',
        'bill_summary_fee_amount',
        'bill_summary_order_amount',
        'bill_summary_refund_apply_amount',
        'import_total_transaction_count',
        'import_total_new_count',
        'import_total_updated_count',
        'import_total_settlement_amount',
        'import_total_refund_amount',
        'import_total_refund_voucher_amount',
        'import_total_fee_amount',
        'import_total_order_amount',
        'import_total_refund_apply_amount',
        'import_status',
        'import_error',
        'import_started_at',
        'import_completed_at',
        'import_duration',
        'import_retry_count',
        'import_last_attempt_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'bill_date' => 'date',
        'download_started_at' => 'datetime',
        'download_completed_at' => 'datetime',
        'download_retry_count' => 'integer',
        'download_last_attempt_at' => 'datetime',
        'bill_summary_transaction_count' => 'integer',
        'bill_summary_settlement_amount' => 'decimal:2',
        'bill_summary_refund_amount' => 'decimal:2',
        'bill_summary_refund_voucher_amount' => 'decimal:2',
        'bill_summary_fee_amount' => 'decimal:2',
        'bill_summary_order_amount' => 'decimal:2',
        'bill_summary_refund_apply_amount' => 'decimal:2',
        'import_total_transaction_count' => 'integer',
        'import_total_new_count' => 'integer',
        'import_total_updated_count' => 'integer',
        'import_total_settlement_amount' => 'decimal:2',
        'import_total_refund_amount' => 'decimal:2',
        'import_total_refund_voucher_amount' => 'decimal:2',
        'import_total_fee_amount' => 'decimal:2',
        'import_total_order_amount' => 'decimal:2',
        'import_total_refund_apply_amount' => 'decimal:2',
        'import_started_at' => 'datetime',
        'import_completed_at' => 'datetime',
        'import_duration' => 'integer',
        'import_retry_count' => 'integer',
        'import_last_attempt_at' => 'datetime',
    ];

    /**
     * 关联支付渠道，便于获取渠道配置。
     */
    public function paymentChannel(): BelongsTo
    {
        return $this->belongsTo(PaymentChannel::class);
    }

    /**
     * 挂载账单下载记录的筛选器。
     */
    public function modelFilter()
    {
        return $this->provideFilter(\WeiJuKeJi\PaymentBill\ModelFilters\BillDownloadFilter::class);
    }
}
