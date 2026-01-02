<?php

namespace WeiJuKeJi\PaymentBill\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PaymentChannel 模型 - 支付渠道配置
 *
 * @property int $id
 * @property string $name
 * @property string $channel
 * @property string|null $mode
 * @property bool $is_enabled
 * @property string|null $remark
 * @property array|null $extra
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class PaymentChannel extends Model
{
    use Filterable;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'payment_bill_channels';

    /**
     * The attributes that are mass assignable.
     *
     * 注意：敏感字段（密钥、私钥、证书路径）不在此列表中，
     * 需要在 Controller 中通过验证后手动设置
     */
    protected $fillable = [
        'name',
        'channel',
        'mode',
        'is_enabled',
        'remark',
        'alipay_app_id',
        'alipay_service_provider_id',
        'wechat_mch_id',
        'wechat_app_id',
        'wechat_mp_app_id',
        'wechat_mini_app_id',
        'wechat_sub_mch_id',
        'wechat_sub_app_id',
        'wechat_sub_mp_app_id',
        'wechat_sub_mini_app_id',
        'extra',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'extra' => 'array',
    ];

    /**
     * 配置过滤器
     */
    public function modelFilter()
    {
        return $this->provideFilter(\WeiJuKeJi\PaymentBill\ModelFilters\PaymentChannelFilter::class);
    }

    /**
     * 访问器 - 渠道文本
     */
    public function getChannelTextAttribute(): string
    {
        return match ($this->channel) {
            'alipay' => '支付宝',
            'wechat' => '微信支付',
            default => $this->channel,
        };
    }

    /**
     * 查询作用域 - 启用状态
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 访问器 - 启用状态文本
     */
    public function getIsEnabledTextAttribute(): string
    {
        return $this->is_enabled ? '启用' : '停用';
    }
}
