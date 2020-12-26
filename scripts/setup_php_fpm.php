<?php
	// Setup PHP FPM.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	@system("/usr/bin/apt-get update");
	@system("/usr/bin/apt-get -y install php-fpm");

	if (!is_dir("/etc/php"))  CLI::DisplayError("The directory '/etc/php/' does not exist.");

	function PHPFPMPoolConfUpdate($line, &$datamap, $key, $separator, $val)
	{
		if ($line === false)
		{
			$datamap = array();

			return;
		}

		if ($val === false)  $line = ";" . $line;
		else  $line = $key . " = " . $val;

		return $line;
	}

	$dir = opendir("/etc/php");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if ($file !== "." && $file !== "..")
			{
				if (file_exists("/etc/php/" . $file . "/fpm/php.ini"))
				{
					$datamap = array(
						"max_execution_time" => "60",
						"post_max_size" => "15M",
						"upload_max_filesize" => "10M",
						"date.timezone" => date_default_timezone_get(),
						"opcache.enable" => "1",
					);

					$lines = explode("\n", trim(file_get_contents("/etc/php/" . $file . "/fpm/php.ini")));
					$lines = UpdateConfFile($lines, $datamap, " = ", ";", "UpdateConfSkipFinal");
					file_put_contents("/etc/php/" . $file . "/fpm/php.ini", implode("\n", $lines) . "\n");
				}

				if (file_exists("/etc/php/" . $file . "/fpm/pool.d/www.conf"))
				{
					$datamap = array(
						"user" => "www-data",
						"group" => "www-data",
						"listen" => "127.0.0.1:9000",
						"listen.owner" => false,
						"listen.group" => false,
						"listen.mode" => false,
						"pm" => "ondemand",
						"pm.max_children" => "50",
						"pm.start_servers" => false,
						"pm.min_spare_servers" => false,
						"pm.max_spare_servers" => false,
						"pm.process_idle_timeout" => "10s",
						"pm.max_requests" => "500",
					);

					$lines = explode("\n", trim(file_get_contents("/etc/php/" . $file . "/fpm/pool.d/www.conf")));
					$lines = UpdateConfFile($lines, $datamap, " = ", ";", "PHPFPMPoolConfUpdate");
					file_put_contents("/etc/php/" . $file . "/fpm/pool.d/www.conf", implode("\n", $lines) . "\n");
				}

				// Start the service.
				@system("service php" . $file . "-fpm restart");
			}
		}

		closedir($dir);
	}
?>