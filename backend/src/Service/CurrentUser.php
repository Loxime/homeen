<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CurrentUser
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function id(): int
    {
        $id = $this->idOrNull();

        if ($id === null) {
            throw new \LogicException(
                'No authenticated Homeen user is available.'
            );
        }

        return $id;
    }

    public function idOrNull(): ?int
    {
        $request = $this->requestStack
            ->getCurrentRequest();

        if (
            $request === null
            || !$request->hasSession()
        ) {
            return null;
        }

        $id = (int) $request
            ->getSession()
            ->get(
                'homeen_user_id',
                0,
            );

        return $id > 0
            ? $id
            : null;
    }
}
