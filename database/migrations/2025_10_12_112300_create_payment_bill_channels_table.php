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
        Schema::create('payment_bill_channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 120)->comment('支付方式名称');
            $table->string('channel', 32)->comment('支付渠道：wechat、alipay');
            $table->string('mode', 32)->default('normal')->comment('支付模式：normal-直连、service-服务商');
            $table->text('remark')->nullable()->comment('备注');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');

            // 支付宝配置
            $table->string('alipay_app_id', 64)->nullable()->comment('支付宝应用ID');
            $table->text('alipay_app_secret_cert')->nullable()->comment('支付宝应用私钥：字符串或路径');
            $table->string('alipay_app_public_cert_path', 512)->nullable()->comment('支付宝应用公钥证书路径');
            $table->string('alipay_public_cert_path', 512)->nullable()->comment('支付宝公钥证书路径');
            $table->string('alipay_root_cert_path', 512)->nullable()->comment('支付宝根证书路径');
            $table->string('alipay_service_provider_id', 64)->nullable()->comment('支付宝服务商模式下的服务商ID');
            $table->string('alipay_app_auth_token', 255)->nullable()->comment('支付宝第三方应用授权token');

            // 微信配置
            $table->string('wechat_mch_id', 64)->nullable()->comment('微信商户号');
            $table->text('wechat_mch_secret_key_v2')->nullable()->comment('微信v2商户私钥');
            $table->string('wechat_mch_secret_key', 128)->nullable()->comment('微信v3商户密钥');
            $table->string('wechat_mch_secret_cert_path', 512)->nullable()->comment('微信商户私钥证书路径');
            $table->string('wechat_mch_public_cert_path', 512)->nullable()->comment('微信商户公钥证书路径');
            $table->string('wechat_public_cert_path', 512)->nullable()->comment('微信平台公钥证书路径');
            $table->string('wechat_app_id', 64)->nullable()->comment('微信APP的AppID');
            $table->string('wechat_mp_app_id', 64)->nullable()->comment('微信公众号AppID');
            $table->string('wechat_mini_app_id', 64)->nullable()->comment('微信小程序AppID');
            $table->string('wechat_sub_mch_id', 64)->nullable()->comment('微信服务商模式下，子商户号');
            $table->string('wechat_sub_app_id', 64)->nullable()->comment('微信服务商模式下，子应用AppID');
            $table->string('wechat_sub_mp_app_id', 64)->nullable()->comment('微信服务商模式下，子公众号AppID');
            $table->string('wechat_sub_mini_app_id', 64)->nullable()->comment('微信服务商模式下，子小程序AppID');

            $table->jsonb('extra')->nullable()->comment('扩展字段');
            $table->softDeletes();
            $table->timestamps();

            $table->index('channel');
            $table->index('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_bill_channels');
    }
};
