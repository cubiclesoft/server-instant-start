Server Instant Start
====================

Spin up a fully configured Ubuntu/Debian-based web server in under 5 minutes with Nginx (w/ HTTPS), PHP FPM, Postfix, OpenDKIM, MySQL/MariaDB, PostgreSQL, and more.  Deploy your web application too.

Instant Start is useful for setting up an entire server with minimal effort.  Quickly install all components of a server in just a couple of minutes:  A well-rounded OS configuration plus optional configuration of web server, email sending capabilities, a scripting language, and database(s).  The contents of and knowledge contained in this repository come from responsibly managing many Linux-based web servers for over a decade.

Only using Instant Start on a brand new server is highly recommended.  Any Debian-based Linux distribution will probably work fine.  Failure to use Instant Start on a newly created system may result in damage to existing configuration files and/or data loss.

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/) [![Discord](https://img.shields.io/discord/777282089980526602?label=chat&logo=discord)](https://cubiclesoft.com/product-support/github/)

Features
--------

* A simple set of scripts that automatically install and configure several software products.
* Your new server is ready to use in just a couple of minutes.
* Nearly zero configuration required (see below).
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for rapid deployment.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Getting Started
---------------

Open the following in a new tab to start creating a Droplet on DigitalOcean:

[![Deploy to DO](https://mp-assets1.sfo2.digitaloceanspaces.com/deploy-to-do/do-btn-blue.svg)](https://cloud.digitalocean.com/droplets/new?size=s-1vcpu-1gb&distro=ubuntu&options=ipv6)

(Read the Non-DigitalOcean Setup section below for other VPS providers.)

Under "Select additional options" check the checkbox that says "User data".  Copy and paste the following script into the box that appears and modify it as you see fit:

```sh
#!/bin/sh

export DEBIAN_FRONTEND=noninteractive;

apt-get update;
apt-get -y dist-upgrade;
apt-get -y install openssl git wget curl php-cli;

export PUBLIC_IPV4=$(curl -s http://169.254.169.254/metadata/v1/interfaces/public/0/ipv4/address);
export PUBLIC_IPV6=$(curl -s http://169.254.169.254/metadata/v1/interfaces/public/0/ipv6/address);

# A list of timezones can be found here:  https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
# Or automatic:  https://geoip.ubuntu.com/lookup
export TZ="";

# Set the hostname.  What to name this server?
export INSTANT_HOSTNAME="";

# Set the configured domain(s) to use (e.g. yourdomain.com, anotherdomain.com).
export INSTANT_EMAIL_DOMAIN="";
export INSTANT_WWW_DOMAINS="";

# Select servers to install (if any).
# Options:  nginx, php-fpm, email-sendonly, mariadb, mysql, postgresql
export INSTANT_SERVERS="";

cd /root/;

# Optionally clone useful but unrelated CubicleSoft network and server management software.
# NOTE:  Some software products require separate installation/configuration (e.g. Cloud Backup is not magical).
#git clone https://github.com/cubiclesoft/net-test.git;
#git clone https://github.com/cubiclesoft/network-speedtest-cli.git;
#git clone https://github.com/cubiclesoft/php-ssh.git;
#git clone https://github.com/cubiclesoft/php-ssl-certs.git;
#git clone https://github.com/cubiclesoft/cloud-backup.git;

# Clone and run Server Instant Start.
git clone https://github.com/cubiclesoft/server-instant-start.git;
cd server-instant-start;

php install.php init-system php-cli;
```

Update the `export TZ=` line with your current timezone.  This will be used to set the timezone of the Droplet and associated software (e.g. PHP) so that dates and times are stored and displayed as expected.  The timezone also affects any cron jobs that are set up.  Leave it blank for `UTC +0000`.

The other `export` options are optional.  Fill out the desired configuration and uncomment/include any additional software you want to install/configure later.

Even after the Droplet becomes available, it can be a few minutes before the server is fully configured.  To watch the installation/configuration progress, run the following command from a SSH terminal:

```
tail -f /var/log/cloud-init-output.log
```

When the server installation is finished, a file called `/root/README-ServerInstantStart` will be created which contains credentials for various server resources (e.g. MariaDB root password).  SSH or SFTP is required to read the file.

```
cat /root/README-ServerInstantStart
```

After installation, configure DNS to point at the IP address(es) of the new system.  Then run the post-install script to set up HTTPS and/or DKIM:

```
cd /root/server-instant-start
php post-install.php https nginx yourdomain.com www.yourdomain.com
php post-install.php dkim create default yourdomain.com
php post-install.php dkim verify default yourdomain.com
```

Key Locations
-------------

* `/var/www/yourdomain.com/public_html` - The public web root for a domain.
* `/var/www/yourdomain.com/protected_html` - A private directory for a domain.
* `/var/scripts` - Various automation scripts (e.g. cron jobs).

Installed Software
------------------

Always installed and configured:

* PHP CLI.
* fail2ban.  Slows down attackers.
* iptables-persistent.  Sane default firewall rules.
* net-tools.  netstat, etc.
* vnstat.  Tracks monthly network transfer.
* htop.  A much better top.
* Fully automated system update script (except major OS upgrades).
* PHP extensions (cURL, JSON, PDO sqlite, GD).

Optionally installed and configured:

* Postfix.
* OpenDKIM.  Post-install only.
* Nginx.
* Let's Encrypt.  Post-install only.
* PHP FPM.
* PHP extensions (PDO mysql, PDO postgres).
* MariaDB/MySQL.
* PostgreSQL.

Non-DigitalOcean Setup
----------------------

To run this software, you need an Ubuntu/Debian OS distribution on a Virual Private Server (VPS) or dedicated host.  Providers like DigitalOcean, OVH, AWS, Azure, etc. make it easy to spin up a VPS.

The shell script under the Getting Started section is also in `example_install.sh`.  For non-DigitalOcean hosts, just upload files, manually modify `PUBLIC_IPV4` and `PUBLIC_IPV6` in `example_install.sh` with correct IP address(es), perform a `chmod 755 example_install.sh`, and then execute the script as the `root` user `./example_install.sh`.

DigitalOcean is primarily for quickly setting up a temporary Internet-facing server, which is good for trying out new things like Server Instant Start, testing some software in isolation, or for short-lived projects.  Web hosting service providers abound but most of those are shared hosts with little control.  A Virtual Private Server (VPS), which is what DigitalOcean mostly offers/provides, is something between shared hosting and cloud/dedicated hosting.  Droplets are intended to be cheap, short-lived VPS instances that are created and destroyed as needed.  Even though Droplets weren't really ever intended for normal web hosting, quite a few people use them that way.

Running a VPS (or similar) comes with responsbilities.  The biggest one is making sure that the system is secure, which means that the system remains fully patched because it won't automatically be done for you.  Server Instant Start solves a number of configuration management problems by performing an opinionated installation that attempts to create a generally self-securing setup.  For example, it installs a PHP script that runs `apt-get dist-upgrade` with automatic rebooting as needed (e.g. kernel updates) and configures cron to automatically run that script every single day.

If the intent is to run a server long-term, I highly recommend using an [OVH VPS](https://www.ovhcloud.com/en/vps/cheap-vps/) instead of DigitalOcean since OVH offers a lot more hardware and network transfer for less cost but slightly less comprehensive technical support.

More Information
----------------

The PHP installation script `install.php` aims to be idempotent.  That is, if it is run again intentionally or by accident, it will result in the same output.

A system group called `sftp-users` is created during the installation process.  The `setgid` attribute is set on various key locations so that any user assigned to the group can easily create new files in a team setting.  Just assign the `sftp-users` group to members of your team.
