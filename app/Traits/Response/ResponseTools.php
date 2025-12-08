<?php

namespace ec5\Traits\Response;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait ResponseTools
{
    /** @noinspection PhpCastIsUnnecessaryInspection */
    public function getMeta(LengthAwarePaginator $entriesPaginator, $newest = null, $oldest = null): array
    {
        return [
            'total' => $entriesPaginator->total(),
            //imp: cast to int for consistency:
            //imp: sometimes the paginator gives a string back, go figure
            /** @noinspection */
            'per_page' => (int)$entriesPaginator->perPage(),
            'current_page' => $entriesPaginator->currentPage(),
            'last_page' => $entriesPaginator->lastPage(),
            // todo - duplication here, remove when dataviewer is rewritten
            'from' => $entriesPaginator->currentPage(),
            'to' => $entriesPaginator->lastPage(),
            'newest' => $newest,
            'oldest' => $oldest
        ];
    }

    /**
     * @param LengthAwarePaginator $entriesPaginator
     * @return array
     */
    protected function getLinks(LengthAwarePaginator $entriesPaginator): array
    {
        return [
            'self' => $entriesPaginator->url($entriesPaginator->currentPage()),
            'first' => $entriesPaginator->url(1),
            'prev' => $entriesPaginator->previousPageUrl(),
            'next' => $entriesPaginator->nextPageUrl(),
            'last' => $entriesPaginator->url($entriesPaginator->lastPage())
        ];
    }
}
