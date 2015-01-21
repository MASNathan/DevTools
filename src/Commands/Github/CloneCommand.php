<?php

namespace MASNathan\DevTools\Commands\Github;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Github\Client as GithubClient;

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
            ->addArgument(
                'repository',
                InputArgument::OPTIONAL,
                'The repository you want to clone.'
            )
            ->addOption(
               'all',
               null,
               InputOption::VALUE_NONE,
               'If set, all the repositories of the user will be cloned.'
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
        $username   = $input->getArgument('username');
        $repository = $input->getArgument('repository');
        if (!$username) {
            $username = 'reidukuduro';
        }
        
        $cloneAllRepos = $input->getOption('all');

        $client = new GithubClient();
        $repos = $client->api('user')->repositories($username);

        $reposSsh = [];
        $answerOptions = [];
        foreach ($repos as $key => $repo) {
            $answerOptions[$key] = $repo['full_name'];
            $reposSsh[strtolower($repo['full_name'])] = [
                'name' => $repo['full_name'],
                'ssh' => $repo['ssh_url']
            ];
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

        echo shell_exec("/usr/bin/git clone $repoSshPath 2>&1");


        $output->writeln($formatter->formatSection($repoName, 'Cloned!'));
        $output->writeln('');
    }
}
