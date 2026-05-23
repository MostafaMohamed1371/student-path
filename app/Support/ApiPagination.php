<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ApiPagination
{
    /**
     * @return array<string, int>
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
