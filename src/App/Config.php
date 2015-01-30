<?php

namespace MASNathan\DevTools\App;
use MASNathan\Object;

/**
 * Config class
 */
class Config extends Object
{
    /**
     * Home directory path
     * @var string
     */
    protected $homeDir;

    /**
     * Configuration File Name
     * @var string
     */
    protected $configurationFileName = '.dev-tools.conf';

    public function __construct()
    {
        /**
         * @todo
         * Windows Compatibility
         * Do not that $_SERVER['HOME'] is not available on Windows. Instead, the variable is split into $_SERVER['HOMEDRIVE'] and $_SERVER['HOMEPATH'].
         */
        if (!isset($_SERVER['HOME'])) {
            throw new \Exception("Your OS is not supported");
        }

        $this->homeDir = $_SERVER['HOME'];
        $config = $this->getFileData();
        parent::__construct($config);
    }

    public function __destruct()
    {
        $this->save();
    }

    public function __call($alias, array $args = array())
    {
        $result = parent::__call($alias, $args);

        if (!$result) {
            preg_match_all('/[A-Z][^A-Z]*/', $alias, $parts);
            $key = strtolower(implode('_', $parts[0]));

            return $this->data->$key = new parent();
        }
    }

    protected function getFileData()
    {
        if (is_file($this->homeDir . '/' . $this->configurationFileName)) {
            if ($content = file_get_contents($this->homeDir . '/' . $this->configurationFileName)) {
                if ($configurations = json_decode($content, true)) {
                    return $configurations;
                }
            }
        }
        return [];
    }

    public function save()
    {
        file_put_contents($this->homeDir . '/' . $this->configurationFileName, json_encode($this));
    }
}