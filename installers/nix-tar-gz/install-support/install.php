<?php
	// Main installer for Linux.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../support/cli.php";
	require_once $rootpath . "/dir_helper.php";

	$args = CLI::ParseCommandLine(array());

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

	echo "Starting " . $packageinfo["business_name"] . " " . $packageinfo["app_name"] . " " . $packageinfo["app_ver"] . " Installer";
	usleep(500000);
	echo ".";
	usleep(500000);
	echo ".";
	usleep(500000);
	echo ".";
	usleep(500000);
	echo "\n\n";

	// Have the user agree to the legal agreement.
	if (file_exists($basepath . "/" . $appname . "-license.txt"))
	{
		echo "Please read the following End User License Agreement:\n\n";
		sleep(1);

		echo file_get_contents($basepath . "/" . $appname . "-license.txt") . "\n";

		$agree = CLI::GetYesNoUserInputWithArgs($args, "i_agree", "I agree to the above", false);
		if (!$agree)
		{
			echo "Installation cancelled.\n";

			exit();
		}
	}

	// Split the installation based on whether or not the app is being installed with root/sudo rights.
	if ($uid)
	{
		echo "Installing for just your user account...\n";
		if (getenv("HOME") === false)  CLI::DisplayError("Unable to install due to missing environment variable.  Expected 'HOME'.");

		$homepath = getenv("HOME");
		$homepath = rtrim($homepath, "/");

		$installpath = $homepath . "/.apps";
		@mkdir($installpath, 0700);
		@chmod($installpath, 0700);
		$installpath .= "/" . $appname;
	}
	else
	{
		echo "Installing for all users...\n";

		$installpath = "/opt/" . $appname;
	}

	if (file_exists($installpath . "/install-support/uninstall.php"))
	{
		echo "Previous version found.  Uninstalling...\n";
		system(escapeshellarg(PHP_BINARY) . " " . escapeshellarg($installpath . "/install-support/uninstall.php"));
		echo "Now proceeding with installation...\n";
	}

	DirHelper::Delete($installpath);
	@mkdir($installpath, ($uid ? 0700 : 0755));
	@chmod($installpath, ($uid ? 0700 : 0755));

	DirHelper::Copy($basepath, $installpath, true, array($basepath . "/install.sh" => true, $basepath . "/install-support/install.php" => true));
	DirHelper::SetPermissions($installpath, false, false, ($uid ? 0700 : 0755), false, false, ($uid ? 0600 : 0644));
	@chmod($installpath . "/uninstall.sh", ($uid ? 0700 : 0755));

	function ScaleAndRegisterImage(&$data, $destwidth, $destheight, $destfile, $destname)
	{
		// GD.
		$info = getimagesizefromstring($data);
		if ($info === false)  CLI::DisplayError("Unable to load image.");
		$srcwidth = $info[0];
		$srcheight = $info[1];

		$img = imagecreatefromstring($data);
		if ($img === false)  CLI::DisplayError("Unable to load image.");

		$img2 = imagecreatetruecolor($destwidth, $destheight);
		if ($img2 === false)
		{
			imagedestroy($img);

			CLI::DisplayError("Unable to resize image.");
		}

		// Make fully transparent.
		$transparent = imagecolorallocatealpha($img2, 0, 0, 0, 127);
		imagecolortransparent($img2, $transparent);
		imagealphablending($img2, false);
		imagesavealpha($img2, true);
		imagefill($img2, 0, 0, $transparent);

		// Copy the source onto the destination, resizing in the process.
		imagecopyresampled($img2, $img, 0, 0, 0, 0, $destwidth, $destheight, $srcwidth, $srcheight);
		imagedestroy($img);

		ob_start();
		imagepng($img2);
		$data2 = ob_get_contents();
		ob_end_clean();

		imagedestroy($img2);

		file_put_contents($destfile, $data2);

		system("xdg-icon-resource install --size " . (int)$destwidth . " " . escapeshellarg($destfile) . " " . escapeshellarg($destname));
	}

	$vendorappname = ($packageinfo["vendor"] != "" ? trim(preg_replace('/\s+/', "-", preg_replace('/[^a-zA-Z]/', " ", $packageinfo["vendor"])), "-") : "phpapp");
	if ($vendorappname === "")  $vendorappname = "phpapp";
	$vendorappname .= "-" . $appname;

	// Prepare the application icon at various sizes.
	$sizes = array(512, 256, 128, 64, 48, 32, 16);
	$data = file_get_contents($basepath . "/" . $appname . ".png");
	foreach ($sizes as $size)
	{
		ScaleAndRegisterImage($data, $size, $size, $installpath . "/install-support/" . $appname . "-" . $size . "x" . $size . ".png", $vendorappname);
	}

	// Create the desktop menu entry.
	$data = array();
	$data[] = "[Desktop Entry]";
	$data[] = "Name=" . $packageinfo["app_name"];
	$data[] = "Comment=" . $packageinfo["app_name"] . " " . $packageinfo["app_ver"] . " | " . $packageinfo["app_copyright"];
	$data[] = "Icon=" . $vendorappname;
	$data[] = "Categories=" . $packageinfo["app_categories"];
	if ($packageinfo["app_keywords"] != "")  $data[] = "Keywords=" . $packageinfo["app_keywords"];

	$data[] = "Type=Application";
	$data[] = "Version=1.1";
	$data[] = "Exec=php " . escapeshellarg($installpath . "/" . $appname . ".phpapp");
	$data[] = "Path=" . $installpath;
	$data[] = "Terminal=false";
	$data[] = "StartupNotify=false";

	$desktopfile = $installpath . "/install-support/" . $vendorappname . ".desktop";
	file_put_contents($desktopfile, implode("\n", $data));

	system("xdg-desktop-menu install " . escapeshellarg($desktopfile));

	// Optional desktop icon.
	if ($uid && $packageinfo["user_desktop_icon"])  system("xdg-desktop-icon install " . escapeshellarg($desktopfile));

	// Post-install script.
	if (file_exists($installpath . "/support/post-install.php"))
	{
		echo "Finishing installation...\n";
		system("php " . escapeshellarg($installpath . "/support/post-install.php"));
	}

	echo "Done.\n";
?>