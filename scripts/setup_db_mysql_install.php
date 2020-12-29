<?php
	// Setup MySQL.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	@system("/usr/bin/apt-get update");
	@system("/usr/bin/apt-get -y install mysql-server php-mysql");

	// Run the main script again to configure MySQL.
	// A separate configuration step is necessary since php-mysql was just installed but is not available to the currently running process.
	$cmd = escapeshellarg(PHP_BINARY) . " " . escapeshellarg($rootpath . "/main.php") . " mysql-configure";

	RunExecutable($cmd);
?>