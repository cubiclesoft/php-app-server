#!/bin/sh

# Check for root privileges.
if ! [ $(id -u) = 0 ]; then
	echo "This dependency installer requires root privileges.  Try:  sudo ./php-nix-install.sh";

	exit 1;
fi

if [ -f "/usr/bin/apt-get" ] && [ -f "/etc/debian_version" ]; then
	export DEBIAN_FRONTEND=noninteractive;

	/usr/bin/apt-get update;
	/usr/bin/apt-get -y install zenity openssl curl php-cli php-cgi php-gd php-json php-sqlite3 php-curl;

elif [ -f "/usr/bin/yum" ] && [ -f "/etc/redhat-release" ]; then
	/usr/bin/yum update;
	/usr/bin/yum -y install zenity openssl curl php-cli php-cgi php-gd php-json php-sqlite3 php-curl;

elif [ -f "/usr/bin/pacman" ] && [ -f "/etc/arch-release" ]; then
	/usr/bin/pacman -Syu;
	/usr/bin/pacman -S --needed zenity openssl curl php php-cgi php-gd php-sqlite;

else
	echo "Unknown package manager.  Only 'apt-get', 'yum', and 'pacman' are supported at this time.";

	exit 1;

fi
