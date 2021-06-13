<?php
	// Binary preparation tool for PHP App Server.
	// (C) 2021 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/cli.php";
	require_once $rootpath . "/win_ico.php";
	require_once $rootpath . "/win_pe_utils.php";

	// Generate application ICO file from PNG.
	$icofilename = false;
	$path = realpath($rootpath . "/../../../");
	$dir = opendir($path);
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if (substr($file, -4) === ".png")
			{
				$pngfilename = $path . "/" . $file;
				$icofilename = $path . "/" . substr($file, 0, -4) . ".ico";

				if (!file_exists($icofilename))
				{
					$data = file_get_contents($pngfilename);
					$result = WinICO::Create($data);
					if (!$result["success"])  CLI::DisplayError("", $result);

					file_put_contents($icofilename, $result["data"]);
				}
			}
		}

		closedir($dir);
	}

	$icodata = ($icofilename !== false ? file_get_contents($icofilename) : false);

	if ($icodata !== false)
	{
		$result = WinICO::Parse($icodata);
		if (!$result["success"])  CLI::DisplayError("The file '" . $icofilename . "' does not appear to be a valid icon (ICO) file.", $result);
		else if ($result["type"] !== WinICO::TYPE_ICO)  CLI::DisplayError("The file '" . $icofilename . "' does not appear to be a valid icon (ICO) file.  Type mismatch (" . $result["type"] . ").", false);

		$icodata = $result;
	}

	function PreparePHP($filename, $newfilename, $elevated)
	{
		global $icodata;

		if (file_exists($filename))
		{
			// If the original file is being overwritten, then create a backup if it does not exist.
			if ($filename === $newfilename && !file_exists($filename . ".bak"))  copy($filename, $filename . ".bak");

			// If the original file has a backup, use the backup instead.
			if (file_exists($filename . ".bak"))  $filename = $filename . ".bak";

			$filename = realpath($filename);

			$data = file_get_contents($filename);

			$winpe = new WinPEFile();
			$result = $winpe->Parse($data);
			if (!$result["success"])  CLI::DisplayError("Unable to parse '" . $filename . "'.", $result);

			// Modify the app icon and/or XML manifest.
			if ($icodata !== false || $elevated)
			{
				// Remove code signing certificates (if any).
				$result = $winpe->ClearCertificates($data);
				if (!$result["success"])  CLI::DisplayError("Unable to remove code signing certificates.", $result);

				// Modify the app icon.
				if ($icodata !== false)
				{
					$result = WinPEUtils::SetIconResource($winpe, $data, $icodata);
					if (!$result["success"])  CLI::DisplayError("Unable to set icon resource.", $result);
				}

				// Modify the XML manifest.
				if ($elevated)
				{
					// Extract the manifest from the resources table.
					$result = $winpe->FindResource(WinPEFile::RT_MANIFEST, true, true);
					if ($result === false)  CLI::DisplayError("XML manifest resource not found in '" . $filename . "'.");

					$manifestdata = $result["entry"]["data"];
					$manifestdata = str_replace("asInvoker", "requireAdministrator", $manifestdata);

					$winpe->OverwriteResourceData($data, $result["num"], $manifestdata);
				}

				$result = $winpe->SavePEResourcesDirectory($data);
				if (!$result["success"])  CLI::DisplayError("Unable to save modified PE resources directory.", $result);

				$winpe->UpdateChecksum($data, true);
			}

			file_put_contents($newfilename, $data);
		}
	}

	PreparePHP($rootpath . "/../php-win-32/php.exe", $rootpath . "/../php-win-32/php.exe", false);
	PreparePHP($rootpath . "/../php-win-32/php.exe", $rootpath . "/../php-win-32/php-elevated.exe", true);
	PreparePHP($rootpath . "/../php-win-32/php-cgi.exe", $rootpath . "/../php-win-32/php-cgi.exe", false);
	PreparePHP($rootpath . "/../php-win-32/php-win.exe", $rootpath . "/../php-win-32/php-win.exe", false);
	PreparePHP($rootpath . "/../php-win-32/php-win.exe", $rootpath . "/../php-win-32/php-win-elevated.exe", true);

	PreparePHP($rootpath . "/../php-win-64/php.exe", $rootpath . "/../php-win-64/php.exe", false);
	PreparePHP($rootpath . "/../php-win-64/php.exe", $rootpath . "/../php-win-64/php-elevated.exe", true);
	PreparePHP($rootpath . "/../php-win-64/php-cgi.exe", $rootpath . "/../php-win-64/php-cgi.exe", false);
	PreparePHP($rootpath . "/../php-win-64/php-win.exe", $rootpath . "/../php-win-64/php-win.exe", false);
	PreparePHP($rootpath . "/../php-win-64/php-win.exe", $rootpath . "/../php-win-64/php-win-elevated.exe", true);
?>