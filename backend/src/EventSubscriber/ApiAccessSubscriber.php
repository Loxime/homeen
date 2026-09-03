<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiAccessSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, array{0:string, 1:int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                'onKernelRequest',
                32,
            ],
        ];
    }

    public function onKernelRequest(
        RequestEvent $event,
    ): void {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        if (
            $path === '/api/health'
            || $path === '/api/access/login'
            || $path === '/api/access/status'
        ) {
            return;
        }

        $session = $request->getSession();

        if (
            $session->get(
                'homeen_access_granted'
            ) !== true
        ) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'error' =>
                            'Access key required.',
                        'code' =>
                            'ACCESS_REQUIRED',
                    ],
                    401,
                ),
            );

            return;
        }

        if (
            $path === '/api/auth/login'
            || $path === '/api/access/logout'
        ) {
            $this->validateCsrf(
                $event,
                $request,
            );

            return;
        }

        $userId = (int) $session->get(
            'homeen_user_id',
            0,
        );

        if ($userId <= 0) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'error' =>
                            'User authentication required.',
                        'code' =>
                            'USER_AUTH_REQUIRED',
                    ],
                    401,
                ),
            );

            return;
        }

        $passwordChangeRequired =
            $session->get(
                'homeen_password_change_required'
            ) === true;

        if (
            $path
                === '/api/auth/change-temporary-password'
        ) {
            $this->validateCsrf(
                $event,
                $request,
            );

            return;
        }

        if ($passwordChangeRequired) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'error' =>
                            'Password change required.',
                        'code' =>
                            'PASSWORD_CHANGE_REQUIRED',
                    ],
                    403,
                ),
            );

            return;
        }

        $this->validateCsrf(
            $event,
            $request,
        );
    }

    private function validateCsrf(
        RequestEvent $event,
        Request $request,
    ): void {
        if (
            in_array(
                $request->getMethod(),
                [
                    'GET',
                    'HEAD',
                    'OPTIONS',
                ],
                true,
            )
        ) {
            return;
        }

        $session = $request->getSession();

        $expected = (string) $session->get(
            'homeen_csrf',
            '',
        );

        $provided = (string) $request
            ->headers
            ->get(
                'X-CSRF-TOKEN',
                '',
            );

        if (
            $expected === ''
            || $provided === ''
            || !hash_equals(
                $expected,
                $provided,
            )
        ) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'error' =>
                            'Invalid CSRF token.',
                        'code' =>
                            'INVALID_CSRF_TOKEN',
                    ],
                    403,
                ),
            );
        }
    }
}
