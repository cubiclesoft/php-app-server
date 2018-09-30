Linux Installer Instructions
============================

This directory contains a packaging program (package.php) and a configuration file (yourapp.json) for preparing tar-gzipped (.tar.gz) installable packages for Linux.

Open `install-support/php-nix-install.sh` in a text editor and adjust the system dependencies as needed (e.g. add any extra PHP extensions that are required by the application).

Rename and open `yourapp.json` in a text editor and fill out the various values.  Most of the fields should be obvious as to what they are for.  However, the following keys are less obvious:

* vendor - May only contain A-Z, a-z, and hyphens.  Required by the `xdg-utils` package to avoid name conflicts.
* app_categories - A semicolon separated list from the standard categories in the [freedesktop.org category registry](https://specifications.freedesktop.org/menu-spec/menu-spec-latest.html#category-registry).
* app_keywords - A semicolon separated list of additional keywords that a user might use to search for the application (e.g. acronyms).
* user_desktop_icon - A boolean indicating whether or not an icon should also be placed on the user's desktop.  Users tend to prefer clean desktops, so setting this to true is generally inadvisable.

Once the package information file has been filled out, save it and run (from Linux or Mac OSX is recommended):

```
php package.php
```

Assuming all the required pieces exist, the `.tar.gz` package will be generated and made ready for deployment.  The recommended icon size for the PNG icon is 512x512.  All additional sizes are generated during installation.

Don't forget to test the installation to verify that it works as expected.  The installer is run via the command `./install.sh` and the uninstaller via `./uninstall.sh`.  Use `sudo ./install.sh` to install for all users.
