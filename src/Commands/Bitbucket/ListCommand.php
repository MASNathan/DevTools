<?php

namespace MASNathan\DevTools\Commands\Bitbucket;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Helper\Table,
    MASNathan\DevTools\App\Config,
    Bitbucket\API\Repositories,
    Bitbucket\API\Http\Listener\OAuthListener;

class ListCommand extends Command {

    protected function configure()
    {   

        $this
            ->setName("bitbucket:list")
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
            if ($configuration->getBitbucket()->getUsername()) {
                $username = $configuration->getBitbucket()->getUsername();
            } else {
                throw new \Exception("No username to look for, please use 'dev-tools github:setup'");
            }
        }
        
        $repos = [];

        if ($username == $configuration->getBitbucket()->getUsername()) {
            $user = new \Bitbucket\API\User();
            $user->getClient()->addListener(new OAuthListener([
                    'oauth_consumer_key'      => $configuration->getBitbucket()->getKey(),
                    'oauth_consumer_secret'   => $configuration->getBitbucket()->getSecret(),
                ]));

            $repos = json_decode($user->repositories()->get()->getContent(), true);
            
            // To get the repo ssh url
            //$repo = new \Bitbucket\API\Repositories\Repository();
            //$repo->getClient()->addListener(new OAuthListener([
            //        'oauth_consumer_key'      => $configuration->getBitbucket()->getKey(),
            //        'oauth_consumer_secret'   => $configuration->getBitbucket()->getSecret(),
            //    ]));
            //print_r(json_decode($repo->get('gemabit', 'foo-d')->getContent()));exit;

        } else {
            $repositories = new Repositories();
            $repositories->getClient()->setApiVersion('2.0')->addListener(new OAuthListener([
                    'oauth_consumer_key'      => $configuration->getBitbucket()->getKey(),
                    'oauth_consumer_secret'   => $configuration->getBitbucket()->getSecret(),
                ]));

            $repos = json_decode($repositories->all($username)->getContent(), true);

            while (isset($repos['next'])) {
                $nextPageResult = $repositories->getClient()->setApiVersion('2.0')->request($repos['next']);
                $nextPage = json_decode($nextPageResult->getContent(), true);

                $repos['values'] = array_merge($repos['values'], $nextPage['values']);

                if (isset($nextPage['next'])) {
                    $repos['next'] = $nextPage['next'];
                } else {
                    unset($repos['next']);
                }
            }

            $repos = $repos['values'];
        }

        $table = new Table($output);
        $table->setHeaders(['#', 'Type', 'Name', 'Language']);
        $row = 1;
        // My Repositories
        foreach ($repos as $repo) {
            if (!isset($repo['full_name'])) {
                $repo['full_name'] = sprintf("%s/%s", $repo['owner'], $repo['slug']);
            }
            $table->addRow([$row++, $repo['is_private'] ? 'Private' : 'Public', $repo['full_name'], $repo['language']]);
        }
        
        $table->render();
    }
}
