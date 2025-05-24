<?php

declare(strict_types=1);

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
final class DispatchJobCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Dispatch a job to the queue')
            ->addArgument('name', InputArgument::REQUIRED, 'Job name')
            ->addArgument('payload', InputArgument::OPTIONAL, 'JSON payload')
            ->addArgument('delay', InputArgument::OPTIONAL, 'Delay in seconds', 0);
    }

    /**
     * Execute the command to dispatch a job.
     *
     * @param  InputInterface  $input  The input interface containing command arguments.
     * @param  OutputInterface  $output  The output interface for displaying messages.
     * @return int Command exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nameArg = $input->getArgument('name');
        $payloadArg = $input->getArgument('payload');
        $delayArg = $input->getArgument('delay');

        if (! is_string($nameArg)) {
            $output->writeln('<error>Job name must be a string</error>');

            return Command::FAILURE;
        }
        $name = trim($nameArg);
        $payloadJson = is_string($payloadArg) ? $payloadArg : '{}';
        $payload = json_decode($payloadJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('<error>Invalid JSON payload: '.json_last_error_msg().'</error>');

            return Command::FAILURE;
        }
        if (! is_array($payload) || array_is_list($payload)) {
            $output->writeln('<error>Payload must be a JSON object</error>');

            return Command::FAILURE;
        }

        $delay = 0;
        if (is_numeric($delayArg)) {
            $delay = (int) $delayArg;
        }

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
