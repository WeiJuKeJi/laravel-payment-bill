<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 为控制器提供统一的 API 响应方法。
 *
 * 优先使用主项目的 ApiResponse（如果存在），否则使用包内的实现。
 */
trait RespondsWithApi
{
    /**
     * 获取 ApiResponse 类名
     */
    protected function getApiResponseClass(): string
    {
        // 优先使用主项目的 ApiResponse
        if (class_exists(\App\Support\ApiResponse::class)) {
            return \App\Support\ApiResponse::class;
        }

        return \WeiJuKeJi\PaymentBill\Support\ApiResponse::class;
    }

    protected function success(
        mixed $data = null,
        string $msg = 'success',
        int $code = 200,
        int $status = 200
    ): JsonResponse {
        return $this->getApiResponseClass()::success($data, $msg, $code, $status);
    }

    protected function respondWithList(
        iterable $list,
        int $total,
        string $msg = 'success',
        int $code = 200,
        int $status = 200
    ): JsonResponse {
        return $this->getApiResponseClass()::listResponse($list, $total, $msg, $code, $status);
    }

    protected function respondWithPagination(
        LengthAwarePaginatorContract $paginator,
        ?string $resourceClass = null,
        string $msg = 'success',
        int $code = 200
    ): JsonResponse {
        $collection = $paginator->getCollection();

        if ($resourceClass) {
            /** @var JsonResource $resourceClass */
            $collection = $collection->map(fn ($item) => $resourceClass::make($item)->toArray(request()))->all();
        } else {
            $collection = $collection->values()->map(function ($item) {
                if ($item instanceof JsonResource) {
                    return $item->toArray(request());
                }

                if ($item instanceof Arrayable) {
                    return $item->toArray();
                }

                if ($item instanceof Model) {
                    return $item->toArray();
                }

                return $item;
            })->all();
        }

        return $this->getApiResponseClass()::listResponse($collection, $paginator->total(), $msg, $code);
    }

    protected function respondWithResource(
        Model $model,
        string $resourceClass,
        string $msg = 'success',
        int $code = 200
    ): JsonResponse {
        /** @var JsonResource $resourceClass */
        $payload = $resourceClass::make($model)->toArray(request());

        return $this->success($payload, $msg, $code);
    }

    protected function error(
        string $msg,
        int $code = 400,
        array $errors = [],
        int $status = 400
    ): JsonResponse {
        return $this->getApiResponseClass()::error($msg, $code, $errors, $status);
    }

    /**
     * 统一解析分页参数，便于在控制器间复用。
     */
    protected function resolvePerPage(array &$filters, int $default = 20, int $max = 100): int
    {
        $perPage = $filters['per_page'] ?? $default;
        $perPage = (int) $perPage;

        unset($filters['per_page'], $filters['page']);

        return max(1, min($max, $perPage));
    }
}
