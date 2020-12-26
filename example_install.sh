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
