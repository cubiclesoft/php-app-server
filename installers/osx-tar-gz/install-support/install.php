<?php
	// Main installer startup for Mac OSX.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/cli.php";
	require_once $rootpath . "/process_helper.php";

	function DisplayInitErrorDialog($title, $msg, $result = false)
	{
		global $rootpath;

		$os = php_uname("s");
		$windows = (strtoupper(substr($os, 0, 3)) == "WIN");
		$mac = (strtoupper(substr($os, 0, 6)) == "DARWIN");

		if ($mac && ($execfile = ProcessHelper::FindExecutable("osascript", "/usr/bin")) !== false)  system(escapeshellarg($execfile) . " -e " . escapeshellarg("display dialog \"" . $msg . "\" with title \"" . $title . "\" buttons {\"OK\"} default button \"OK\""));
		else if (($execfile = ProcessHelper::FindExecutable("zenity", "/usr/bin")) !== false)  system(escapeshellarg($execfile) . " --error --title " . escapeshellarg($title) . " --text " . escapeshellarg($msg) . " --width=400 2>/dev/null");

		CLI::DisplayError($title . " - " . $msg, $result);
	}

	// Determine the OS.
	$os = php_uname("s");
	$windows = (strtoupper(substr($os, 0, 3)) == "WIN");
	$mac = (strtoupper(substr($os, 0, 6)) == "DARWIN");

	if (!$mac)  DisplayInitErrorDialog("Invalid OS", "This script only runs on Mac OSX.");

	// Verify that this is a package.
	if (!is_dir($rootpath . "/../app/"))  DisplayInitErrorDialog("Missing Application", "This application does not appear to be packaged properly.  Missing 'app' directory.");
	$basepath = str_replace("\\", "/", rtrim(realpath($rootpath . "/../app/"), "/"));
	if (!file_exists($basepath . "/Contents/MacOS/server.php"))  DisplayInitErrorDialog("Missing App Server", "This application does not appear to be packaged properly.  Missing 'server.php'.");

	require_once $basepath . "/Contents/MacOS/support/pas_functions.php";

	$appname = false;
	$dir = opendir($basepath . "/Contents/MacOS");
	while (($file = readdir($dir)) !== false)
	{
		if (substr($file, -7) === ".phpapp")  $appname = substr($file, 0, -7);
	}
	closedir($dir);
	if ($appname === false)  DisplayInitErrorDialog("Missing App Startup", "This application does not appear to be packaged properly.  Missing the required .phpapp file.");

	$packageinfo = json_decode(file_get_contents($basepath . "/Contents/MacOS/package.json"), true);
	if (!is_array($packageinfo))  DisplayInitErrorDialog("Missing/Invalid Configuration", "The 'package.json' file is missing or not valid JSON.");

	// Configuration for the installer server.
	$options = array(
		"business" => $packageinfo["business_name"],
		"appname" => $packageinfo["app_name"],
		"host" => "127.0.0.1",
		"port" => 0,
		"quitdelay" => 6,
		"exts" => $rootpath . "/extensions",
		"www" => $rootpath . "/www"
	);

	// Start the server.
	$rootpath = $basepath . "/Contents/MacOS";
	$result = PAS_StartServer($options);

	// Start the user's default web browser.
	PAS_LaunchWebBrowser($result["url"]);

	// Wait for the server to terminate.
	do
	{
		sleep(1);

		$pinfo = @proc_get_status($result["procinfo"]["proc"]);
	} while ($pinfo["running"]);
?>