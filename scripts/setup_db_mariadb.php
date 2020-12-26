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
	require_once $rootpath2 . "/../support/random.php";
	require_once $rootpath2 . "/../support/process_helper.php";

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

	// Stop the MariaDB server.
	@system("service mysql stop");

	// Set up a temporary directory for a manual run.
	@mkdir("/var/run/mysqld", 0755, true);
	@chown("/var/run/mysqld", "mysql");
	@chgrp("/var/run/mysqld", "mysql");

	// Start the MariaDB server process in the background without privileges.
	$cmd = escapeshellarg(ProcessHelper::FindExecutable("mysqld", "/usr/sbin")) . " --skip-grant-tables --skip-networking";
	$options = array(
		"stdin" => false,
		"stdout" => false,
		"stderr" => false
	);

	$procresult = ProcessHelper::StartProcess($cmd, $options);
	if (!$procresult["success"])  CLI::DisplayError("Unable to start MariaDB server.", $procresult);

	// Wait for the UNIX socket to come up.
	echo "Waiting for MariaDB UNIX socket...";
	fflush(STDOUT);
	$retries = 30;
	do
	{
		sleep(1);
		echo ".";
		fflush(STDOUT);

		$found = false;

		if (file_exists("/var/run/mysqld/mysqld.sock"))  $found = true;

		$retries--;
	} while ($retries && !$found);

	$failedmsg = false;

	if (!$retries)
	{
		echo "FAILED.\n";

		$failedmsg = "MariaDB failed to start within 30 seconds.";
	}
	else
	{
		sleep(1);
		echo "SUCCESS.\n";

		// Generate a password for the root user.
		$installconfig = @json_decode(file_get_contents($rootpath2 . "/../config.dat"), true);
		if (!is_array($installconfig))  $installconfig = array("accounts" => array());
		if (!isset($installconfig["accounts"]["mariadb"]))  $installconfig["accounts"]["mariadb"] = array("root" => MakePassword());
		file_put_contents($rootpath2 . "/../config.dat", json_encode($installconfig, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		@chmod($rootpath2 . "/../config.dat", 0600);

		// Try to connect to the database.
		require_once $rootpath2 . "/../support/db.php";
		require_once $rootpath2 . "/../support/db_mysql_lite.php";

		try
		{
			$db = new CSDB_mysql_lite();

			$db->SetDebug(true);

			$db->Connect("mysql:unix_socket");

			// Set the root password, secure the root account, and commit privilege changes.
			echo "Setting MariaDB root password, securing the root account, and committing privilege changes...\n";
			$db->Query(false, "FLUSH PRIVILEGES");
			$db->Query(false, "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password AS PASSWORD(?)", $installconfig["accounts"]["mariadb"]["root"]);
			$db->Query(false, "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1')");
			$db->Query(false, "DELETE FROM mysql.user WHERE User=''");
			$db->Query(false, "FLUSH PRIVILEGES");

			$db->Disconnect();
		}
		catch (Exception $e)
		{
			CLI::DisplayError("An error occurred while attempting to reset the root password.  " . $e->getMessage(), false, false);

			$failedmsg = "The MariaDB connection or a query failed.";
		}
	}

	// Stop the privilege-less MariaDB server.
	echo "Terminating child MariaDB process...";
	fflush(STDOUT);
	ProcessHelper::TerminateProcess($procresult["pid"], true, false);

	do
	{
		sleep(1);
		echo ".";
		fflush(STDOUT);

		$pinfo = @proc_get_status($procresult["proc"]);
	} while ($pinfo["running"]);

	echo "Done.\n";

	if ($failedmsg !== false)  CLI::DisplayError($failedmsg);

	// Start the MariaDB server normally.
	@system("service mysql start");
?>