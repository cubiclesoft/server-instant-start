<?php
	// Server Instant Start post-installation setup.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	$rootpath = dirname(__FILE__);

	require_once $rootpath . "/support/cli.php";
	require_once $rootpath . "/support/random.php";
	require_once $rootpath . "/support/dir_helper.php";
	require_once $rootpath . "/scripts/functions.php";

	// Normalize the environment.
	$prevpath = getenv("PATH");
	$path = ($prevpath === false ? "" : $prevpath . ":") . "/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin";
	putenv("PATH=" . $path);

	putenv("DEBIAN_FRONTEND=noninteractive");

	// Process the command-line options.
	$options = array(
		"shortmap" => array(
			"?" => "help"
		),
		"rules" => array(
			"help" => array("arg" => false)
		),
		"allow_opts_after_param" => false
	);
	$args = CLI::ParseCommandLine($options);

	if (isset($args["opts"]["help"]))
	{
		echo "The post-installation tool\n";
		echo "Purpose:  Apply additional post-installation options from the command-line.\n";
		echo "\n";
		echo "This tool is question/answer enabled.  Just running it will provide a guided interface.  It can also be run entirely from the command-line if you know all the answers.\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options] [cmdgroup cmd [cmdoptions]]\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $args["file"] . "\n";
		echo "\tphp " . $args["file"] . " https nginx yourdomain.com www.yourdomain.com\n";
		echo "\tphp " . $args["file"] . " dkim create default yourdomain.com\n";
		echo "\tphp " . $args["file"] . " dkim verify default yourdomain.com\n";

		exit();
	}

	$origargs = $args;
	$suppressoutput = (isset($args["opts"]["suppressoutput"]) && $args["opts"]["suppressoutput"]);

	// Get the command group.
	$cmdgroups = array();
	$dir = opendir($rootpath . "/scripts");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if (substr($file, 0, 11) !== "post_setup_" || substr($file, -4) !== ".php")  continue;

			$key = substr($file, 11, -4);

			$cmdgroups[$key] = $key;
		}

		closedir($dir);
	}

	if (!count($cmdgroups))  CLI::DisplayError("No command groups were found in '" . $rootpath . "/scripts'.");

	$cmdgroup = CLI::GetLimitedUserInputWithArgs($args, false, "Command group", false, "Available command groups:", $cmdgroups, true, $suppressoutput);

	require_once $rootpath . "/scripts/post_setup_" . $cmdgroup . ".php";

	echo "\nDone.\n";

	putenv("PATH=" . $prevpath);
?>