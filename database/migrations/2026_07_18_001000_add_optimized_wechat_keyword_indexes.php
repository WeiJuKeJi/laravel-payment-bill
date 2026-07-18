<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_wechat_goods_name_trgm ON payment_bill_wechat_bills USING gin (goods_name gin_trgm_ops)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_wechat_goods_info_trgm ON payment_bill_wechat_bills USING gin (goods_info gin_trgm_ops)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_wechat_user_open_id ON payment_bill_wechat_bills (user_open_id)');
    }

    public function down(): void
    {
        // 兼容迁移不自动删除索引，避免回滚时影响正在使用的查询。
    }
};
