<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SearchRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SearchController
{
    public function __construct(
        private SearchRepository $search,
    ) {
    }

    #[Route(
        '/api/search',
        name: 'api_search',
        methods: ['GET'],
    )]
    public function search(
        Request $request,
    ): JsonResponse {
        return new JsonResponse(
            $this->search->search(
                $request->query->getString('q'),
            ),
        );
    }
}
