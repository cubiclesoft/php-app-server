#!/bin/bash

# Calculate the absolute path of this script.
SCRIPTPATH=$(dirname "$0")

php "$SCRIPTPATH/install-support/install.php"
