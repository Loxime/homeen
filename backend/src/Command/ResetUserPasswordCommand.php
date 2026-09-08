<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\UserPasswordService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:reset-password',
    description: 'Reset a Homeen account to a new one-time temporary password.',
)]
final class ResetUserPasswordCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordService $passwords,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'email',
            InputArgument::REQUIRED,
            'Any email address linked to the account.',
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
            $email === ''
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            $io->error(
                'Invalid email address.'
            );

            return Command::INVALID;
        }

        $temporaryPassword =
            $this->passwords
                ->generateTemporaryPassword();

        $user = $this->users
            ->resetPasswordToTemporary(
                $email,
                $this->passwords->hash(
                    $temporaryPassword
                ),
            );

        if ($user === null) {
            $io->error(
                'No Homeen account uses this email address.'
            );

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Password reset for Homeen user #%d.',
            $user['id'],
        ));

        $io->definitionList(
            [
                'Email' =>
                    $user['email'],
            ],
            [
                'Temporary password' =>
                    $temporaryPassword,
            ],
        );

        $io->warning([
            'The previous password is no longer valid.',
            'This temporary password can be used for one successful login only.',
        ]);

        return Command::SUCCESS;
    }
}
