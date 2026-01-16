<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_bill_downloads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('payment_channel_id')
                ->constrained('payment_bill_channels')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('bill_date')->comment('账单日期');
            $table->string('bill_type', 64)->default('ALL')->comment('账单类型');
            $table->string('file_path')->nullable()->comment('文件路径');
            $table->string('download_status', 32)->default('pending')->comment('下载状态：pending, processing, completed, failed');
            $table->text('download_error')->nullable()->comment('下载错误信息');
            $table->timestamp('download_started_at')->nullable()->comment('下载开始时间');
            $table->timestamp('download_completed_at')->nullable()->comment('下载完成时间');
            $table->unsignedInteger('download_retry_count')->default(0)->comment('下载重试次数');
            $table->timestamp('download_last_attempt_at')->nullable()->comment('下载最后尝试时间');
            $table->unsignedInteger('bill_summary_transaction_count')->nullable()->comment('账单汇总行的交易笔数');
            $table->decimal('bill_summary_settlement_amount', 16, 2)->nullable()->comment('账单汇总行的应结订单金额');
            $table->decimal('bill_summary_refund_amount', 16, 2)->nullable()->comment('账单汇总行的退款金额');
            $table->decimal('bill_summary_refund_voucher_amount', 16, 2)->nullable()->comment('账单汇总行的充值券退款金额');
            $table->decimal('bill_summary_fee_amount', 16, 2)->nullable()->comment('账单汇总行的手续费金额');
            $table->decimal('bill_summary_order_amount', 16, 2)->nullable()->comment('账单汇总行的订单金额');
            $table->decimal('bill_summary_refund_apply_amount', 16, 2)->nullable()->comment('账单汇总行的申请退款金额');
            $table->unsignedInteger('import_total_transaction_count')->nullable()->comment('导入明细合计的交易笔数');
            $table->unsignedInteger('import_total_new_count')->nullable()->comment('导入明细合计的新增记录数');
            $table->unsignedInteger('import_total_updated_count')->nullable()->comment('导入明细合计的更新记录数');
            $table->decimal('import_total_settlement_amount', 16, 2)->nullable()->comment('导入明细合计的应结订单金额');
            $table->decimal('import_total_refund_amount', 16, 2)->nullable()->comment('导入明细合计的退款金额');
            $table->decimal('import_total_refund_voucher_amount', 16, 2)->nullable()->comment('导入明细合计的充值券退款金额');
            $table->decimal('import_total_fee_amount', 16, 2)->nullable()->comment('导入明细合计的手续费金额');
            $table->decimal('import_total_order_amount', 16, 2)->nullable()->comment('导入明细合计的订单金额');
            $table->decimal('import_total_refund_apply_amount', 16, 2)->nullable()->comment('导入明细合计的申请退款金额');
            $table->string('import_status', 32)->default('pending')->comment('导入状态：pending, processing, completed, failed');
            $table->text('import_error')->nullable()->comment('导入错误信息');
            $table->timestamp('import_started_at')->nullable()->comment('导入开始时间');
            $table->timestamp('import_completed_at')->nullable()->comment('导入完成时间');
            $table->unsignedInteger('import_duration')->nullable()->comment('导入耗时（秒）');
            $table->unsignedInteger('import_retry_count')->default(0)->comment('导入重试次数');
            $table->timestamp('import_last_attempt_at')->nullable()->comment('导入最后尝试时间');
            $table->timestamps();

            $table->unique(['payment_channel_id', 'bill_date', 'bill_type'], 'payment_bill_downloads_channel_date_type_unique');
            $table->index('download_status');
            $table->index('import_status');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_bill_downloads');
    }
};
