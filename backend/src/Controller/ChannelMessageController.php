<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelMercureAudienceRepository;
use App\Repository\ChannelMessageRepository;
use App\Service\ChannelMercureTopic;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/channels/{code<\d{9}>}/messages'
)]
final readonly class ChannelMessageController
{
    public function __construct(
        private ChannelMessageRepository $messages,
        private ChannelMercureAudienceRepository $mercureAudience,
        private ChannelMercureTopic $topics,
        private HubInterface $hub,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_channel_messages_list',
        methods: ['GET'],
    )]
    public function list(
        string $code,
        Request $request,
    ): JsonResponse {
        $after =
            $request->query
                ->getInt(
                    'after',
                    0,
                );

        try {
            return new JsonResponse([
                'messages' =>
                    $this->messages->list(
                        $code,
                        $after > 0
                            ? $after
                            : null,
                    ),
            ]);
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
    }

    #[Route(
        '',
        name: 'api_channel_messages_create',
        methods: ['POST'],
    )]
    public function create(
        string $code,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input->read(
                $request
            );

        try {
            $message =
                $this->messages->create(
                    $code,
                    (string) (
                        $data['content']
                        ?? ''
                    ),
                );

            /*
             * PostgreSQL remains the source
             * of truth. Mercure only transports
             * the newly persisted representation.
             */
            try {
                $payload =
                    json_encode(
                        $message,
                        JSON_THROW_ON_ERROR,
                    );

                $this->hub->publish(
                    new Update(
                        $this->topics
                            ->messages(
                                $code
                            ),
                        $payload,
                        true,
                        (string) $message[
                            'id'
                        ],
                    ),
                );

                /*
                 * Notify every other member
                 * without duplicating the actual
                 * message content on their
                 * personal notification topic.
                 */
                $notificationTopics = [];

                foreach (
                    $this->mercureAudience
                        ->recipientUserIds(
                            $code,
                            (int) $message[
                                'authorUserId'
                            ],
                        )
                    as $recipientUserId
                ) {
                    $notificationTopics[] =
                        $this->topics
                            ->notifications(
                                $recipientUserId,
                            );
                }

                if ([] !== $notificationTopics) {
                    $notificationPayload =
                        json_encode(
                            [
                                'type' =>
                                    'channel-message',

                                'channelCode' =>
                                    $code,

                                'messageId' =>
                                    (int) $message[
                                        'id'
                                    ],
                            ],
                            JSON_THROW_ON_ERROR,
                        );

                    $this->hub->publish(
                        new Update(
                            $notificationTopics,
                            $notificationPayload,
                            true,
                            sprintf(
                                'channel-message-%d',
                                (int) $message[
                                    'id'
                                ],
                            ),
                        ),
                    );
                }
            } catch (\Throwable) {
                /*
                 * A temporary realtime outage
                 * must not turn an already stored
                 * message into a failed send.
                 *
                 * A reload still retrieves it
                 * from PostgreSQL.
                 */
            }

            return new JsonResponse(
                $message,
                201,
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
    }

    #[Route(
        '/read',
        name: 'api_channel_messages_read',
        methods: ['POST'],
    )]
    public function read(
        string $code,
    ): JsonResponse {
        try {
            $this->messages
                ->markRead(
                    $code
                );

            return new JsonResponse(
                null,
                204,
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
    }

    private function error(
        \Throwable $exception,
    ): JsonResponse {
        if (
            $exception
            instanceof \DomainException
            && $exception->getMessage()
                === 'CHANNEL_FORBIDDEN'
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Channel access denied.',

                    'code' =>
                        'CHANNEL_FORBIDDEN',
                ],
                403,
            );
        }

        if (
            $exception
            instanceof \OutOfBoundsException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        'CHANNEL_NOT_FOUND',
                ],
                404,
            );
        }

        if (
            $exception
            instanceof \InvalidArgumentException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        'INVALID_CHANNEL_MESSAGE',
                ],
                422,
            );
        }

        throw $exception;
    }
}
