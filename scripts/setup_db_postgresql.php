<?php
	// Setup PostgreSQL.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	@system("/usr/bin/apt-get update");
	@system("/usr/bin/apt-get -y install postgresql postgresql-contrib php-pgsql");
?>