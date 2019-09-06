<?php
	// Main package preparation script for Linux.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	// Determine the OS.
	$os = php_uname("s");
	$windows = (strtoupper(substr($os, 0, 3)) == "WIN");
	$mac = (strtoupper(substr($os, 0, 6)) == "DARWIN");

	if ($windows)  echo "This script might not work as expected on Windows.  Running this script via a reasonable Bash port (e.g. git bash) is recommended for tar/gzip support.\n\n";

	require_once $rootpath . "/install-support/dir_helper.php";

	// Find a .phpapp file.
	$basepath = str_replace("\\", "/", realpath($rootpath . "/../../"));
	$appname = false;
	$dir = opendir($basepath);
	while (($file = readdir($dir)) !== false)
	{
		if (substr($file, -7) === ".phpapp")  $appname = substr($file, 0, -7);
	}
	closedir($dir);
	if ($appname === false)
	{
		echo "Unable to find a .phpapp file.\n";

		exit();
	}

	// Load the package info.
	$packagefile = $rootpath . "/" . $appname . ".json";
	if (!file_exists($packagefile))
	{
		echo "The file '" . $packagefile . "' does not exist.\n";

		exit();
	}

	$packageinfo = json_decode(file_get_contents($packagefile), true);
	if (!is_array($packageinfo))
	{
		echo "The '" . $packagefile . "' file is not valid JSON.\n";

		exit();
	}

	// Prepare the staging area.
	echo "Preparing staging area...\n";
	$stagingpath = $rootpath . "/" . $packageinfo["app_filename"] . "-" . $packageinfo["app_ver"];
	DirHelper::Delete($stagingpath);
	mkdir($stagingpath, 0775);

	copy($basepath . "/server.php", $stagingpath . "/server.php");

	copy($basepath . "/" . $appname . ".phpapp", $stagingpath . "/" . $appname . ".phpapp");
	copy($basepath . "/" . $appname . "-license.txt", $stagingpath . "/" . $appname . "-license.txt");
	copy($basepath . "/" . $appname . ".png", $stagingpath . "/" . $appname . ".png");

	DirHelper::Copy($basepath . "/extensions", $stagingpath . "/extensions");
	DirHelper::Copy($basepath . "/support", $stagingpath . "/support", true, array($basepath . "/support/windows" => true, $basepath . "/support/mac" => true));
	DirHelper::Copy($basepath . "/www", $stagingpath . "/www");

	DirHelper::Copy($rootpath . "/install-support", $stagingpath . "/install-support");
	copy($rootpath . "/install.sh", $stagingpath . "/install.sh");
	chmod($stagingpath . "/install.sh", 0775);
	copy($rootpath . "/uninstall.sh", $stagingpath . "/uninstall.sh");
	chmod($stagingpath . "/uninstall.sh", 0775);
	copy($packagefile, $stagingpath . "/package.json");

	echo "Generating " . $packageinfo["app_filename"] . "-" . $packageinfo["app_ver"] . "-linux.tar.gz...\n";
	chdir($rootpath);
	@unlink($packageinfo["app_filename"] . "-" . $packageinfo["app_ver"] . "-linux.tar.gz");
	system("tar czvf " . escapeshellarg($packageinfo["app_filename"] . "-" . $packageinfo["app_ver"] . "-linux.tar.gz") . ($mac ? " " : " --owner=0 --group=0 ") . escapeshellarg($packageinfo["app_filename"] . "-" . $packageinfo["app_ver"] . "/"));

	echo "Cleaning up...\n";
	DirHelper::Delete($stagingpath);

	echo "Done.\n";
?>