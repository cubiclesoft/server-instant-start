<?php
	// Setup Postfix as a sending MTA.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	@system("/usr/bin/apt-get update");
	@system("/usr/bin/apt-get -y install postfix");

	if (!is_dir("/etc/postfix"))  CLI::DisplayError("The directory '/etc/postfix/' does not exist.");

	file_put_contents("/etc/mailname", trim((string)getenv("INSTANT_EMAIL_DOMAIN")));

	$datamap = array(
		"myhostname" => trim((string)getenv("INSTANT_EMAIL_DOMAIN")),
		"myorigin" => "/etc/mailname",
		"smtpd_recipient_restrictions" => "reject_unknown_sender_domain, reject_unknown_recipient_domain, reject_unauth_pipelining, permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination",
		"smtpd_sender_restrictions" => "reject_unknown_sender_domain",
		"smtpd_relay_restrictions" => "reject_unknown_sender_domain, reject_unknown_recipient_domain, reject_unauth_pipelining, permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination",
	);

	$lines = explode("\n", trim(file_get_contents("/etc/postfix/main.cf")));
	$lines = UpdateConfFile($lines, $datamap, " = ", "#");
	file_put_contents("/etc/postfix/main.cf", implode("\n", $lines) . "\n");

	// Start the service.
	@system("service postfix restart");
?>