<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:claim-legacy-data',
    description: 'Assign Homeen data created before user accounts existed to one user.',
)]
final class ClaimLegacyDataCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Any email address linked to the target Homeen account.',
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Skip confirmation.',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle(
            $input,
            $output,
        );

        $email = trim(
            (string) $input->getArgument('email')
        );

        if (
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            $io->error(
                'Invalid email address.'
            );

            return Command::INVALID;
        }

        $user = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    u.id,
    ue.email
FROM app_user u
INNER JOIN user_email ue
    ON ue.user_id = u.id
WHERE ue.normalized_email = :email
LIMIT 1
SQL,
                [
                    'email' =>
                        UserRepository::normalizeEmail(
                            $email
                        ),
                ],
            );

        if ($user === false) {
            $io->error(
                'No Homeen account uses this email address.'
            );

            return Command::FAILURE;
        }

        $userId = (int) $user['id'];

        /*
         * Prevent collisions before modifying anything.
         */
        $labelConflict = $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT 1
FROM label legacy
INNER JOIN label owned
    ON owned.user_id = :userId
   AND owned.name = legacy.name
WHERE legacy.user_id IS NULL
LIMIT 1
SQL,
                ['userId' => $userId],
            );

        if ($labelConflict !== false) {
            $io->error(
                'Cannot claim legacy data: a legacy label conflicts with an existing user label.'
            );

            return Command::FAILURE;
        }

        $presetConflict = $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT 1
FROM pomodoro_preset legacy
INNER JOIN pomodoro_preset owned
    ON owned.user_id = :userId
   AND owned.work_minutes = legacy.work_minutes
WHERE legacy.user_id IS NULL
LIMIT 1
SQL,
                ['userId' => $userId],
            );

        if ($presetConflict !== false) {
            $io->error(
                'Cannot claim legacy data: a legacy Pomodoro preset conflicts with an existing user preset.'
            );

            return Command::FAILURE;
        }

        $counts = [
            'labels' => (int) $this->connection
                ->fetchOne(
                    'SELECT COUNT(*) FROM label WHERE user_id IS NULL'
                ),
            'notes' => (int) $this->connection
                ->fetchOne(
                    'SELECT COUNT(*) FROM note WHERE user_id IS NULL'
                ),
            'pomodoroPresets' => (int) $this->connection
                ->fetchOne(
                    'SELECT COUNT(*) FROM pomodoro_preset WHERE user_id IS NULL'
                ),
            'pomodoroSessions' => (int) $this->connection
                ->fetchOne(
                    'SELECT COUNT(*) FROM pomodoro_session WHERE user_id IS NULL'
                ),
            'activityEvents' => (int) $this->connection
                ->fetchOne(
                    'SELECT COUNT(*) FROM activity_event WHERE user_id IS NULL'
                ),
            'usageSessions' => (int) $this->connection
                ->fetchOne(
                    'SELECT COUNT(*) FROM app_usage_session WHERE user_id IS NULL'
                ),
        ];

        $io->section('Legacy data');

        $io->definitionList(
            ['User' => (string) $user['email']],
            ['User ID' => (string) $userId],
            ['Labels' => (string) $counts['labels']],
            ['Notes' => (string) $counts['notes']],
            [
                'Pomodoro presets' =>
                    (string) $counts['pomodoroPresets'],
            ],
            [
                'Pomodoro sessions' =>
                    (string) $counts['pomodoroSessions'],
            ],
            [
                'Activity events' =>
                    (string) $counts['activityEvents'],
            ],
            [
                'Usage sessions' =>
                    (string) $counts['usageSessions'],
            ],
        );

        if (
            !$input->getOption('force')
            && !$io->confirm(
                'Assign all legacy Homeen data to this user?',
                false,
            )
        ) {
            $io->warning(
                'No data was changed.'
            );

            return Command::SUCCESS;
        }

        $this->connection->transactional(
            function (
                Connection $connection
            ) use ($userId): void {
                foreach ([
                    'label',
                    'note',
                    'pomodoro_preset',
                    'pomodoro_session',
                    'activity_event',
                    'app_usage_session',
                ] as $table) {
                    $connection->executeStatement(
                        sprintf(
                            'UPDATE %s SET user_id = :userId WHERE user_id IS NULL',
                            $table,
                        ),
                        ['userId' => $userId],
                    );
                }
            },
        );

        $io->success(
            'Legacy Homeen data assigned successfully.'
        );

        return Command::SUCCESS;
    }
}
