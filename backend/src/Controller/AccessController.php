<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UsageRepository;
use App\Service\JsonInput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/access')]
final readonly class AccessController
{
    public function __construct(
        #[Autowire('%env(ACCESS_KEY)%')] private string $accessKey,
        private RateLimiterFactory $accessLoginLimiter,
        private JsonInput $input,
        private UsageRepository $usage,
    ) {
    }

    #[Route('/status', name: 'api_access_status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $authenticated = $session->get('homeen_authenticated') === true;

        return new JsonResponse([
            'authenticated' => $authenticated,
            'csrfToken' => $authenticated ? $this->csrfToken($session) : null,
        ]);
    }

    #[Route('/login', name: 'api_access_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $limit = $this->accessLoginLimiter->create($request->getClientIp() ?? 'unknown')->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse(['error' => 'Too many access attempts. Try again later.'], 429);
        }

        $data = $this->input->read($request);
        $provided = (string) ($data['accessKey'] ?? '');
        if ($this->accessKey === '' || $provided === '' || !hash_equals($this->accessKey, $provided)) {
            return new JsonResponse(['error' => 'Invalid access key.'], 401);
        }

        $session = $request->getSession();
        $session->migrate(true);
        $session->set('homeen_authenticated', true);
        $session->set('homeen_csrf', bin2hex(random_bytes(32)));

        return new JsonResponse([
            'authenticated' => true,
            'csrfToken' => $this->csrfToken($session),
        ]);
    }

    #[Route('/logout', name: 'api_access_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $this->usage->stopAllOpen();
        $request->getSession()->invalidate();

        return new JsonResponse(['authenticated' => false]);
    }

    private function csrfToken(\Symfony\Component\HttpFoundation\Session\SessionInterface $session): string
    {
        $token = (string) $session->get('homeen_csrf', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set('homeen_csrf', $token);
        }

        return $token;
    }
}
