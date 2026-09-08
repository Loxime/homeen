<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UsageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/access')]
final readonly class AccessController
{
    public function __construct(
        private UsageRepository $usage,
    ) {
    }

    #[Route(
        '/status',
        name: 'api_access_status',
        methods: ['GET'],
    )]
    public function status(
        Request $request,
    ): JsonResponse {
        $session =
            $request->getSession();

        $userId =
            (int) $session->get(
                'homeen_user_id',
                0,
            );

        $userAuthenticated =
            $userId > 0;

        $mustChangePassword =
            $userAuthenticated
            && $session->get(
                'homeen_password_change_required'
            ) === true;

        return new JsonResponse([
            'userAuthenticated' =>
                $userAuthenticated,

            'mustChangePassword' =>
                $mustChangePassword,

            'authenticated' =>
                $userAuthenticated
                && !$mustChangePassword,

            'email' =>
                $userAuthenticated
                    ? (string) $session->get(
                        'homeen_user_email',
                        '',
                    )
                    : null,

            /*
             * The login form needs a CSRF token
             * even before a user is authenticated.
             */
            'csrfToken' =>
                $this->csrfToken(
                    $session
                ),
        ]);
    }

    #[Route(
        '/logout',
        name: 'api_access_logout',
        methods: ['POST'],
    )]
    public function logout(
        Request $request,
    ): JsonResponse {
        $this->usage
            ->stopAllOpen();

        $request
            ->getSession()
            ->invalidate();

        return new JsonResponse([
            'userAuthenticated' => false,
            'authenticated' => false,
        ]);
    }

    private function csrfToken(
        SessionInterface $session,
    ): string {
        $token =
            (string) $session->get(
                'homeen_csrf',
                '',
            );

        if ($token === '') {
            $token =
                bin2hex(
                    random_bytes(32)
                );

            $session->set(
                'homeen_csrf',
                $token,
            );
        }

        return $token;
    }
}
