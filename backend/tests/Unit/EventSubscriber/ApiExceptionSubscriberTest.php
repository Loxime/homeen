<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ApiExceptionSubscriber;
use App\Exception\ApiProblemException;
use App\Exception\ConflictException;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionSubscriberTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     0:ApiProblemException,
     *     1:int,
     *     2:string,
     *     3:string
     * }>
     */
    public static function problemProvider():
    iterable {
        yield 'forbidden' => [
            new ForbiddenException(
                'Forbidden.',
                'TEST_FORBIDDEN',
            ),
            403,
            'Forbidden.',
            'TEST_FORBIDDEN',
        ];

        yield 'not found' => [
            new NotFoundException(
                'Missing.',
                'TEST_NOT_FOUND',
            ),
            404,
            'Missing.',
            'TEST_NOT_FOUND',
        ];

        yield 'conflict' => [
            new ConflictException(
                'Conflict.',
                'TEST_CONFLICT',
            ),
            409,
            'Conflict.',
            'TEST_CONFLICT',
        ];
    }

    #[DataProvider('problemProvider')]
    public function testTypedApiProblemsHaveStableContract(
        ApiProblemException $exception,
        int $status,
        string $message,
        string $code,
    ): void {
        $event =
            $this->event(
                $exception,
            );

        (new ApiExceptionSubscriber())
            ->onKernelException(
                $event,
            );

        $response =
            $event->getResponse();

        self::assertInstanceOf(
            JsonResponse::class,
            $response,
        );

        self::assertSame(
            $status,
            $response->getStatusCode(),
        );

        self::assertSame(
            [
                'error' => $message,
                'code' => $code,
            ],
            $this->payload(
                $response,
            ),
        );
    }

    public function testLegacyValidationStillWorks():
    void {
        $event =
            $this->event(
                new \InvalidArgumentException(
                    'Legacy validation.',
                ),
            );

        (new ApiExceptionSubscriber())
            ->onKernelException(
                $event,
            );

        $response =
            $event->getResponse();

        self::assertInstanceOf(
            JsonResponse::class,
            $response,
        );

        self::assertSame(
            422,
            $response->getStatusCode(),
        );

        self::assertSame(
            [
                'error' =>
                    'Legacy validation.',
            ],
            $this->payload(
                $response,
            ),
        );
    }

    public function testInternalErrorMessageIsHidden():
    void {
        $event =
            $this->event(
                new \RuntimeException(
                    'Database password leaked.',
                ),
            );

        (new ApiExceptionSubscriber())
            ->onKernelException(
                $event,
            );

        $response =
            $event->getResponse();

        self::assertInstanceOf(
            JsonResponse::class,
            $response,
        );

        self::assertSame(
            500,
            $response->getStatusCode(),
        );

        self::assertSame(
            [
                'error' =>
                    'Internal server error.',
            ],
            $this->payload(
                $response,
            ),
        );
    }

    public function testNonApiExceptionIsIgnored():
    void {
        $event =
            $this->event(
                new \RuntimeException(
                    'Failure.',
                ),
                '/profile',
            );

        (new ApiExceptionSubscriber())
            ->onKernelException(
                $event,
            );

        self::assertNull(
            $event->getResponse(),
        );
    }

    private function event(
        \Throwable $exception,
        string $path = '/api/test',
    ): ExceptionEvent {
        $kernel =
            $this->createStub(
                HttpKernelInterface::class,
            );

        return new ExceptionEvent(
            $kernel,
            Request::create(
                $path,
            ),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        JsonResponse $response,
    ): array {
        $content =
            $response->getContent();

        self::assertIsString(
            $content,
        );

        $data =
            json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

        self::assertIsArray(
            $data,
        );

        return $data;
    }
}
