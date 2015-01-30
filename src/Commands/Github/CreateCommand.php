<?php

namespace MASNathan\DevTools\Commands\Github;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Github\Client as GithubClient;
use MASNathan\DevTools\App\Config;
use MASNathan\DevTools\App\Git;

/**
 * This command helps you creating github repositories from your couch
 */
class CreateCommand extends Command {

    /**
     * Command configuration setup
     * @return void
     */
    protected function configure()
    {   

        $this
            ->setName("github:create")
            ->setDescription("Helper to create github repositories.")
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of the repository you want to create.'
            )
            ->addOption(
               'clone',
               'c',
               InputOption::VALUE_NONE,
               'Clones the repository after creation.'
            )
            ->setHelp(<<<EOT
Let's create a github repo from cmd

Usage:

<info>dev-tools github:create repository_name</info>

You can also clone the repo immediately by using the --clone/-c option

<info>dev-tools github:create repository_name -c</info>

EOT
);

    }

    /**
     * Execute action for this command
     * @param  InputInterface  $input  
     * @param  OutputInterface $output 
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = new Config;

        if (!$configuration->getGithub()->getUsername()) {
            throw new \Exception("No username to look for, please use 'dev-tools github:setup'");
        }

        $username = $configuration->getGithub()->getUsername();
        $name = $input->getArgument('name');

        $output->writeln("");
        $output->writeln("Let's start our configuration for the repository <info>$name</info>");

        $client = new GithubClient();
        $client->authenticate($configuration->getGithub()->getToken(), null, GithubClient::AUTH_URL_TOKEN);

        // Description
        $question = new Question('Enter a <info>short description</info> of the repository:');
        $description = $this->getHelper('question')->ask($input, $output, $question);
        // Homepage
        $question = new Question('Enter a <info>URL</info> with more information about the repository:');
        $homepage = $this->getHelper('question')->ask($input, $output, $question);
        // Public or Private
        $question = new Question('Is this a <info>Private</info> repository (y/n):');
        $question->setValidator(function ($answer) {
            if (!in_array($answer, ['y', 'Y', 'n', 'N'])) {
                throw new \RuntimeException('Please use one of the following answers: "y" or "n"');
            }
            return $answer;
        });
        $private = $this->getHelper('question')->ask($input, $output, $question);
        // Does it belong to an organization?
        $orgAnswerOptions = ['none'];
        $organizations = $client->api('user')->organizations($username);
        foreach ($organizations as $organization) {
            $orgAnswerOptions[] = $organization['login'];
        }
        $question = new ChoiceQuestion(
            'Please choose the <info>organization</info> that the repository belongs to:',
            $orgAnswerOptions
        );
        $question->setErrorMessage('The organization %s is invalid.');
        $organization = $this->getHelper('question')->ask($input, $output, $question);
        // Enable Issues?
        $question = new Question('Enable the <info>Issues</info> for this repository (y/n):');
        $question->setValidator(function ($answer) {
            if (!in_array($answer, ['y', 'Y', 'n', 'N'])) {
                throw new \RuntimeException('Please use one of the following answers: "y" or "n"');
            }
            return $answer;
        });
        $hasIssues = $this->getHelper('question')->ask($input, $output, $question);
        // Enable Wiki?
        $question = new Question('Enable the <info>Wiki</info> for this repository (y/n):');
        $question->setValidator(function ($answer) {
            if (!in_array($answer, ['y', 'Y', 'n', 'N'])) {
                throw new \RuntimeException('Please use one of the following answers: "y" or "n"');
            }
            return $answer;
        });
        $hasWiki = $this->getHelper('question')->ask($input, $output, $question);
        // Create README file
        $question = new Question('Initialize this repository with a <info>README</info> (y/n):');
        $question->setValidator(function ($answer) {
            if (!in_array($answer, ['y', 'Y', 'n', 'N'])) {
                throw new \RuntimeException('Please use one of the following answers: "y" or "n"');
            }
            return $answer;
        });
        $autoInit = $this->getHelper('question')->ask($input, $output, $question);
        
        $configuration->getGithub()->setUsername($username);

        // Lets fetch my repositories
        $output->writeln("");
        $output->writeln("Creating...");
        $repo = $client->api('repo')->create(
                $name,
                $description,
                $homepage,
                in_array($private, ['y', 'Y']) ? false : true,
                $organization == 'none' ? null : $organization,
                in_array($hasIssues, ['y', 'Y']) ? true : false,
                in_array($hasWiki, ['y', 'Y']) ? true : false,
                true,
                null,
                in_array($autoInit, ['y', 'Y']) ? true : false
            );

        if (!$repo || !isset($repo['full_name']) || !isset($repo['ssh_url'])) {
            throw new \RuntimeException("Error creating the repository <info>$name</info>");
        }

        if ($input->getOption('clone')) {
            $output->writeln("Cloning...");
            $message = Git::cloneRepo($repo['ssh_url']);
            if (strpos($message, 'fatal:') === 0) {
                throw new \RuntimeException($message);
            }
        }

        $output->writeln("Finished!");
        $output->writeln("");
    }
}
