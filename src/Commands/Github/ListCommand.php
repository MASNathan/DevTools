<?php

namespace MASNathan\DevTools\Commands\Github;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Helper\Table,
    MASNathan\DevTools\App\Config,
    Github\Client as GithubClient;

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
            ->setHelp(<<<EOT
Display the repositories of a Github user

Usage:

<info>dev-tools github:list github_user</info>

You can also set your credentials by executing <info>dev-tools github:setup</info> to simply list your stuff

<info>dev-tools github:list</info>
EOT
);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = new Config;

        $username = $input->getArgument('username');
        if (!$username) {
            if ($configuration->getGithub()->getUsername()) {
                $username = $configuration->getGithub()->getUsername();
            } else {
                throw new \Exception("No username to look for, please use 'dev-tools github:setup'");
            }
        }
        
        $client = new GithubClient();
        $client->authenticate($configuration->getGithub()->getToken(), null, GithubClient::AUTH_URL_TOKEN);

        $table = new Table($output);
        $table->setHeaders(['#', 'Type', 'Name', 'Language']);
        $row = 1;
        // My Repositories
        $repos = $client->api('user')->repositories($username);
        foreach ($repos as $key => $repo) {
            $table->addRow([$row++, $repo['private'] ? 'Private' : 'Public', $repo['full_name'], $repo['language']]);
        }
        // My Organizations
        $organizations = $client->api('user')->organizations($username);
        foreach ($organizations as $organization) {
            $organizationRepos = $client->api('organization')->repositories($organization['login'], 'member');
            foreach ($organizationRepos as $key => $repo) {
                $table->addRow([$row++, $repo['private'] ? 'Private' : 'Public', $repo['full_name'], $repo['language']]);
            }
        }
        $table->render();
    }
}
