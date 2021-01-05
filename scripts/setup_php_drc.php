<?php
	// Setup Data Relay Center.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	// Install/configure PECL ev.
	@system("/usr/bin/apt-get update");
	@system("/usr/bin/apt-get -y install php-pear php-dev");

	@system("pecl channel-update pecl.php.net");
	@system("pecl install ev");

	$dir = opendir("/etc/php");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if ($file !== "." && $file !== "..")
			{
				if (file_exists("/etc/php/" . $file . "/cli/php.ini"))
				{
					file_put_contents("/etc/php/" . $file . "/cli/conf.d/20-pecl-ev.ini", "extension=ev.so\n");
				}
			}
		}

		closedir($dir);
	}

	@system("service php-data-relay-center stop");

	// Clone/update php-drc.
	if (!file_exists("/opt/php-drc/server.php"))  @system("git clone https://github.com/cubiclesoft/php-drc.git /opt/php-drc");
	else  @system("git -C /opt/php-drc pull");

	// Add known origins.
	@system("php /opt/php-drc/config.php origins add http://127.0.0.1");

	$domains = explode(" ", preg_replace('/\s+/', " ", str_replace(array(",", ";"), " ", trim((string)getenv("INSTANT_WWW_DOMAINS")))));
	foreach ($domains as $domain)
	{
		$domain = trim(preg_replace('/[.]+/', ".", preg_replace('/[^a-z0-9-.]/', "", strtolower($domain))), "-.");
		if ($domain !== "" && $domain !== "default")
		{
			@system("php /opt/php-drc/config.php origins add " . escapeshellarg("http://" . $domain));
			@system("php /opt/php-drc/config.php origins add " . escapeshellarg("https://" . $domain));
		}
	}

	// Start the system service.
	@system("php /opt/php-drc/config.php service install");
	@system("service php-data-relay-center start");
?>