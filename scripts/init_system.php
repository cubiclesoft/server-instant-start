<?php
	// System initializer.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!defined("SYSTEM_FILES") || !class_exists("CLI", false))
	{
		echo "This file is intended to be included via another file that correctly initializes the environment.";

		exit();
	}

	$rootpath2 = dirname(__FILE__);

	require_once $rootpath2 . "/functions.php";

	// Modify various kernel options for improved uptime and security.
	// Source:  http://cubicspot.blogspot.com/2016/06/elegant-iptables-rules-for-your-linux.html
	if (!file_exists("/etc/sysctl.conf"))  CLI::DisplayError("The file '/etc/sysctl.conf' does not exist.  Is this a Linux system?");

	$datamap = array(
		"kernel.panic" => "600",
		"net.ipv4.conf.default.rp_filter" => "1",
		"net.ipv4.conf.all.rp_filter" => "1",
		"net.ipv4.tcp_syncookies" => "1",
		"net.ipv4.icmp_echo_ignore_broadcasts" => "1",
		"net.ipv4.conf.all.accept_redirects" => "0",
		"net.ipv6.conf.all.accept_redirects" => "0",
		"net.ipv4.conf.all.secure_redirects" => "1",
		"net.ipv4.conf.all.send_redirects" => "0",
		"net.ipv4.conf.all.accept_source_route" => "0",
		"net.ipv6.conf.all.accept_source_route" => "0",
	);

	$lines = explode("\n", trim(file_get_contents("/etc/sysctl.conf")));
	$lines = UpdateConfFile($lines, $datamap, " = ");
	file_put_contents("/etc/sysctl.conf", implode("\n", $lines) . "\n");


	// Set iptables rules via external files.
	if (file_exists($rootpath2 . "/files/firewall_ipv4.txt") && file_exists("/etc/iptables/rules.v4"))
	{
		file_put_contents("/etc/iptables/rules.v4", file_get_contents($rootpath2 . "/files/firewall_ipv4.txt"));

		system("iptables-restore < /etc/iptables/rules.v4");
	}

	if (file_exists($rootpath2 . "/files/firewall_ipv6.txt") && file_exists("/etc/iptables/rules.v6"))
	{
		file_put_contents("/etc/iptables/rules.v6", file_get_contents($rootpath2 . "/files/firewall_ipv6.txt"));

		system("iptables-restore < /etc/iptables/rules.v6");
	}


	// Set OS file handle limits.
	// Source:  https://superuser.com/questions/1200539/cannot-increase-open-file-limit-past-4096-ubuntu
	if (!file_exists("/etc/security/limits.conf"))  CLI::DisplayError("The file '/etc/security/limits.conf' does not exist.  Is this a Linux system?");

	$datamap = array(
		"root soft nofile" => SYSTEM_FILES,
		"root hard nofile" => SYSTEM_FILES,
	);

	$lines = explode("\n", trim(file_get_contents("/etc/security/limits.conf")));
	$lines = UpdateConfFile($lines, $datamap, " ");
	file_put_contents("/etc/security/limits.conf", implode("\n", $lines) . "\n");

	// Handle systemd.
	$datamap = array(
		"DefaultLimitNOFILE" => SYSTEM_FILES,
	);

	if (file_exists("/etc/systemd/system.conf"))
	{
		$lines = explode("\n", trim(file_get_contents("/etc/systemd/system.conf")));
		$lines = UpdateConfFile($lines, $datamap, "=");
		file_put_contents("/etc/systemd/system.conf", implode("\n", $lines) . "\n");
	}

	if (file_exists("/etc/systemd/user.conf"))
	{
		$lines = explode("\n", trim(file_get_contents("/etc/systemd/user.conf")));
		$lines = UpdateConfFile($lines, $datamap, "=");
		file_put_contents("/etc/systemd/user.conf", implode("\n", $lines) . "\n");
	}

	if (file_exists("/etc/systemd/system.conf") || file_exists("/etc/systemd/user.conf"))  @system("systemctl daemon-reexec");

	// Update PAM.
	if (!file_exists("/etc/pam.d/common-session"))  CLI::DisplayError("The file '/etc/pam.d/common-session' does not exist.  Is this a Linux system?");
	if (!file_exists("/etc/pam.d/common-session-noninteractive"))  CLI::DisplayError("The file '/etc/pam.d/common-session-noninteractive' does not exist.  Is this a Linux system?");

	$datamap = array(
		'/session\s+required\s+pam_limits\.so/' => "session required pam_limits.so",
	);

	$lines = explode("\n", trim(file_get_contents("/etc/pam.d/common-session")));
	$lines = UpdateConfFileRegEx($lines, $datamap);
	file_put_contents("/etc/pam.d/common-session", implode("\n", $lines) . "\n");

	$lines = explode("\n", trim(file_get_contents("/etc/pam.d/common-session-noninteractive")));
	$lines = UpdateConfFileRegEx($lines, $datamap);
	file_put_contents("/etc/pam.d/common-session-noninteractive", implode("\n", $lines) . "\n");


	// Set the system timezone.
	@system("timedatectl set-timezone " . escapeshellarg(date_default_timezone_get()));


	// Create a group for SFTP users.
	@system("addgroup sftp-users");

	// Create a baseline scripts run directory.
	@mkdir("/var/scripts");
	@chgrp("/var/scripts", "sftp-users");
	@chmod("/var/scripts", 02770);

	// Set up the apt auto-updater script.
	if (!file_exists("/var/scripts/apt_updater.php"))
	{
		file_put_contents("/var/scripts/apt_updater.php", file_get_contents($rootpath2 . "/files/apt_updater.php"));
		@chgrp("/var/scripts/apt_updater.php", "sftp-users");
		@chmod("/var/scripts/apt_updater.php", 0660);
	}

	$ts = time() - 60;
	$filename = "/root/crontab_" . time() . ".txt";
	@system("crontab -l > " . escapeshellarg($filename));
	$data = trim(file_get_contents($filename));
	if (strpos($data, "/var/scripts/apt_updater.php") === false)
	{
		$data .= "\n\n";
		$data .= "# Automatic updater.\n";
		$data .= date("i H", $ts) . " * * * /usr/bin/php /var/scripts/apt_updater.php >/tmp/cron_apt_updater.log 2>&1\n";

		$data = trim($data) . "\n";
		file_put_contents($filename, $data);
		@system("crontab " . escapeshellarg($filename));
	}

	@unlink($filename);


	// Change the hostname.
	$hostname = trim((string)getenv("INSTANT_HOSTNAME"));
	if ($hostname !== "")
	{
		if (!file_exists("/etc/hostname"))  CLI::DisplayError("The file '/etc/hostname' does not exist.  Is this a Linux system?");
		if (!file_exists("/etc/hosts"))  CLI::DisplayError("The file '/etc/hosts' does not exist.  Is this a Linux system?");

		$prevhostname = trim(@system("hostname"));
		if ($prevhostname !== $hostname)
		{
			file_put_contents("/etc/hostname", $hostname);

			$lines = explode("\n", trim(file_get_contents("/etc/hosts")));
			foreach ($lines as $num => $line)
			{
				if (substr($line, 0, 4) === "127.")  $lines[$num] = str_replace($prevhostname, $hostname, $line);
			}

			file_put_contents("/etc/hosts", implode("\n", $lines) . "\n");

			// Update cloud-init configuration (if it exists).
			if (file_exists("/etc/cloud/cloud.cfg"))
			{
				$datamap = array(
					'/preserve_hostname:\s*false/' => "preserve_hostname: true",
				);

				$lines = explode("\n", trim(file_get_contents("/etc/cloud/cloud.cfg")));
				$lines = UpdateConfFileRegEx($lines, $datamap);
				file_put_contents("/etc/cloud/cloud.cfg", implode("\n", $lines) . "\n");
			}

			// Force a reboot.
			@touch("/var/run/reboot-required");
		}
	}
?>