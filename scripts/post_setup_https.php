<?php
	// Post-setup HTTPS.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	// Install/update dehydrated.
	if (!file_exists("/root/dehydrated/dehydrated"))  @system("git clone https://github.com/dehydrated-io/dehydrated.git /root/dehydrated");
	else  @system("git -C /root/dehydrated pull");

	@chmod("/root/dehydrated/dehydrated", 0750);

	@mkdir("/etc/dehydrated");
	@chmod("/etc/dehydrated", 0750);

	if (!file_exists("/etc/dehydrated/config"))  @copy("/root/dehydrated/docs/examples/config", "/etc/dehydrated/config");
	@chmod("/etc/dehydrated/config", 0640);

	$datamap = array(
		"WELLKNOWN" => "\"/var/www/shared/.well-known/acme-challenge\""
	);

	$lines = explode("\n", trim(file_get_contents("/etc/dehydrated/config")));
	$lines = UpdateConfFile($lines, $datamap, "=", "#");
	file_put_contents("/etc/dehydrated/config", implode("\n", $lines) . "\n");

	if (!file_exists("/etc/dehydrated/domains.txt"))  file_put_contents("/etc/dehydrated/domains.txt", "");
	@chmod("/etc/dehydrated/domains.txt", 0640);

	// Set up dehydrated to auto-renew certificates.
	$ts = time() - 60;
	$filename = "/root/crontab_" . time() . ".txt";
	@system("crontab -l > " . escapeshellarg($filename));
	$data = trim(file_get_contents($filename));
	if (strpos($data, "/root/dehydrated/dehydrated") === false)
	{
		$data .= "\n\n";
		$data .= "# Automatic certificate renewal.\n";
		$data .= date("i H", $ts) . " * * * /usr/bin/timeout 10m /root/dehydrated/dehydrated --cron >/tmp/cron_dehydrated_renew.log 2>&1\n";

		$data = trim($data) . "\n";
		file_put_contents($filename, $data);
		@system("crontab " . escapeshellarg($filename));
	}

	@unlink($filename);

	@system("/root/dehydrated/dehydrated --register --accept-terms");

	// Get the command.
	$cmds = array(
		"none" => "Do nothing besides install/update dehydrated and register an account",
		"certonly" => "Get a certificate but configure your web server manually later",
		"nginx" => "Get a certificate and attempt to auto-configure Nginx"
	);

	$cmd = CLI::GetLimitedUserInputWithArgs($args, false, "Command", false, "Available commands:", $cmds, true, $suppressoutput);

	if ($cmd !== "none")
	{
		if (count($args["params"]))  $domains = $args["params"];
		else
		{
			$domains = CLI::GetUserInputWithArgs($args, false, "Domains", false, "Enter one or more space/comma-separated domains to associate with this certificate.  The first domain will be used for the name of the certificate on disk and used for locating any auto-configuration files.", $suppressoutput);
			$domains = explode(" ", preg_replace('/\s+/', " ", trim(str_replace(array(",", ";"), " ", $domains))));
		}

		$certname = array_shift($domains);

		// Update domains.txt.
		$datamap = array(
			$certname => implode(" ", $domains)
		);

		$lines = explode("\n", trim(file_get_contents("/etc/dehydrated/domains.txt")));
		$lines = UpdateConfFile($lines, $datamap, " ", "#");
		file_put_contents("/etc/dehydrated/domains.txt", trim(implode("\n", $lines)) . "\n");

		@system("/root/dehydrated/dehydrated --cron");

		// Attempt to update Nginx config for the first domain.
		if ($cmd === "nginx" && file_exists("/etc/dehydrated/certs/" . $certname . "/fullchain.pem") && file_exists("/etc/nginx/sites-available/" . $certname . ".conf"))
		{
			$datamap = array(
				'/listen 443 ssl http2;/' => "    listen 443 ssl http2;",
				'/listen \[::\]:443 ssl http2;/' => "    listen [::]:443 ssl http2;",
				'/ssl_certificate\s+/' => "    ssl_certificate          /etc/dehydrated/certs/" . $certname . "/fullchain.pem;",
				'/ssl_certificate_key\s+/' => "    ssl_certificate_key      /etc/dehydrated/certs/" . $certname . "/privkey.pem;",
				'/ssl_trusted_certificate\s+/' => "    ssl_trusted_certificate  /etc/dehydrated/certs/" . $certname . "/chain.pem;",
			);

			$lines = explode("\n", trim(file_get_contents("/etc/nginx/sites-available/" . $certname . ".conf")));
			$lines = UpdateConfFileRegEx($lines, $datamap, false);
			file_put_contents("/etc/nginx/sites-available/" . $certname . ".conf", trim(implode("\n", $lines)) . "\n");

			@system("service nginx reload");
		}
	}
?>