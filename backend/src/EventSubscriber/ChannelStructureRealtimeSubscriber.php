<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\ChannelStructurePublisher;
use App\Service\CurrentUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ChannelStructureRealtimeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ChannelStructurePublisher $publisher,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE =>
                'onResponse',
        ];
    }

    public function onResponse(
        ResponseEvent $event,
    ): void {
        if (!$event->isMainRequest()) {
            return;
        }

        $response =
            $event->getResponse();

        if (
            $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
        ) {
            return;
        }

        $request =
            $event->getRequest();

        $route =
            $request->attributes
                ->get(
                    '_route',
                );

        if (!is_string($route)) {
            return;
        }

        try {
            match ($route) {
                'api_channel_invitation_accept' =>
                    $this->memberJoined(
                        $response,
                    ),

                'api_channel_leave' =>
                    $this->memberLeft(
                        (string) $request
                            ->attributes
                            ->get(
                                'code',
                                '',
                            ),
                    ),

                'api_channel_management_member_remove' =>
                    $this->memberRemoved(
                        (string) $request
                            ->attributes
                            ->get(
                                'code',
                                '',
                            ),
                        (int) $request
                            ->attributes
                            ->get(
                                'userId',
                                0,
                            ),
                    ),

                'api_channel_member_role_update' =>
                    $this->roleChanged(
                        (string) $request
                            ->attributes
                            ->get(
                                'code',
                                '',
                            ),
                        (int) $request
                            ->attributes
                            ->get(
                                'memberUserId',
                                0,
                            ),
                        $response,
                    ),

                'api_channel_ownership_transfer' =>
                    $this->ownershipTransferred(
                        (string) $request
                            ->attributes
                            ->get(
                                'code',
                                '',
                            ),
                        $response,
                    ),

                'api_channel_settings_update' =>
                    $this->channelUpdated(
                        (string) $request
                            ->attributes
                            ->get(
                                'code',
                                '',
                            ),
                    ),

                'api_channel_close' =>
                    $this->channelClosed(
                        (string) $request
                            ->attributes
                            ->get(
                                'code',
                                '',
                            ),
                    ),

                default =>
                    null,
            };
        } catch (\Throwable) {
            /*
             * The HTTP mutation already succeeded.
             * Realtime propagation cannot invalidate
             * the successful response.
             */
        }
    }

    private function memberJoined(
        Response $response,
    ): void {
        $data =
            $this->responseData(
                $response,
            );

        $code =
            (string) (
                $data['code']
                ?? ''
            );

        if ($code === '') {
            return;
        }

        $userId =
            $this->currentUser
                ->id();

        $this->publisher
            ->publish(
                $code,
                'member-joined',
                [
                    'userId' =>
                        $userId,
                ],
                [
                    $userId,
                ],
            );
    }

    private function memberLeft(
        string $code,
    ): void {
        if ($code === '') {
            return;
        }

        $userId =
            $this->currentUser
                ->id();

        /*
         * The membership was already deleted,
         * therefore explicitly include the user
         * who just left for their other tabs.
         */
        $this->publisher
            ->publish(
                $code,
                'member-left',
                [
                    'userId' =>
                        $userId,
                ],
                [
                    $userId,
                ],
            );
    }

    private function memberRemoved(
        string $code,
        int $userId,
    ): void {
        if (
            $code === ''
            || $userId <= 0
        ) {
            return;
        }

        /*
         * The removed membership no longer exists,
         * so the removed user must be explicitly
         * included in the personal-topic audience.
         */
        $this->publisher
            ->publish(
                $code,
                'member-removed',
                [
                    'userId' =>
                        $userId,
                ],
                [
                    $userId,
                ],
            );
    }

    private function roleChanged(
        string $code,
        int $userId,
        Response $response,
    ): void {
        if (
            $code === ''
            || $userId <= 0
        ) {
            return;
        }

        $data =
            $this->responseData(
                $response,
            );

        $this->publisher
            ->publish(
                $code,
                'role-changed',
                [
                    'userId' =>
                        $userId,

                    'role' =>
                        (string) (
                            $data['role']
                            ?? ''
                        ),
                ],
            );
    }

    private function ownershipTransferred(
        string $code,
        Response $response,
    ): void {
        if ($code === '') {
            return;
        }

        $data =
            $this->responseData(
                $response,
            );

        $this->publisher
            ->publish(
                $code,
                'ownership-transferred',
                [
                    'previousCreatorUserId' =>
                        (int) (
                            $data[
                                'previousCreatorUserId'
                            ]
                            ?? 0
                        ),

                    'newCreatorUserId' =>
                        (int) (
                            $data[
                                'newCreatorUserId'
                            ]
                            ?? 0
                        ),
                ],
            );
    }

    private function channelUpdated(
        string $code,
    ): void {
        if ($code === '') {
            return;
        }

        $this->publisher
            ->publish(
                $code,
                'channel-updated',
            );
    }

    private function channelClosed(
        string $code,
    ): void {
        if ($code === '') {
            return;
        }

        $this->publisher
            ->publish(
                $code,
                'channel-closed',
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(
        Response $response,
    ): array {
        $content =
            $response->getContent();

        if (
            !is_string($content)
            || $content === ''
        ) {
            return [];
        }

        $decoded =
            json_decode(
                $content,
                true,
            );

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
