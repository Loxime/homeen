<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\JsonInput;
use App\Service\UserPasswordService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile')]
final readonly class ProfileController
{
    private const MIN_PASSWORD_LENGTH = 12;
    private const MAX_PASSWORD_LENGTH = 72;

    public function __construct(
        private UserRepository $users,
        private UserPasswordService $passwords,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_profile_show',
        methods: ['GET'],
    )]
    public function show(
        Request $request,
    ): JsonResponse {
        $userId = $this->userId($request);

        $profile = $this->users
            ->getProfile($userId);

        if ($profile === null) {
            return new JsonResponse(
                ['error' => 'User not found.'],
                404,
            );
        }

        return new JsonResponse($profile);
    }

    #[Route(
        '/emails',
        name: 'api_profile_email_add',
        methods: ['POST'],
    )]
    public function addEmail(
        Request $request,
    ): JsonResponse {
        $data = $this->input->read($request);

        $email = trim(
            (string) ($data['email'] ?? '')
        );

        if (
            $email === ''
            || strlen($email) > 254
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Invalid email address.',
                    'code' =>
                        'INVALID_EMAIL',
                ],
                422,
            );
        }

        if ($this->users->emailExists($email)) {
            return new JsonResponse(
                [
                    'error' =>
                        'This email address is already linked to a Homeen account.',
                    'code' =>
                        'EMAIL_ALREADY_USED',
                ],
                409,
            );
        }

        try {
            $created = $this->users->addEmail(
                $this->userId($request),
                $email,
            );
        } catch (
            UniqueConstraintViolationException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'This email address is already linked to a Homeen account.',
                    'code' =>
                        'EMAIL_ALREADY_USED',
                ],
                409,
            );
        }

        return new JsonResponse(
            $created,
            201,
        );
    }

    #[Route(
        '/emails/{id<\d+>}',
        name: 'api_profile_email_delete',
        methods: ['DELETE'],
    )]
    public function deleteEmail(
        int $id,
        Request $request,
    ): JsonResponse {
        if (
            !$this->users->deleteSecondaryEmail(
                $this->userId($request),
                $id,
            )
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Email address not found or primary email cannot be deleted.',
                    'code' =>
                        'EMAIL_NOT_DELETABLE',
                ],
                409,
            );
        }

        return new JsonResponse(null, 204);
    }

    #[Route(
        '/password',
        name: 'api_profile_password_change',
        methods: ['POST'],
    )]
    public function changePassword(
        Request $request,
    ): JsonResponse {
        $userId = $this->userId($request);
        $data = $this->input->read($request);

        $currentPassword = (string) (
            $data['currentPassword'] ?? ''
        );

        $password = (string) (
            $data['password'] ?? ''
        );

        $confirmation = (string) (
            $data['confirmation'] ?? ''
        );

        $currentHash = $this->users
            ->findPasswordHash($userId);

        if (
            $currentHash === null
            || !$this->passwords->verify(
                $currentPassword,
                $currentHash,
            )
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Current password is incorrect.',
                    'code' =>
                        'INVALID_CURRENT_PASSWORD',
                ],
                403,
            );
        }

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

        if (
            $this->passwords->verify(
                $password,
                $currentHash,
            )
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Choose a password different from your current password.',
                    'code' =>
                        'PASSWORD_UNCHANGED',
                ],
                422,
            );
        }

        $this->users->changePassword(
            $userId,
            $this->passwords->hash(
                $password
            ),
        );

        return new JsonResponse([
            'passwordChanged' => true,
        ]);
    }

    #[Route(
        '/notifications',
        name: 'api_profile_notifications',
        methods: ['PATCH'],
    )]
    public function notifications(
        Request $request,
    ): JsonResponse {
        $data = $this->input->read($request);

        if (
            !array_key_exists(
                'soundEnabled',
                $data,
            )
            || !is_bool(
                $data['soundEnabled']
            )
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'soundEnabled must be a boolean.',
                    'code' =>
                        'INVALID_NOTIFICATION_SETTING',
                ],
                422,
            );
        }

        $enabled =
            $data['soundEnabled'];

        $this->users
            ->setNotificationSoundEnabled(
                $this->userId($request),
                $enabled,
            );

        return new JsonResponse([
            'soundEnabled' => $enabled,
        ]);
    }

    private function userId(
        Request $request,
    ): int {
        $userId = (int) $request
            ->getSession()
            ->get(
                'homeen_user_id',
                0,
            );

        if ($userId <= 0) {
            throw new \LogicException(
                'Authenticated user missing from session.'
            );
        }

        return $userId;
    }
}
