<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use WeiJuKeJi\PaymentBill\Http\Requests\AlipayBillFilterRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\AlipayBillResource;
use WeiJuKeJi\PaymentBill\Models\AlipayBill;

class AlipayBillController extends Controller
{
    public function index(AlipayBillFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $order = Arr::pull($filters, 'order', 'desc');
        $withChannel = (bool) Arr::pull($filters, 'with_channel', false);
        $perPage = $this->resolvePerPage($filters, 20, 500);

        $direction = $order === 'asc' ? 'asc' : 'desc';

        $query = AlipayBill::query()->filter($filters);

        if ($withChannel) {
            $query->with('paymentChannel');
        }

        $query->orderBy('bill_date', $direction)
            ->orderBy('created_time', $direction)
            ->orderBy('id', $direction);

        $paginator = $query->paginate($perPage);

        return $this->respondWithPagination($paginator, AlipayBillResource::class);
    }

    public function show(Request $request, AlipayBill $alipayBill): JsonResponse
    {
        if ($request->boolean('with_channel')) {
            $alipayBill->loadMissing('paymentChannel');
        }

        return $this->respondWithResource($alipayBill, AlipayBillResource::class);
    }
}
