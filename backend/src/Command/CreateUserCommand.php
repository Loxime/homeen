<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\UserPasswordService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a Homeen user with a one-time temporary password.',
)]
final class CreateUserCommand extends Command
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
            'Primary login email address.',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $email = trim(
            (string) $input->getArgument('email')
        );

        if (
            $email === ''
            || strlen($email) > 254
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            $io->error('Invalid email address.');

            return Command::INVALID;
        }

        if ($this->users->emailExists($email)) {
            $io->error(
                'This email address is already linked to a Homeen account.'
            );

            return Command::FAILURE;
        }

        $temporaryPassword =
            $this->passwords
                ->generateTemporaryPassword();

        $passwordHash =
            $this->passwords
                ->hash($temporaryPassword);

        try {
            $user = $this->users->createWithPrimaryEmail(
                $email,
                $passwordHash,
            );
        } catch (UniqueConstraintViolationException) {
            $io->error(
                'This email address is already linked to a Homeen account.'
            );

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Homeen user #%d created.',
            $user['id'],
        ));

        $io->definitionList(
            ['Email' => $user['email']],
            [
                'Temporary password' =>
                    $temporaryPassword,
            ],
        );

        $io->warning([
            'This password is intended for first login only.',
            'Copy it now. Homeen does not store the plain-text password.',
        ]);

        return Command::SUCCESS;
    }
}
