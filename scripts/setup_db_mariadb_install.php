<?php
	// Setup MariaDB.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	// NOTE:  This script installs MariaDB from a Debian package mirror since the official Ubuntu/Debian package (10.3) hangs when configuring MariaDB.
	$ver = "10.5";

	// Retrieve system information.
	if (!file_exists("/etc/os-release"))  CLI::DisplayError("The file '/etc/os-release' does not exist.  Is this a Linux system?");
	$osinfo = array();
	$lines = explode("\n", file_get_contents("/etc/os-release"));
	foreach ($lines as $line)
	{
		$pos = strpos($line, "=");
		if ($pos !== false)  $osinfo[substr($line, 0, $pos)] = substr($line, $pos + 1);
	}

	// Update the list of sources for apt.
	$datamap = array(
		"deb [arch=amd64,arm64,ppc64el] http://sfo1.mirrors.digitalocean.com/mariadb/repo/" . $ver . "/ubuntu " . $osinfo["VERSION_CODENAME"] . " main" => "",
		"deb-src http://sfo1.mirrors.digitalocean.com/mariadb/repo/" . $ver . "/ubuntu " . $osinfo["VERSION_CODENAME"] . " main" => ""
	);

	if (!file_exists("/etc/apt/sources.list"))  CLI::DisplayError("The file '/etc/apt/sources.list' does not exist.  Not actually Debian-based?");
	$lines = explode("\n", trim(file_get_contents("/etc/apt/sources.list")));
	$lines = UpdateConfFile($lines, $datamap, "");
	file_put_contents("/etc/apt/sources.list", implode("\n", $lines) . "\n");

	@system("apt-key adv --fetch-keys 'https://mariadb.org/mariadb_release_signing_key.asc'");

	@system("/usr/bin/apt-get update");
	@system("/usr/bin/apt-get -y install mariadb-server php-mysql");

	// Run the main script again to configure MariaDB.
	// A separate configuration step is necessary since php-mysql was just installed but is not available to the currently running process.
	$cmd = escapeshellarg(PHP_BINARY) . " " . escapeshellarg($rootpath . "/main.php") . " mariadb-configure";

	RunExecutable($cmd);
?>