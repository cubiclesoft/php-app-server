#!/bin/bash

# Calculate the absolute path of this script.
SCRIPTPATH=$(dirname "$0")

# Use osascript to launch a Terminal to start the installer.
/usr/bin/osascript -e 'tell app "Terminal" to do script "php \"'"$SCRIPTPATH"'/install-support/install.php\""'

/bin/sleep 600
