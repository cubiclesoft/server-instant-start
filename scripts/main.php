<?php
	// Server Instant Start main installation script.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// This script is called by 'install.php', which sets up the correct baseline environment (minus the timezone).
	$rootpath = dirname(__FILE__);

	require_once $rootpath . "/../support/cli.php";
	require_once $rootpath . "/../support/random.php";
	require_once $rootpath . "/../support/dir_helper.php";
	require_once $rootpath . "/functions.php";

	// Set the PHP timezone.
	$tz = trim(getenv("TZ"));
	if ($tz === "")  $tz = "Etc/UTC";
	date_default_timezone_set($tz);

	// Configure the OS.
	define("SYSTEM_FILES", "8192");

	if (in_array("init-system", $argv))
	{
		require_once $rootpath . "/init_system.php";
		@system("service fail2ban restart");
	}

	// Configure PHP CLI.
	if (in_array("php-cli", $argv))  require_once $rootpath . "/setup_php_cli.php";

	// Install and configure Nginx.
	if (in_array("nginx", $argv))  require_once $rootpath . "/setup_nginx.php";

	// Install and configure PHP FPM.
	if (in_array("php-fpm", $argv))  require_once $rootpath . "/setup_php_fpm.php";

	// Install and configure postfix.
	if (in_array("email-sendonly", $argv))  require_once $rootpath . "/setup_email_sendonly.php";

	// Install and configure MariaDB/MySQL.
	if (in_array("mariadb", $argv))  require_once $rootpath . "/setup_db_mariadb.php";
	else if (in_array("mysql", $argv))  require_once $rootpath . "/setup_db_mysql.php";

	// Install and configure PostgreSQL.
	if (in_array("postgresql", $argv) || in_array("postgres", $argv) || in_array("pgsql", $argv))  require_once $rootpath . "/setup_db_postgresql.php";

	// Load the final configuration (if any).
	$installconfig = @json_decode(file_get_contents($rootpath . "/../config.dat"), true);
	if (!is_array($installconfig))  $installconfig = array("accounts" => array());
	$ipaddr = trim((string)getenv("PUBLIC_IPV4"));
	if ($ipaddr !== "")  $installconfig["ipv4"] = $ipaddr;
	$ipaddr = trim((string)getenv("PUBLIC_IPV6"));
	if ($ipaddr !== "")  $installconfig["ipv6"] = $ipaddr;
	file_put_contents($rootpath . "/../config.dat", json_encode($installconfig, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	@chmod($rootpath . "/../config.dat", 0600);

	// Generate a README file for the root directory.
	$data = "Server Instant Start completed successfully.\n\n";
	$data .= "Configuration from '" . realpath($rootpath . "/../config.dat") . "':\n\n";
	$data .= json_encode($installconfig, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

	file_put_contents("/root/README-ServerInstantStart", $data);
	@chmod("/root/README-ServerInstantStart", 0600);

	echo "-------------\n" . $data . "\n-------------\n\n";
?>