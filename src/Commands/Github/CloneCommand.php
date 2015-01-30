<?php

namespace MASNathan\DevTools\Commands\Github;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Question\ChoiceQuestion,
    MASNathan\DevTools\App\Config,
    MASNathan\DevTools\App\Git,
    Github\Client as GithubClient;

class CloneCommand extends Command {

    protected function configure()
    {   

        $this
            ->setName("github:clone")
            ->setDescription("Helper to clone github repositories.")
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

<info>dev-tools github:clone github_user</info>

You can also set your credentials by executing <info>dev-tools github:setup</info> to simply list your stuff

<info>dev-tools github:clone</info>

If you don't know the name of the repo you want to clone, don't panic, you'll get to choose one, but if you do, you can also run something like this:

<info>dev-tools github:clone -r repository_name</info>

EOT
);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = new Config;

        $repository = $input->getOption('repository');
        $username   = $input->getArgument('username');
        if (!$username) {
            if ($configuration->getGithub()->getUsername()) {
                $username = $configuration->getGithub()->getUsername();
            } else {
                throw new \Exception("No username to look for, please use 'dev-tools github:setup'");
            }
        }
        
        $cloneAllRepos = $input->getOption('all');

        $client = new GithubClient();
        $client->authenticate($configuration->getGithub()->getToken(), null, GithubClient::AUTH_URL_TOKEN);

        // Array that stores the repositories ssh keys
        $reposSsh = [];
        // Array that stores the answers
        $answerOptions = [];
        // Here we have a row index, so we can clone it later
        $row = 1;

        // Lets fetch my repositories
        $repos = $client->api('user')->repositories($username);
        foreach ($repos as $key => $repo) {
            $answerOptions[$row++] = $repo['full_name'];
            $reposSsh[strtolower($repo['full_name'])] = [
                'name' => $repo['full_name'],
                'ssh' => $repo['ssh_url']
            ];
        }
        // Now let's fetch my organizations repositories, if any
        // My Organizations
        $organizations = $client->api('user')->organizations($username);
        foreach ($organizations as $organization) {
            $organizationRepos = $client->api('organization')->repositories($organization['login'], 'member');
            foreach ($organizationRepos as $key => $repo) {
                $answerOptions[$row++] = $repo['full_name'];
                $reposSsh[strtolower($repo['full_name'])] = [
                    'name' => $repo['full_name'],
                    'ssh' => $repo['ssh_url']
                ];
            }
        }

        // Let's clone all the things
        if ($cloneAllRepos) {
            foreach ($reposSsh as $repo) {
                $this->cloneRepo($output, $repo['name'], $repo['ssh']);
            }
            exit;
        }

        // If we have a repo name to work with, let's do it
        if ($repository) {
            $lowRepository = strtolower($repository);
            $lowUsername   = strtolower($username);

            if (isset($reposSsh[$lowRepository])) {
                $this->cloneRepo(
                    $output,
                    $reposSsh[$lowRepository]['name'], 
                    $reposSsh[$lowRepository]['ssh']
                );
            } elseif (isset($reposSsh[$lowUsername . '/' . $lowRepository])) {
                $this->cloneRepo(
                    $output,
                    $reposSsh[$lowUsername . '/' . $lowRepository]['name'], 
                    $reposSsh[$lowUsername . '/' . $lowRepository]['ssh']
                );
            } else {
                $formattedBlock = $this->getHelper('formatter')->formatBlock(['', 'Error!', sprintf('Repository "%s" not found for the user "%s"', $repository, $username), ''], 'error');
                $output->writeln($formattedBlock);
            }
            exit;
        }

        // Well maybe you don't know what to clone
        $question = new ChoiceQuestion(
            'Please select a repository to clone:',
            $answerOptions
        );
        $question->setErrorMessage('The repository %s is invalid.');

        $repository = $this->getHelper('question')->ask($input, $output, $question);
        $output->writeln('You selected: '. $repository);
        $this->cloneRepo(
            $output,
            $reposSsh[strtolower($repository)]['name'], 
            $reposSsh[strtolower($repository)]['ssh']
        );
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
