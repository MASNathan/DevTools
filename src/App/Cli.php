<?php

namespace MASNathan\DevTools\App;

use Symfony\Component\Console\Application,
    MASNathan\DevTools\Commands\Github;

/**
 * Base Client Application class
 */
class Cli extends Application
{
    /**
     * DevTools client application constructor.
     */
    public function __construct() {
        parent::__construct('MASNathan Dev Tools', '1.0');
 
        $this->addCommands(array(
            new Github\SetupCommand(),
            new Github\ListCommand(),
            new Github\CloneCommand(),
        ));
    }
}
