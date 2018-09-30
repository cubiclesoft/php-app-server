#!/bin/sh

# Calculate the absolute path of this script.
SCRIPT=$(readlink -f "$0")
SCRIPTPATH=$(dirname "$SCRIPT")

echo "Installing prerequisite packages (requires root/sudo)...";
chmod 775 "$SCRIPTPATH/install-support/php-nix-install.sh"

# This code intentionally falls through if the user cancels.
# This script is designed to be idempotent and MAY still work if all of the prerequisite binaries are already installed.
if [ -x "$(command -v pkexec)" ]; then
	pkexec "$SCRIPTPATH/install-support/php-nix-install.sh"
else
	gksudo "$SCRIPTPATH/install-support/php-nix-install.sh"
fi

echo "Running installer...";
php install-support/install.php
