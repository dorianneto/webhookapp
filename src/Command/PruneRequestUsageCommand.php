<?php

namespace App\Command;

use App\Application\UseCase\RequestUsage\PruneRequestUsageUseCase;
use DateTimeImmutable;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Uid\Uuid;

#[AsCronTask('0 0 * * *', schedule: 'prune-request-usage')]
#[AsCommand(
    name: 'app:prune-request-usage',
    description: 'Prune old request usage records',
)]
#[WithMonologChannel('hookyard')]
class PruneRequestUsageCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PruneRequestUsageUseCase $pruneRequestUsageUseCase,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $requestId = (string) Uuid::v4();
        $before = new DateTimeImmutable('-30 days');

        $this->logger->info('Prune request usage command invoked', [
            'request_id' => $requestId,
            'before'     => $before->format('Y-m-d'),
        ]);

        $deleted = $this->pruneRequestUsageUseCase->execute($requestId, $before);
        $io->success(sprintf('Deleted %d row(s) older than %s.', $deleted, $before->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
