<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\JsonInput;
use App\Service\UserPasswordService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final readonly class AuthController
{
    private const MIN_PASSWORD_LENGTH = 12;
    private const MAX_PASSWORD_LENGTH = 72;

    public function __construct(
        private UserRepository $users,
        private UserPasswordService $passwords,
        private JsonInput $input,
        #[Autowire(service: 'limiter.user_login')]
        private RateLimiterFactory $userLoginLimiter,
    ) {
    }

    #[Route(
        '/login',
        name: 'api_auth_login',
        methods: ['POST'],
    )]
    public function login(
        Request $request,
    ): JsonResponse {
        $limit = $this
            ->userLoginLimiter
            ->create(
                $request->getClientIp()
                ?? 'unknown'
            )
            ->consume(1);

        if (!$limit->isAccepted()) {
            return new JsonResponse(
                [
                    'error' =>
                        'Too many login attempts. Try again later.',
                    'code' =>
                        'USER_LOGIN_RATE_LIMITED',
                ],
                429,
            );
        }

        $data = $this->input->read($request);

        $email = trim(
            (string) ($data['email'] ?? '')
        );

        $password = (string) (
            $data['password'] ?? ''
        );

        $user = $this
            ->users
            ->findForAuthentication($email);

        if (
            $user === null
            || $password === ''
            || !$this->passwords->verify(
                $password,
                $user['passwordHash'],
            )
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Invalid email or password.',
                    'code' =>
                        'INVALID_CREDENTIALS',
                ],
                401,
            );
        }

        if ($user['mustChangePassword']) {
            if (
                $user[
                    'temporaryPasswordConsumedAt'
                ] !== null
            ) {
                return new JsonResponse(
                    [
                        'error' =>
                            'This temporary password has already been used.',
                        'code' =>
                            'TEMPORARY_PASSWORD_ALREADY_USED',
                    ],
                    403,
                );
            }

            if (
                !$this->users
                    ->consumeTemporaryPassword(
                        $user['id']
                    )
            ) {
                return new JsonResponse(
                    [
                        'error' =>
                            'This temporary password has already been used.',
                        'code' =>
                            'TEMPORARY_PASSWORD_ALREADY_USED',
                    ],
                    403,
                );
            }
        } else {
            $this->users->markSuccessfulLogin(
                $user['id']
            );
        }

        $session = $request->getSession();

        $session->migrate(true);

        $session->set(
            'homeen_user_id',
            $user['id'],
        );

        $session->set(
            'homeen_user_email',
            $user['email'],
        );

        $session->set(
            'homeen_password_change_required',
            $user['mustChangePassword'],
        );

        return new JsonResponse([
            'userAuthenticated' => true,
            'mustChangePassword' =>
                $user['mustChangePassword'],
            'authenticated' =>
                !$user['mustChangePassword'],
            'email' => $user['email'],
        ]);
    }

    #[Route(
        '/change-temporary-password',
        name: 'api_auth_change_temporary_password',
        methods: ['POST'],
    )]
    public function changeTemporaryPassword(
        Request $request,
    ): JsonResponse {
        $session = $request->getSession();

        $userId = (int) $session->get(
            'homeen_user_id',
            0,
        );

        if (
            $userId <= 0
            || $session->get(
                'homeen_password_change_required'
            ) !== true
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'No password change is pending.',
                    'code' =>
                        'PASSWORD_CHANGE_NOT_REQUIRED',
                ],
                409,
            );
        }

        $data = $this->input->read($request);

        $password = (string) (
            $data['password'] ?? ''
        );

        $confirmation = (string) (
            $data['confirmation'] ?? ''
        );

        if ($password !== $confirmation) {
            return new JsonResponse(
                [
                    'error' =>
                        'Password confirmation does not match.',
                    'code' =>
                        'PASSWORD_CONFIRMATION_MISMATCH',
                ],
                422,
            );
        }

        $length = strlen($password);

        if (
            $length < self::MIN_PASSWORD_LENGTH
            || $length > self::MAX_PASSWORD_LENGTH
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        sprintf(
                            'Password must contain between %d and %d characters.',
                            self::MIN_PASSWORD_LENGTH,
                            self::MAX_PASSWORD_LENGTH,
                        ),
                    'code' =>
                        'INVALID_PASSWORD_LENGTH',
                ],
                422,
            );
        }

        $currentHash =
            $this->users
                ->findPasswordHash($userId);

        if (
            $currentHash !== null
            && $this->passwords->verify(
                $password,
                $currentHash,
            )
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Choose a password different from the temporary password.',
                    'code' =>
                        'PASSWORD_UNCHANGED',
                ],
                422,
            );
        }

        $this->users
            ->completeInitialPasswordChange(
                $userId,
                $this->passwords->hash(
                    $password
                ),
            );

        $session->set(
            'homeen_password_change_required',
            false,
        );

        $session->set(
            'homeen_csrf',
            bin2hex(random_bytes(32)),
        );

        return new JsonResponse([
            'userAuthenticated' => true,
            'mustChangePassword' => false,
            'authenticated' => true,
            'email' => (string) $session->get(
                'homeen_user_email',
                '',
            ),
            'csrfToken' => (string) $session->get(
                'homeen_csrf',
            ),
        ]);
    }
}
