<?php
	// Post-setup DKIM.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";
	require_once $rootpath2 . "/../support/process_helper.php";

	// Install OpenDKIM.
	$filename = ProcessHelper::FindExecutable("opendkim", "/usr/sbin");
	if ($filename === false)
	{
		@system("/usr/bin/apt-get update");
		@system("/usr/bin/apt-get -y install opendkim opendkim-tools");
	}

	// Configure OpenDKIM.
	$datamap = array(
		"Socket" => "                 inet:8892@localhost",
		"Canonicalization" => "       relaxed/simple",
		"Mode" => "                   sv",
		"SubDomains" => "             no",
		"AutoRestart" => "            yes",
		"AutoRestartRate" => "        10/1M",
		"Background" => "             yes",
		"DNSTimeout" => "             5",
		"SignatureAlgorithm" => "     rsa-sha256",
		"ExternalIgnoreList" => "     /etc/opendkim/trusted.hosts",
		"InternalHosts" => "          /etc/opendkim/trusted.hosts",
		"SigningTable" => "           refile:/etc/opendkim/signing.table",
		"KeyTable" => "               /etc/opendkim/key.table",
	);

	$lines = explode("\n", trim(file_get_contents("/etc/opendkim.conf")));
	$lines = UpdateConfFile($lines, $datamap, " ", "#");
	file_put_contents("/etc/opendkim.conf", trim(implode("\n", $lines)) . "\n");

	@mkdir("/etc/opendkim", 0755, true);
	@chmod("/etc/opendkim", 0755);
	@chown("/etc/opendkim", "opendkim");
	@chgrp("/etc/opendkim", "opendkim");

	@mkdir("/etc/opendkim/keys", 0700, true);
	@chmod("/etc/opendkim/keys", 0700);
	@chown("/etc/opendkim/keys", "opendkim");
	@chgrp("/etc/opendkim/keys", "opendkim");

	if (!file_exists("/etc/opendkim/trusted.hosts"))
	{
		$template = file_get_contents($rootpath2 . "/files/opendkim_trusted_hosts.txt");

		$installconfig = @json_decode(file_get_contents($rootpath2 . "/../config.dat"), true);
		if (isset($installconfig["ipv4"]) && $installconfig["ipv4"] !== "")  $template .= $installconfig["ipv4"] . "\n";
		if (isset($installconfig["ipv6"]) && $installconfig["ipv6"] !== "")  $template .= $installconfig["ipv6"] . "\n";

		file_put_contents("/etc/opendkim/trusted.hosts", $template);
		@chmod("/etc/opendkim/trusted.hosts", 0644);

		// Configure Postfix integration.
		if (file_exists("/etc/postfix/main.cf"))
		{
			// NOTE:  This might overwrite existing milters.
			$datamap = array(
				"smtpd_milters" => "inet:localhost:8892",
				"non_smtpd_milters" => "inet:localhost:8892"
			);

			$lines = explode("\n", trim(file_get_contents("/etc/postfix/main.cf")));
			$lines = UpdateConfFile($lines, $datamap, " = ", "#");
			file_put_contents("/etc/postfix/main.cf", trim(implode("\n", $lines)) . "\n");

			@system("service postfix reload");
		}
	}

	if (!file_exists("/etc/opendkim/signing.table"))
	{
		file_put_contents("/etc/opendkim/signing.table", "");
		@chmod("/etc/opendkim/signing.table", 0644);
	}

	if (!file_exists("/etc/opendkim/key.table"))
	{
		file_put_contents("/etc/opendkim/key.table", "");
		@chmod("/etc/opendkim/key.table", 0644);
	}

	// Get the command.
	$cmds = array(
		"none" => "Do nothing besides install and configure OpenDKIM + postfix",
		"create" => "Create a DKIM signing key for an email domain",
		"verify" => "Verify that a DKIM signing key is properly published in DNS"
	);

	$cmd = CLI::GetLimitedUserInputWithArgs($args, false, "Command", false, "Available commands:", $cmds, true, $suppressoutput);

	if ($cmd === "create")
	{
		$selector = CLI::GetUserInputWithArgs($args, false, "Selector", "default", "", $suppressoutput);
		$domain = CLI::GetUserInputWithArgs($args, false, "Email domain", false, "", $suppressoutput);

		@mkdir("/etc/opendkim/keys/" . $domain);
		@system("opendkim-genkey -b 2048 -d " . escapeshellarg($domain) . " -D " . escapeshellarg("/etc/opendkim/keys/" . $domain) . " -s " . escapeshellarg($selector) . " -v");
		if (!file_exists("/etc/opendkim/keys/" . $domain . "/" . $selector . ".private"))  CLI::DisplayError("An error occurred while creating the DKIM signing key.");
		@chmod("/etc/opendkim/keys/" . $domain . "/" . $selector . ".private", 0640);
		@chgrp("/etc/opendkim/keys/" . $domain . "/" . $selector . ".private", "opendkim");

		// Update the signing and key tables.
		$datamap = array(
			"*@" . $domain => "   " . $selector . "._domainkey." . $domain
		);

		$lines = explode("\n", trim(file_get_contents("/etc/opendkim/signing.table")));
		$lines = UpdateConfFile($lines, $datamap, " ", "#");
		file_put_contents("/etc/opendkim/signing.table", trim(implode("\n", $lines)) . "\n");

		$datamap = array(
			$selector . "._domainkey." . $domain => "   " . $domain . ":" . $selector . ":/etc/opendkim/keys/" . $domain . "/" . $selector . ".private"
		);

		$lines = explode("\n", trim(file_get_contents("/etc/opendkim/key.table")));
		$lines = UpdateConfFile($lines, $datamap, " ", "#");
		file_put_contents("/etc/opendkim/key.table", trim(implode("\n", $lines)) . "\n");

		@system("service opendkim reload");

		echo "\n";
		echo "Publish a TXT record for " . $domain . " as follows in DNS:\n\n";

		echo file_get_contents("/etc/opendkim/keys/" . $domain . "/" . $selector . ".txt") . "\n";
	}
	else if ($cmd === "verify")
	{
		$selector = CLI::GetUserInputWithArgs($args, false, "Selector", "default", "", $suppressoutput);
		$domain = CLI::GetUserInputWithArgs($args, false, "Email domain", false, "", $suppressoutput);

		@system("opendkim-testkey -d " . escapeshellarg($domain) . " -s " . escapeshellarg($selector) . " -vvv");
	}
?>