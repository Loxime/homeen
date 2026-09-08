<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ChannelMercureAudienceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;

final readonly class ChannelMercureAuthorizationService
{
    public function __construct(
        private ChannelMercureAudienceRepository $audience,
        private ChannelMercureTopic $topics,
        private CurrentUser $currentUser,
        private Authorization $authorization,
    ) {
    }

    /**
     * @return array{
     *     currentUserId:int,
     *     notificationTopic:string
     * }
     */
    public function authorize(
        Request $request,
    ): array {
        $userId =
            $this->currentUser
                ->id();

        $grantedTopics = [
            $this->topics
                ->notifications(
                    $userId,
                ),
        ];

        /*
         * Every channel topic is scoped both to
         * the channel and to the authenticated
         * user.
         *
         * Possessing a stale JWT therefore does
         * not grant access to another member's
         * stream.
         */
        foreach (
            $this->audience
                ->accessibleChannelCodes()
            as $code
        ) {
            $grantedTopics[] =
                $this->topics
                    ->messagesForUser(
                        $code,
                        $userId,
                    );
        }

        $this->authorization
            ->setCookie(
                $request,
                array_values(
                    array_unique(
                        $grantedTopics,
                    ),
                ),
            );

        return [
            'currentUserId' =>
                $userId,

            'notificationTopic' =>
                $this->topics
                    ->notifications(
                        $userId,
                    ),
        ];
    }
}
