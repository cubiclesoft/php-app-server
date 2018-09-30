Instructions
============

This directory contains a packaging program (package.php) and a configuration file (package.json) for preparing tar-gzipped (.tar.gz) installable packages for Mac OSX.

Open `package.json` in a text editor and fill out the various values.  Most of the fields should be obvious as to what they are for.  However, the following keys are less obvious:

* vendor - May only contain A-Z, a-z, and hyphens.  Required by the `Info.plist` to avoid name conflicts.
* app_category - A valid `LSApplicationCategoryType` from the list on [developer.apple.com](https://developer.apple.com/library/archive/documentation/General/Reference/InfoPlistKeyReference/Articles/LaunchServicesKeys.html#//apple_ref/doc/uid/TP40009250-SW8).
* user_dock_icon - A boolean indicating whether or not an icon should also be placed in the user's dock.  Users tend to prefer clean desktops, so setting this to true is generally inadvisable.  When false, Finder is launched to the /Applications directory.

Once the package information file has been filled out, save it and run (from Linux or Mac OSX is recommended):

```
php package.php
```

Assuming all the required pieces exist, the `.tar.gz` package will be generated and made ready for deployment.  The recommended icon size for the PNG icon is 512x512.  All icon (.icns) files are generated during packaging.

Don't forget to test the installation to verify that it works as expected.  Extract the installer by double-clicking on the `.tar.gz` file.  Use `Ctrl + click`, click `Open` in the menu that appears, and then click the `Open` button in the dialog that appears to launch the installer.
