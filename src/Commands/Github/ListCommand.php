<?php

namespace MASNathan\DevTools\Commands\Github;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Github\Client as GithubClient;

class ListCommand extends Command {

    protected function configure()
    {   

        $this
            ->setName("github:list")
            ->setDescription("Lists all the [public] repositories of a user.")
            ->addArgument(
                'username',
                InputArgument::OPTIONAL,
                'The user that you want to spy on.'
            )
            ->addOption(
               'private',
               'p',
               InputOption::VALUE_NONE,
               'If set, the private repositories will be displayed instead.'
            )
            ->setHelp(<<<EOT
Display the repositories of a Github user

Usage:

<info>dev-tools github:list reidukuduro</info>

You can also set your credentials on the config file to simply list your stuff

<info>dev-tools github:list</info>
EOT
);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @todo if empty, read the user from config
         */
        $username = $input->getArgument('username');
        if (!$username) {
            $username = 'reidukuduro';
        }
        
        $showPrivateRepos = $input->getOption('private');

        $table = new Table($output);
        $table->setHeaders(['#', 'Name', 'Language']);

        $client = new GithubClient();
        //$users = $client->api('user')->find($username);
        $repos = $client->api('user')->repositories($username);
        foreach ($repos as $key => $repo) {
            $table->addRow([$key, $repo['full_name'], $repo['language']]);
        }

        $table->render();
    }
}
