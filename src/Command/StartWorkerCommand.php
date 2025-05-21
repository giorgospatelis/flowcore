<?php

namespace FlowCore\Command;

use FlowCore\Job\Registry;
use FlowCore\Queue\QueueManager;
use FlowCore\Queue\RedisQueue;
use FlowCore\Storage\RedisClient;
use FlowCore\Worker\JobWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'work')]
class StartWorkerCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Start a job worker to process jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registry = new Registry();

        // Register example jobs here
        // $registry->register('example', ExampleJob::class);

        $worker = new JobWorker(
            new QueueManager(
                new RedisQueue(
                    new RedisClient()
                )
            ),
            $registry
        );

        $output->writeln("<info>Worker started. Waiting for jobs...</info>");
        $worker->run();
        return Command::SUCCESS;
    }
}
