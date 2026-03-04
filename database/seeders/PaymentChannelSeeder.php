<?php

namespace WeiJuKeJi\PaymentBill\Database\Seeders;

use Illuminate\Database\Seeder;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;

class PaymentChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'id'                          => 1,
                'name'                        => '润泽微信-测试',
                'channel'                     => 'wechat',
                'mode'                        => 'service',
                'remark'                      => '润泽测试',
                'is_enabled'                  => true,
                'wechat_mch_id'               => '1609502409',
                'wechat_mch_secret_key_v2'    => 'qtdP8WFwlH2Nc0sBQVz9gTSiX1fjIrAu',
                'wechat_mch_secret_key'       => 'TWOKcZhl1wjUk0fsQ9gx4i5pqRNaHBEC',
                'wechat_mch_secret_cert_path' => 'payment/certs/wechat/1/apiclient_key.pem',
                'wechat_mch_public_cert_path' => 'payment/certs/wechat/1/apiclient_cert.pem',
                'wechat_public_cert_path'     => 'payment/certs/wechat/1/platform_cert.pem',
                'wechat_mp_app_id'            => 'wx0d2e4b3f06a5eddf',
                'wechat_sub_mch_id'           => '1726531769',
            ],
        ];

        foreach ($channels as $data) {
            PaymentChannel::updateOrCreate(['id' => $data['id']], $data);
        }
    }
}
