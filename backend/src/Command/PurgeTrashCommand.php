<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ActivityLogger;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:trash:purge', description: 'Permanently delete notes that have been in trash for at least 30 days.')]
final class PurgeTrashCommand extends Command
{
    public function __construct(private readonly Connection $connection, private readonly ActivityLogger $logger)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = $this->connection->fetchFirstColumn("SELECT id FROM note WHERE deleted_at <= NOW() - INTERVAL '30 days'");
        foreach ($ids as $id) {
            $noteId = (int) $id;
            $this->connection->delete('note', ['id' => $noteId]);
            $this->logger->log('NOTE_PURGED', 'note', $noteId);
        }

        $output->writeln(sprintf('Purged %d note(s).', count($ids)));
        return Command::SUCCESS;
    }
}
