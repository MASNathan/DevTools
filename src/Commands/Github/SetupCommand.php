<?php

namespace MASNathan\DevTools\Commands\Github;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Question\Question,
    MASNathan\DevTools\App\Config;

class SetupCommand extends Command {

    protected function configure()
    {   
        $this
            ->setName("github:setup")
            ->setDescription("Github auth setup.")
            ->setHelp(<<<EOT

To generate a token please go to github.com -> Settings -> Applications -> Generate new token

EOT
);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = new Config;

        // Username
        $question = new Question('Enter your username:');
        $username = $this->getHelper('question')->ask($input, $output, $question);
        $configuration->getGithub()->setUsername($username);
        // Token
        $question = new Question('Enter your github token:');
        $token = $this->getHelper('question')->ask($input, $output, $question);
        $configuration->getGithub()->setToken($token);

        $output->writeln('Welcome aboard: '. $username);
    }
}
