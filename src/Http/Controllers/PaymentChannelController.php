<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use WeiJuKeJi\PaymentBill\Http\Requests\PaymentChannel\PaymentChannelStoreRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\PaymentChannel\PaymentChannelUpdateRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\PaymentChannelResource;
use WeiJuKeJi\PaymentBill\Models\PaymentChannel;
use WeiJuKeJi\PaymentBill\Services\PaymentChannelCertificateManager;

class PaymentChannelController extends Controller
{
    public function __construct(private readonly PaymentChannelCertificateManager $certificateManager) {}

    public function index(Request $request): JsonResponse
    {
        $params = $request->only([
            'channel',
            'mode',
            'is_enabled',
            'keywords',
            'per_page',
            'page',
        ]);
        $perPage = $this->resolvePerPage($params);

        $query = PaymentChannel::filter($params);

        $channels = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->respondWithPagination($channels, PaymentChannelResource::class);
    }

    public function store(PaymentChannelStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $attributes = Arr::except($data, $this->certificateFileKeys());

        $channel = PaymentChannel::create($attributes);

        $updates = $this->certificateManager->storeCertificates($channel, $request->allFiles());
        if (! empty($updates)) {
            $channel->fill($updates)->save();
        }

        return $this->success(
            PaymentChannelResource::make($channel)->toArray($request),
            '创建成功'
        );
    }

    public function show(PaymentChannel $payment_channel): JsonResponse
    {
        return $this->respondWithResource($payment_channel, PaymentChannelResource::class);
    }

    public function update(PaymentChannelUpdateRequest $request, PaymentChannel $payment_channel): JsonResponse
    {
        $attributes = Arr::except($request->validated(), $this->certificateFileKeys());

        $payment_channel->update($attributes);

        $updates = $this->certificateManager->storeCertificates($payment_channel, $request->allFiles());
        if (! empty($updates)) {
            $payment_channel->fill($updates)->save();
        }

        $payment_channel->refresh();

        return $this->success(
            PaymentChannelResource::make($payment_channel)->toArray($request),
            '更新成功'
        );
    }

    public function destroy(PaymentChannel $payment_channel): JsonResponse
    {
        $this->certificateManager->removeCertificates($payment_channel);
        $payment_channel->delete();

        return $this->success(null, '删除成功');
    }

    private function certificateFileKeys(): array
    {
        return [
            'wechat_mch_public_cert_file',
            'wechat_mch_secret_cert_file',
            'wechat_public_cert_file',
            'alipay_app_cert_file',
            'alipay_public_cert_file',
            'alipay_root_cert_file',
        ];
    }
}
