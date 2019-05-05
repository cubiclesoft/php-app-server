<?php
	// Main package preparation script for Linux.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

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

	require_once $rootpath . "/install-support/www/support/dir_helper.php";
	require_once $rootpath . "/support/apple_icns.php";

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

	// Prepare platform icons.
	if (!file_exists($rootpath . "/install_icon.icns"))
	{
		$data = file_get_contents($rootpath . "/install_icon.png");
		$result = AppleICNS::Create($data);
		if (!$result["success"])  echo "Unable to convert '" . $rootpath . "/install_icon.png' to an Apple ICNS file.\n";
		else  file_put_contents($rootpath . "/install_icon.icns", $result["data"]);
	}

	if (!file_exists($basepath . "/" . $appname . ".icns"))
	{
		$data = file_get_contents($basepath . "/" . $appname . ".png");
		$result = AppleICNS::Create($data);
		if (!$result["success"])  echo "Unable to convert '" . $basepath . "/" . $appname . ".png' to an Apple ICNS file.\n";
		else  file_put_contents($basepath . "/" . $appname . ".icns", $result["data"]);
	}

	// Prepare the staging area.
	echo "Preparing staging area...\n";
	$stagingpath = $rootpath . "/" . $packageinfo["app_name"] . " " . $packageinfo["app_ver"] . " Installer.app";
	DirHelper::Delete($stagingpath);
	mkdir($stagingpath, 0775);
	mkdir($stagingpath . "/Contents/MacOS/app/Contents/MacOS", 0755, true);
	mkdir($stagingpath . "/Contents/MacOS/app/Contents/Resources", 0755, true);
	mkdir($stagingpath . "/Contents/Resources", 0755, true);

	copy($basepath . "/server.php", $stagingpath . "/Contents/MacOS/app/Contents/MacOS/server.php");

	copy($basepath . "/" . $appname . ".phpapp", $stagingpath . "/Contents/MacOS/app/Contents/MacOS/" . $appname . ".phpapp");
	copy($basepath . "/" . $appname . "-license.txt", $stagingpath . "/Contents/MacOS/app/Contents/MacOS/" . $appname . "-license.txt");
	copy($basepath . "/" . $appname . ".icns", $stagingpath . "/Contents/MacOS/app/Contents/Resources/" . $appname . ".icns");

	DirHelper::Copy($basepath . "/extensions", $stagingpath . "/Contents/MacOS/app/Contents/MacOS/extensions");
	DirHelper::Copy($basepath . "/support", $stagingpath . "/Contents/MacOS/app/Contents/MacOS/support", true, array($basepath . "/support/windows" => true, $basepath . "/support/linux" => true));
	DirHelper::Copy($basepath . "/www", $stagingpath . "/Contents/MacOS/app/Contents/MacOS/www");

	DirHelper::Copy($rootpath . "/install-support", $stagingpath . "/Contents/MacOS/install-support");
	copy($rootpath . "/install.sh", $stagingpath . "/Contents/MacOS/install.sh");
	chmod($stagingpath . "/Contents/MacOS/install.sh", 0775);
	copy($packagefile, $stagingpath . "/Contents/MacOS/app/Contents/MacOS/package.json");
	copy($rootpath . "/install_icon.icns", $stagingpath . "/Contents/Resources/install.icns");

	// Generate the 'Info.plist' file for the app.
	$data = "<" . "?xml version=\"1.0\" encoding=\"UTF-8\"?" . ">\n";
	$data .= "<!DOCTYPE plist PUBLIC \"-//Apple Computer//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n";
	$data .= "<plist version=\"1.0\">\n";
	$data .= "<dict>\n";
	$data .= "\t<key>CFBundleName</key>\n";
	$data .= "\t<string>" . htmlspecialchars($packageinfo["app_name"]) . "</string>\n";
	$data .= "\t<key>CFBundleDisplayName</key>\n";
	$data .= "\t<string>" . htmlspecialchars($packageinfo["app_name"]) . "</string>\n";
	$data .= "\t<key>CFBundleIdentifier</key>\n";
	$data .= "\t<string>" . htmlspecialchars(trim(preg_replace('/[^A-Za-z0-9.]/', "-", $packageinfo["vendor"] . "." . $appname), ".-")) . "</string>\n";
	$data .= "\t<key>CFBundleVersion</key>\n";
	$data .= "\t<string>" . htmlspecialchars($packageinfo["app_ver"]) . "</string>\n";
	$data .= "\t<key>CFBundleShortVersionString</key>\n";
	$data .= "\t<string>" . htmlspecialchars($packageinfo["app_ver"]) . "</string>\n";
	$data .= "\t<key>CFBundleGetInfoString</key>\n";
	$data .= "\t<string>" . trim(htmlspecialchars($packageinfo["business_name"] . " " . $packageinfo["app_name"] . " " . $packageinfo["app_ver"])) . "</string>\n";
	$data .= "\t<key>CFBundlePackageType</key>\n";
	$data .= "\t<string>APPL</string>\n";
	$data .= "\t<key>CFBundleExecutable</key>\n";
	$data .= "\t<string>" . htmlspecialchars($appname . ".phpapp") . "</string>\n";
	$data .= "\t<key>CFBundleIconFile</key>\n";
	$data .= "\t<string>" . htmlspecialchars($appname) . "</string>\n";
	$data .= "\t<key>CFBundleInfoDictionaryVersion</key>\n";
	$data .= "\t<string>6.0</string>\n";
	if ($packageinfo["app_category"] != "")
	{
		$data .= "\t<key>LSApplicationCategoryType</key>\n";
		$data .= "\t<string>" . htmlspecialchars($packageinfo["app_category"]) . "</string>\n";
	}
	$data .= "</dict>\n";
	$data .= "</plist>\n";

	file_put_contents($stagingpath . "/Contents/MacOS/app/Contents/Info.plist", $data);

	// Generate the 'Info.plist' file for the installer.
	$data = "<" . "?xml version=\"1.0\" encoding=\"UTF-8\"?" . ">\n";
	$data .= "<!DOCTYPE plist PUBLIC \"-//Apple Computer//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n";
	$data .= "<plist version=\"1.0\">\n";
	$data .= "<dict>\n";
	$data .= "\t<key>CFBundleName</key>\n";
	$data .= "\t<string>" . htmlspecialchars($packageinfo["app_name"] . " Installer") . "</string>\n";
	$data .= "\t<key>CFBundleDisplayName</key>\n";
	$data .= "\t<string>" . htmlspecialchars($packageinfo["app_name"] . " Installer") . "</string>\n";
	$data .= "\t<key>CFBundleIdentifier</key>\n";
	$data .= "\t<string>com.phpapp.installer</string>\n";
	$data .= "\t<key>CFBundleVersion</key>\n";
	$data .= "\t<string>" . htmlspecialchars($packageinfo["app_ver"]) . "</string>\n";
	$data .= "\t<key>CFBundleShortVersionString</key>\n";
	$data .= "\t<string>" . htmlspecialchars($packageinfo["app_ver"]) . "</string>\n";
	$data .= "\t<key>CFBundleGetInfoString</key>\n";
	$data .= "\t<string>" . trim(htmlspecialchars($packageinfo["business_name"] . " " . $packageinfo["app_name"] . " " . $packageinfo["app_ver"])) . " Installer</string>\n";
	$data .= "\t<key>CFBundlePackageType</key>\n";
	$data .= "\t<string>APPL</string>\n";
	$data .= "\t<key>CFBundleExecutable</key>\n";
	$data .= "\t<string>install.sh</string>\n";
	$data .= "\t<key>CFBundleIconFile</key>\n";
	$data .= "\t<string>install</string>\n";
	$data .= "\t<key>CFBundleInfoDictionaryVersion</key>\n";
	$data .= "\t<string>6.0</string>\n";
	$data .= "</dict>\n";
	$data .= "</plist>\n";

	file_put_contents($stagingpath . "/Contents/Info.plist", $data);

	echo "Generating " . $appname . "-" . $packageinfo["app_ver"] . "-osx.tar.gz...\n";
	chdir($rootpath);
	@unlink($appname . "-" . $packageinfo["app_ver"] . "-osx.tar.gz");
	system("tar czvf " . escapeshellarg($appname . "-" . $packageinfo["app_ver"] . "-osx.tar.gz") . ($mac ? " " : " --owner=0 --group=0 ") . escapeshellarg($packageinfo["app_name"] . " " . $packageinfo["app_ver"] . " Installer.app/"));

	echo "Cleaning up...\n";
	DirHelper::Delete($stagingpath);

	echo "Done.\n";
?>