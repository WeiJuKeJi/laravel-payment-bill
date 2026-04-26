<?php

namespace WeiJuKeJi\PaymentBill\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use JsonSerializable;
use stdClass;

/**
 * 统一的 API 响应构建器，确保各模块输出一致结构。
 */
class ApiResponse
{
    /**
     * 构建成功响应。
     */
    public static function success(
        mixed $data = null,
        string $msg = 'success',
        int $code = 200,
        ?int $status = null
    ): JsonResponse {
        if ($status === null) {
            $status = ($code >= 200 && $code < 300) ? $code : 200;
        }

        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => self::normalizePayload($data),
        ], $status);
    }

    /**
     * 构建带列表的响应。
     */
    public static function listResponse(
        iterable $list,
        int $total,
        string $msg = 'success',
        int $code = 200,
        int $status = 200
    ): JsonResponse {
        if ($list instanceof \Illuminate\Support\Collection) {
            $list = $list->values()->all();
        } elseif ($list instanceof \Traversable) {
            $list = iterator_to_array($list, false);
        } elseif (! is_array($list)) {
            $list = Arr::wrap($list);
        }

        $list = array_values($list);

        return self::success([
            'list' => $list,
            'total' => $total,
        ], $msg, $code, $status);
    }

    /**
     * 构建带列表和汇总统计的响应。
     */
    public static function listResponseWithSummary(
        iterable $list,
        int $total,
        array $summary,
        string $msg = 'success',
        int $code = 200,
        int $status = 200
    ): JsonResponse {
        if ($list instanceof \Illuminate\Support\Collection) {
            $list = $list->values()->all();
        } elseif ($list instanceof \Traversable) {
            $list = iterator_to_array($list, false);
        } elseif (! is_array($list)) {
            $list = Arr::wrap($list);
        }

        $list = array_values($list);

        return self::success([
            'list' => $list,
            'total' => $total,
            'summary' => $summary,
        ], $msg, $code, $status);
    }

    /**
     * 基于分页器构建列表响应，可传入转换器处理单条数据。
     */
    public static function paginate(
        LengthAwarePaginator $paginator,
        ?callable $transform = null,
        string $msg = 'success',
        int $code = 200
    ): JsonResponse {
        $items = $paginator->getCollection();

        if ($transform) {
            $items = $items->map($transform);
        }

        return self::listResponse(
            $items->values()->all(),
            $paginator->total(),
            $msg,
            $code
        );
    }

    /**
     * 构建错误响应。
     */
    public static function error(
        string $msg,
        int $code = 400,
        array $errors = [],
        int $status = 400
    ): JsonResponse {
        $payload = [
            'errors' => $errors,
        ];

        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $payload,
        ], $status);
    }

    /**
     * 将各种类型的载荷转换为统一结构。
     */
    protected static function normalizePayload(mixed $data): mixed
    {
        if ($data instanceof LengthAwarePaginator) {
            return [
                'list' => $data->items(),
                'total' => $data->total(),
            ];
        }

        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        } elseif ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        if (is_array($data)) {
            return $data;
        }

        if (is_object($data)) {
            return $data;
        }

        if (is_null($data)) {
            return new stdClass();
        }

        return ['value' => $data];
    }
}
