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
     */
    protected $fillable = [
        'name',
        'channel',
        'mode',
        'is_enabled',
        'remark',
        'alipay_app_id',
        'alipay_app_secret_cert',
        'alipay_app_public_cert_path',
        'alipay_public_cert_path',
        'alipay_root_cert_path',
        'alipay_service_provider_id',
        'alipay_app_auth_token',
        'wechat_mch_id',
        'wechat_mch_secret_key_v2',
        'wechat_mch_secret_key',
        'wechat_mch_secret_cert_path',
        'wechat_mch_public_cert_path',
        'wechat_public_cert_path',
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
