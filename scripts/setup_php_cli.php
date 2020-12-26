<?php
	// Setup PHP CLI.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	if (!is_dir("/etc/php"))  CLI::DisplayError("The directory '/etc/php/' does not exist.");

	$dir = opendir("/etc/php");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if ($file !== "." && $file !== ".." && file_exists("/etc/php/" . $file . "/cli/php.ini"))
			{
				$datamap = array(
					"date.timezone" => date_default_timezone_get()
				);

				$lines = explode("\n", trim(file_get_contents("/etc/php/" . $file . "/cli/php.ini")));
				$lines = UpdateConfFile($lines, $datamap, " = ", ";", "UpdateConfSkipFinal");
				file_put_contents("/etc/php/" . $file . "/cli/php.ini", implode("\n", $lines) . "\n");
			}
		}

		closedir($dir);
	}
?>