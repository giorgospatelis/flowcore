<?php

namespace FlowCore\Command;

use FlowCore\Dispatcher\JobDispatcher;
use FlowCore\Queue\QueueManager;
use FlowCore\Queue\RedisQueue;
use FlowCore\Storage\RedisClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'dispatch:job')]
class DispatchJobCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Dispatch a job to the queue')
            ->addArgument('name', InputArgument::REQUIRED, 'Job name')
            ->addArgument('payload', InputArgument::OPTIONAL, 'JSON payload')
            ->addArgument('delay', InputArgument::OPTIONAL, 'Delay in seconds', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $payload = json_decode($input->getArgument('payload') ?? '{}', true);
        $delay = (int) $input->getArgument('delay');

        $dispatcher = new JobDispatcher(
            new QueueManager(
                new RedisQueue(
                    new RedisClient()
                )
            )
        );

        $dispatcher->dispatch($name, $payload, $delay);

        $output->writeln("<info>Dispatched job '$name'</info>");
        return Command::SUCCESS;
    }
}