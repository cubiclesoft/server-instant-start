<?php
	// Server Instant Start installer.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	$rootpath = dirname(__FILE__);

	require_once $rootpath . "/support/cli.php";
	require_once $rootpath . "/support/process_helper.php";

	// Normalize the environment.
	$prevpath = getenv("PATH");
	$path = ($prevpath === false ? "" : $prevpath . ":") . "/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin";
	putenv("PATH=" . $path);

	putenv("DEBIAN_FRONTEND=noninteractive");

	if (!file_exists("/etc/apt/sources.list"))  CLI::DisplayError("The file '/etc/apt/sources.list' does not exist.  Not actually Debian-based?");
	system("/usr/bin/apt-get update");
	system("/usr/bin/apt-get -y install software-properties-common iptables-persistent fail2ban vnstat net-tools htop openssl git wget curl php-gd php-json php-sqlite3 php-curl");

	// Now that the environment is normalized, run the main script.
	$cmd = escapeshellarg(PHP_BINARY) . " " . escapeshellarg($rootpath . "/scripts/main.php");
	for ($x = 1; $x < $argc; $x++)  $cmd .= " " . escapeshellarg($argv[$x]);
	$options = explode(" ", preg_replace('/\s+/', " ", trim(str_replace(array(",", ";"), " ", (string)getenv("INSTANT_SERVERS")))));
	foreach ($options as $opt)
	{
		if ($opt !== "")  $cmd .= " " . escapeshellarg($opt);
	}

	$procresult = ProcessHelper::StartProcess($cmd);
	if (!$procresult["success"])  CLI::DisplayError("Installation failed.", $result);

	function DumpOutput($str)
	{
		echo $str;
	}

	ProcessHelper::Wait($procresult["proc"], $procresult["pipes"], "", -1, "DumpOutput");

	echo "\nInstallation complete.\n";

	// Reboot automatically as needed.
	if (file_exists("/var/run/reboot-required"))  system("reboot");

	putenv("PATH=" . $prevpath);
?>