<?php

namespace MASNathan\DevTools\App;

class Git
{
	static public function cloneRepo($repositoryPath)
	{
		return exec("/usr/bin/git clone $repositoryPath 2>&1");
	}
}
