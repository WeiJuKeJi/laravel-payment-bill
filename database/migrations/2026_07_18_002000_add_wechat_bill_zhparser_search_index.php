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

        $available = DB::table('pg_available_extensions')->where('name', 'zhparser')->exists();
        if (! $available) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS zhparser');
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_ts_config WHERE cfgname = 'payment_bill_zh') THEN
        CREATE TEXT SEARCH CONFIGURATION payment_bill_zh (PARSER = zhparser);
        ALTER TEXT SEARCH CONFIGURATION payment_bill_zh ADD MAPPING FOR n,v,a,i,e,l WITH simple;
    END IF;
END
$$
SQL);
        DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_wechat_bill_zh_search
ON payment_bill_wechat_bills
USING gin (
    to_tsvector(
        'payment_bill_zh',
        COALESCE(goods_name, '') || ' ' || COALESCE(goods_info, '')
    )
)
SQL);
    }

    public function down(): void
    {
        // 搜索配置和索引可能正被线上查询使用，不在自动回滚中删除。
    }
};
