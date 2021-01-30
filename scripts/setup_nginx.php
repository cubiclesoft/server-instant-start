<?php
	// Setup Nginx.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";
	require_once $rootpath2 . "/../support/dir_helper.php";
	require_once $rootpath2 . "/../support/process_helper.php";

	// Deny www-data from creating/running crontabs.  Prevents advanced persistent threats (APTs).
	$datamap = array(
		"www-data" => "",
	);

	$lines = (file_exists("/etc/cron.deny") ? explode("\n", trim(file_get_contents("/etc/cron.deny"))) : array());
	$lines = UpdateConfFile($lines, $datamap, "");
	file_put_contents("/etc/cron.deny", implode("\n", $lines) . "\n");


	// NOTE:  The Ubuntu/Debian nginx package tends to lag far behind the latest version and makes several really bad configuration decisions.

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
		"deb http://nginx.org/packages/ubuntu/ " . $osinfo["VERSION_CODENAME"] . " nginx" => "",
		"deb-src http://nginx.org/packages/ubuntu/ " . $osinfo["VERSION_CODENAME"] . " nginx" => ""
	);

	if (!file_exists("/etc/apt/sources.list"))  CLI::DisplayError("The file '/etc/apt/sources.list' does not exist.  Not actually Debian-based?");
	$lines = explode("\n", trim(file_get_contents("/etc/apt/sources.list")));
	$lines = UpdateConfFile($lines, $datamap, "");
	file_put_contents("/etc/apt/sources.list", implode("\n", $lines) . "\n");

	// Run apt update to gather the required GPG key.
	ob_start();
	@system("/usr/bin/apt-get update 2>&1");
	$data = ob_get_contents();
	ob_end_clean();

	$lines = explode("\n", $data);
	foreach ($lines as $line)
	{
		$pos = strpos($line, "://nginx.org/packages/ubuntu");
		$pos2 = strpos($line, "NO_PUBKEY");
		if ($pos !== false && $pos2 !== false)
		{
			$pubkey = trim(substr($line, $pos2 + 10));
			@system("/usr/bin/apt-key adv --keyserver keyserver.ubuntu.com --recv-keys " . $pubkey);
			@system("/usr/bin/apt-get update");
		}
	}

	// Install Nginx.
	@system("/usr/bin/apt-get -y install nginx");

	// Create a stronger ephemeral key exchange for SSL (this process can take several minutes).
	if (!file_exists("/var/local/dhparam2048.pem"))
	{
		$cmd = ProcessHelper::FindExecutable("openssl", "/usr/bin");
		if ($cmd === false)  CLI::DisplayError("Unable to locate OpenSSL.");

		RunExecutable(escapeshellarg($cmd) . " dhparam -out /var/local/dhparam2048.pem 2048");
	}

	chmod("/var/local/dhparam2048.pem", 0400);

	// The main Nginx configuration file needs so much work that simply overwriting it is the best option.
	if (!file_exists("/etc/nginx/global/php-local.conf"))  file_put_contents("/etc/nginx/nginx.conf", file_get_contents($rootpath2 . "/files/nginx_core.txt"));

	// Set up shared global files.  This makes certain configurations easier and safer (e.g. PHP FPM).
	@mkdir("/etc/nginx/global");
	if (!file_exists("/etc/nginx/global/php-local.conf"))  file_put_contents("/etc/nginx/global/php-local.conf", file_get_contents($rootpath2 . "/files/nginx_global_php-local.txt"));
	if (!file_exists("/etc/nginx/global/php-proxy.conf"))  file_put_contents("/etc/nginx/global/php-proxy.conf", file_get_contents($rootpath2 . "/files/nginx_global_php-proxy.txt"));
	if (!file_exists("/etc/nginx/global/restrictions.conf"))  file_put_contents("/etc/nginx/global/restrictions.conf", file_get_contents($rootpath2 . "/files/nginx_global_restrictions.txt"));
	if (!file_exists("/etc/nginx/global/location-default.conf"))  file_put_contents("/etc/nginx/global/location-default.conf", file_get_contents($rootpath2 . "/files/nginx_global_location-default.txt"));
	if (!file_exists("/etc/nginx/global/location-cms.conf"))  file_put_contents("/etc/nginx/global/location-cms.conf", file_get_contents($rootpath2 . "/files/nginx_global_location-cms.txt"));
	if (!file_exists("/etc/nginx/global/location-drc.conf"))  file_put_contents("/etc/nginx/global/location-drc.conf", file_get_contents($rootpath2 . "/files/nginx_global_location-drc.txt"));

	// Well, the Ubuntu nginx package does one thing correctly.  So let's borrow that idea.
	@mkdir("/etc/nginx/sites-available");
	@mkdir("/etc/nginx/sites-enabled");
	if (!file_exists("/etc/nginx/sites-available/default.conf"))  file_put_contents("/etc/nginx/sites-available/default.conf", file_get_contents($rootpath2 . "/files/nginx_site_default.txt"));
	if (!file_exists("/etc/nginx/sites-enabled/default.conf"))  @system("ln -s ../sites-available/default.conf /etc/nginx/sites-enabled/default.conf");

	// Create the web root.
	@mkdir("/var/www");
	@chgrp("/var/www", "sftp-users");
	@chmod("/var/www", 02775);

	// Set up domains.
	$domains = explode(" ", preg_replace('/\s+/', " ", str_replace(array(",", ";"), " ", trim((string)getenv("INSTANT_WWW_DOMAINS")))));
	$first = true;
	foreach ($domains as $domain)
	{
		$domain = trim(preg_replace('/[.]+/', ".", preg_replace('/[^a-z0-9-.]/', "", strtolower($domain))), "-.");
		if ($domain !== "" && $domain !== "default")
		{
			if (!file_exists("/etc/nginx/sites-available/" . $domain . ".conf"))  file_put_contents("/etc/nginx/sites-available/" . $domain . ".conf", str_replace("domain.com", $domain, file_get_contents($rootpath2 . "/files/nginx_site.txt")));
			if (!file_exists("/etc/nginx/sites-enabled/" . $domain . ".conf"))  @system("ln -s " . escapeshellarg("../sites-available/" . $domain . ".conf") . " " . escapeshellarg("/etc/nginx/sites-enabled/" . $domain . ".conf"));

			@mkdir("/var/www/" . $domain . "/public_html", 0777, true);
			@mkdir("/var/www/" . $domain . "/protected_html", 0777, true);
			DirHelper::SetPermissions("/var/www/" . $domain, false, "sftp-users", 02775, false, "sftp-users", 0664);

			// Link the default path with the first domain.
			if ($first)
			{
				if (!is_dir("/var/www/default"))  @system("ln -s " . escapeshellarg("/var/www/" . $domain) . " /var/www/default");

				$first = false;
			}
		}
	}

	if ($first)
	{
		@mkdir("/var/www/default/public_html", 0777, true);
		@mkdir("/var/www/default/protected_html", 0777, true);
		DirHelper::SetPermissions("/var/www/default", false, "sftp-users", 02775, false, "sftp-users", 0664);
	}

	@mkdir("/var/www/shared/.well-known/acme-challenge", 0777, true);
	DirHelper::SetPermissions("/var/www/shared", false, "sftp-users", 02775, false, "sftp-users", 0664);

	// Start the service.
	@system("service nginx restart");
?>