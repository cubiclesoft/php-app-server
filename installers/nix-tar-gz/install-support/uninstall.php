<?php
	// Main uninstaller for Linux.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../support/cli.php";
	require_once $rootpath . "/dir_helper.php";

	// Determine the OS.
	$os = php_uname("s");
	$windows = (strtoupper(substr($os, 0, 3)) == "WIN");
	$mac = (strtoupper(substr($os, 0, 6)) == "DARWIN");

	if ($windows || $mac)  CLI::DisplayError("This script only runs on Linux.");

	// Verify that this is a package.
	$basepath = str_replace("\\", "/", rtrim(realpath($rootpath . "/../"), "/"));
	if (!file_exists($basepath . "/server.php"))  CLI::DisplayError("This application does not appear to be packaged properly.  Missing 'server.php'.");

	$appname = false;
	$dir = opendir($basepath);
	while (($file = readdir($dir)) !== false)
	{
		if (substr($file, -7) === ".phpapp")  $appname = substr($file, 0, -7);
	}
	closedir($dir);
	if ($appname === false)  CLI::DisplayError("This application does not appear to be packaged properly.  Missing the required .phpapp file.");

	$packageinfo = json_decode(file_get_contents($basepath . "/package.json"), true);
	if (!is_array($packageinfo))  CLI::DisplayError("The 'package.json' file is missing or not valid JSON.");

	// Get the user the software will generally run as.
	if (!function_exists("posix_geteuid"))  CLI::DisplayError("Not able to determine what user you are signed in as.  Is this standard Linux?");

	$uid = posix_geteuid();

	// Split the uninstall logic based on whether or not the app is being installed with root/sudo rights.
	if ($uid)
	{
		echo "Uninstalling for just your user account...\n";
		if (getenv("HOME") === false)  CLI::DisplayError("Unable to uninstall due to missing environment variable.  Expected 'HOME'.");

		$homepath = getenv("HOME");
		$homepath = rtrim($homepath, "/");

		$installpath = $homepath . "/.apps";
		@mkdir($installpath, 0700);
		@chmod($installpath, 0700);
		$installpath .= "/" . $appname;
	}
	else
	{
		echo "Uninstalling for all users...\n";

		$installpath = "/opt/" . $appname;
	}

	// Deal with the case where someone tries to run the uninstaller without the product being installed or runs it as the wrong user.
	if (!is_dir($installpath))  CLI::DisplayError("Unable to find '" . $installpath . "'.");

	// Pre-uninstall script.
	if (file_exists($installpath . "/support/pre-uninstall.php"))  system("php " . escapeshellarg($installpath . "/support/pre-uninstall.php"));

	$vendorappname = ($packageinfo["vendor"] != "" ? trim(preg_replace('/\s+/', "-", preg_replace('/[^a-zA-Z]/', " ", $packageinfo["vendor"])), "-") : "phpapp");
	if ($vendorappname === "")  $vendorappname = "phpapp";
	$vendorappname .= "-" . $appname;

	// Remove desktop file(s).
	$desktopfile = $installpath . "/install-support/" . $vendorappname . ".desktop";

	if ($uid && $packageinfo["user_desktop_icon"])  system("xdg-desktop-icon uninstall " . escapeshellarg($desktopfile));

	system("xdg-desktop-menu uninstall " . escapeshellarg($desktopfile));

	// Remove icons.
	$sizes = array(512, 256, 128, 64, 48, 32, 16);
	foreach ($sizes as $size)
	{
		system("xdg-icon-resource uninstall --size " . (int)$size . " " . escapeshellarg($vendorappname));
	}

	// Remove files.
	DirHelper::Delete($installpath);

	echo "Done.\n";
?>