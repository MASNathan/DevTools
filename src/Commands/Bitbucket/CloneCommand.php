<?php

namespace MASNathan\DevTools\Commands\Bitbucket;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Question\ChoiceQuestion,
    MASNathan\DevTools\App\Config,
    MASNathan\DevTools\App\Git,
    Bitbucket\API\Repositories,
    Bitbucket\API\Http\Listener\OAuthListener;

class CloneCommand extends Command {

    protected function configure()
    {   
        $this->configuration = new Config;
        $this
            ->setName("bitbucket:clone")
            ->setDescription("Helper to clone bitbucket repositories.")
            ->addArgument(
                'username',
                InputArgument::OPTIONAL,
                'The user you want to spy on.'
            )
            ->addOption(
               'all',
               null,
               InputOption::VALUE_NONE,
               'If set, all the repositories of the user will be cloned.'
            )
            ->addOption(
               'repository',
               'r',
               InputOption::VALUE_OPTIONAL,
               'The name of the repository that you want to clone.'
            )
            ->setHelp(<<<EOT
Helps you cloning repositories

Usage:

<info>dev-tools bitbucket:clone github_user</info>

You can also set your credentials by executing <info>dev-tools bitbucket:setup</info> to simply list your stuff

<info>dev-tools bitbucket:clone</info>

If you don't know the name of the repo you want to clone, don't panic, you'll get to choose one, but if you do, you can also run something like this:

<info>dev-tools bitbucket:clone -r repository_name</info>

EOT
);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $input->getOption('repository');
        $username   = $input->getArgument('username');
        if (!$username) {
            if ($this->configuration->getBitbucket()->getUsername()) {
                $username = $this->configuration->getBitbucket()->getUsername();
            } else {
                throw new \Exception("No username to look for, please use 'dev-tools github:setup'");
            }
        }
        
        // Here we clone only one repository, the one that the user asked for
        if ($username && $repository) {
            if ($path = $this->getRepositorySsh($username, $repository)) {
                return $this->cloneRepo($output, $username . '/' . $repository, $path);
            }

            throw new \Exception("Repository not found");
        }

        // Here we get the list of repos
        $user = new \Bitbucket\API\User();
        $user->getClient()->addListener(new OAuthListener([
                'oauth_consumer_key'      => $this->configuration->getBitbucket()->getKey(),
                'oauth_consumer_secret'   => $this->configuration->getBitbucket()->getSecret(),
            ]));

        $repos = json_decode($user->repositories()->get()->getContent(), true);

        $cloneAllRepos = $input->getOption('all');
        // If --all flag is setted, we do what we gotta do, clone everything
        if ($cloneAllRepos) {
            foreach ($repos as $repo) {
                if ($path = $this->getRepositorySsh($repo['owner'], $repo['slug'])) {
                    $this->cloneRepo($output, $repo['owner'] . '/' . $repo['slug'], $path);
                }
            }

            return;
        }
        
        // Well maybe you don't know what to clone
        // If the user doesn't know what to clone, we ask
        // Array that stores the answers
        $answerOptions = [];
        // Here we have a row index, so we can clone it later
        $row = 1;

        foreach ($repos as $repo) {
            $answerOptions[$row++] = $repo['owner'] . '/' . $repo['slug'];
        }

        $question = new ChoiceQuestion(
            'Please select a repository to clone:',
            $answerOptions
        );
        $question->setErrorMessage('The repository %s is invalid.');

        $repository = $this->getHelper('question')->ask($input, $output, $question);
        $output->writeln('You selected: '. $repository);

        $repositoryParts = explode('/', $repository);
        if ($path = $this->getRepositorySsh(reset($repositoryParts), end($repositoryParts))) {
            $this->cloneRepo($output, $repository, $path);
        }
    }

    /**
     * Returns the SSH path to clone the repository
     * @param  string $owner    Owner
     * @param  string $repoName Repository name
     * @return string Repo clone path
     */
    protected function getRepositorySsh($owner, $repoName)
    {
        // To get the repo ssh url
        $repo = new \Bitbucket\API\Repositories\Repository();
        $repo->getClient()->addListener(new OAuthListener([
                'oauth_consumer_key'      => $this->configuration->getBitbucket()->getKey(),
                'oauth_consumer_secret'   => $this->configuration->getBitbucket()->getSecret(),
            ]));

        $repoDetails = json_decode($repo->get($owner, $repoName)->getContent());
        if (isset($repoDetails->error->message)) {
            throw new \Exception($repoDetails->error->message);
        }

        if (isset($repoDetails->links->clone)) {
            if (is_array($repoDetails->links->clone)) {
                return end($repoDetails->links->clone)->href;
            }
            return $repoDetails->links->clone;
        }
        return false;
    }

    protected function cloneRepo($output, $repoName, $repoSshPath)
    {
        $formatter = $this->getHelper('formatter');

        $output->writeln($formatter->formatSection($repoName, 'Cloning from ' . $repoSshPath));
        $output->writeln($formatter->formatSection($repoName, '...'));

        $message = Git::cloneRepo($repoSshPath);

        if (strpos($message, 'fatal:') === 0) {
            $output->writeln($formatter->formatSection($repoName, $message, 'error'));
        } else {
            $output->writeln($formatter->formatSection($repoName, 'Cloned!'));
        }

        $output->writeln('');
    }
}
