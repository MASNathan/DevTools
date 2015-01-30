<?php

namespace MASNathan\DevTools\Commands\Bitbucket;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Question\Question,
    MASNathan\DevTools\App\Config;

class SetupCommand extends Command {

    protected function configure()
    {   
        $this
            ->setName("bitbucket:setup")
            ->setDescription("Bitbucket auth setup.")
            ->setHelp(<<<EOT

You can create a new consumer at: https://bitbucket.org/account/user/<username or team>/api

EOT
);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = new Config;
        $output->writeln('');

        // Username
        $question = new Question('Enter your <info>Username</info>:');
        $username = $this->getHelper('question')->ask($input, $output, $question);
        $configuration->getBitbucket()->setUsername($username);
        // Key
        $question = new Question('Enter your <info>Consumer Key</info>:');
        $key = $this->getHelper('question')->ask($input, $output, $question);
        $configuration->getBitbucket()->setKey($key);
        // Secret
        $question = new Question('Enter your <info>Consumer Secret</info>:');
        $secret = $this->getHelper('question')->ask($input, $output, $question);
        $configuration->getBitbucket()->setSecret($secret);

        $output->writeln('');
        $output->writeln(sprintf('Welcome aboard: <info>%s</info>', $username));
    }
}
