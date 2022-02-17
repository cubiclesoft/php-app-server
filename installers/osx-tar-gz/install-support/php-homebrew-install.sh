#!/bin/bash

# Calculate the absolute path of this script.
SCRIPTPATH=$(dirname "$0")

# Detect if PHP is installed.  Starting with Mac OSX 12.0 Monterey, PHP is not included with the OS.
if [ ! -x "$(command -v php)" ]; then
	# Install Homebrew.
	if [ ! -x "$(command -v brew)" ]; then
		echo "Attempting to install Homebrew..."
		/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

		if [ ! -x "$(command -v brew)" ]; then
			echo " "
			echo "Homebrew installation attempt failed."
			echo "Please manually install PHP on your system and then try running the application installer again."
		fi
	fi

	# Install PHP.
	if [ -x "$(command -v brew)" ]; then
		echo "Attempting to install PHP via Homebrew..."
		brew install php

		if [ ! -x "$(command -v php)" ]; then
			echo " "
			echo "Homebrew is installed on your system but the Homebrew PHP installation failed."
			echo "Please manually install PHP on your system by running 'brew install php' from a Terminal and then try running the application installer again."
		fi
	fi

	echo " "
fi

# Report result.
if [ -x "$(command -v php)" ]; then
	echo "PHP has been successfully installed on this system.  Run the main application installer to install the application."
else
	echo "PHP is not installed on this system."
fi
