<?php

namespace App\Modules\Shared\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class PaginatedResponse
{
    public static function make(LengthAwarePaginator $page, string $resource, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $resource::collection($page->getCollection())->resolve(),
            'meta' => [
                ...$meta,
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
