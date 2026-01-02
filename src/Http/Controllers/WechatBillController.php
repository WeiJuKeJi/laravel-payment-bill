<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use WeiJuKeJi\PaymentBill\Http\Requests\WechatBillFilterRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\WechatBillResource;
use WeiJuKeJi\PaymentBill\Models\WechatBill;

class WechatBillController extends Controller
{
    public function index(WechatBillFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $order = Arr::pull($filters, 'order', 'desc');
        $withChannel = (bool) Arr::pull($filters, 'with_channel', false);
        $perPage = $this->resolvePerPage($filters, 20, 500);

        $direction = $order === 'asc' ? 'asc' : 'desc';

        $query = WechatBill::query()->filter($filters);

        if ($withChannel) {
            $query->with('paymentChannel');
        }

        $query->orderBy('trade_time', $direction)
            ->orderBy('id', $direction);

        $paginator = $query->paginate($perPage);

        return $this->respondWithPagination($paginator, WechatBillResource::class);
    }

    public function show(Request $request, WechatBill $wechatBill): JsonResponse
    {
        if ($request->boolean('with_channel')) {
            $wechatBill->loadMissing('paymentChannel');
        }

        return $this->respondWithResource($wechatBill, WechatBillResource::class);
    }
}
