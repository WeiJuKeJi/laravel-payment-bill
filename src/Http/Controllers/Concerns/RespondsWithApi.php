<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Traversable;

/**
 * 为控制器提供统一的 API 响应方法。
 *
 * 优先使用主项目的 ApiResponse（如果存在），否则使用包内的实现。
 * 宿主项目的 ApiResponse 仅需实现 success() 与 error() 两个静态方法；
 * 列表 / 分页 / 汇总等结构化 payload 由本 trait 自行拼装，避免与宿主类签名耦合。
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
        return $this->success([
            'list' => $this->normalizeIterableList($list),
            'total' => $total,
        ], $msg, $code, $status);
    }

    protected function respondWithPagination(
        LengthAwarePaginatorContract $paginator,
        ?string $resourceClass = null,
        string $msg = 'success',
        int $code = 200
    ): JsonResponse {
        return $this->success([
            'list' => $this->transformPaginatorCollection($paginator, $resourceClass),
            'total' => $paginator->total(),
        ], $msg, $code);
    }

    protected function respondWithPaginationAndSummary(
        LengthAwarePaginatorContract $paginator,
        array $summary,
        ?string $resourceClass = null,
        string $msg = 'success',
        int $code = 200
    ): JsonResponse {
        return $this->success([
            'list' => $this->transformPaginatorCollection($paginator, $resourceClass),
            'total' => $paginator->total(),
            'summary' => $summary,
        ], $msg, $code);
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

    /**
     * 将分页器内的集合转换为可直接 JSON 序列化的数组。
     */
    private function transformPaginatorCollection(
        LengthAwarePaginatorContract $paginator,
        ?string $resourceClass
    ): array {
        $collection = $paginator->getCollection();

        if ($resourceClass) {
            /** @var JsonResource $resourceClass */
            return $collection
                ->map(fn ($item) => $resourceClass::make($item)->toArray(request()))
                ->values()
                ->all();
        }

        return $collection->values()->map(function ($item) {
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

    /**
     * 将任意 iterable 规整为数组（与原 ApiResponse::listResponse 行为保持一致）。
     */
    private function normalizeIterableList(iterable $list): array
    {
        if ($list instanceof Collection) {
            return $list->values()->all();
        }

        if ($list instanceof Traversable) {
            return array_values(iterator_to_array($list, false));
        }

        return array_values((array) $list);
    }
}
