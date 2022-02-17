#!/bin/bash

# Calculate the absolute path of this script.
SCRIPTPATH=$(dirname "$0")

# Detect if PHP is installed.  Starting with Mac OSX 12.0 Monterey, PHP is not included with the OS.
if [ ! -x "$(command -v php)" ]; then
	# If Homebrew PHP was just installed, the system PATH might not be updated yet.
	# Requiring the user to reboot their system seems tacky, so alter the PATH instead.
	export PATH="/usr/local/bin:$PATH"
fi

if [ ! -x "$(command -v php)" ]; then
	# Detect osascript support.
	if [ -x "$(command -v osascript)" ]; then
		# Notify the user via a dialog box and ask to install Homebrew PHP.
		response=$(osascript 2>&1 <<-EOF
set Msg to "A required system component (PHP) is missing from your system." & return & return & "Would you like to install the missing component now via Homebrew?" & return & return & "Note that this process can take a very long time to complete, requires a functional Internet connection, may additionally install Homebrew and other prerequisite subcomponents, and you will need to run this installer again once the process has completed." & return & return & "Quitting now and manually installing PHP on your system first via Homebrew or MacPorts from a Terminal before running this installer is a reasonable alternative."
set Response to display dialog Msg with title "Install Missing System Component(s)?" buttons {"OK", "Quit"} default button "OK"

if button returned of Response is "OK" then
	return 0
else
	return 1
end if
EOF)

		# Check return status.
		if [ $response -eq 0 ] ; then
			# Copy the Homebrew PHP installer script somewhere safe-ish to write to and remove quarantine to prevent weird propagation issues.
			cp "$SCRIPTPATH/install-support/php-homebrew-install.sh" "/tmp/php-homebrew-install.sh"
			chmod 0755 "/tmp/php-homebrew-install.sh"
			xattr -d com.apple.quarantine "/tmp/php-homebrew-install.sh"

			# Run the Homebrew PHP installer via Terminal and exit this installer.
			osascript -e 'tell app "Terminal" to do script "/tmp/php-homebrew-install.sh"'
		fi
	else
		# Probably not Mac OSX and probably being run from a terminal for some reason.
		echo "A required system component (PHP) is missing from your system.  Please install PHP on your system to run this installer."
	fi
fi

# Verify that PHP is available and run the main installer.
if [ -x "$(command -v php)" ]; then
	php "$SCRIPTPATH/install-support/install.php"
fi
