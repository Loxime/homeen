<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiAccessSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 32]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        if ($path === '/api/health' || $path === '/api/access/login' || $path === '/api/access/status') {
            return;
        }

        $session = $request->getSession();
        if ($session->get('homeen_authenticated') !== true) {
            $event->setResponse(new JsonResponse(['error' => 'Authentication required.'], 401));
            return;
        }

        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $expected = (string) $session->get('homeen_csrf', '');
            $provided = (string) $request->headers->get('X-CSRF-TOKEN', '');
            if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
                $event->setResponse(new JsonResponse(['error' => 'Invalid CSRF token.'], 403));
            }
        }
    }
}
