<?php
	// Binary preparation tool for PHP.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	chdir($rootpath);

	function PreparePHP($filename, $newfilename)
	{
		if (file_exists($filename))
		{
			@unlink("php.exe.manifest");
			system("mt.exe -inputresource:" . $filename . ";#1 -out:php.exe.manifest");

			$data = file_get_contents("php.exe.manifest");
			$data = str_replace("asInvoker", "requireAdministrator", $data);
			file_put_contents("php.exe.manifest", $data);

			@copy($filename, $newfilename);

			system("mt.exe -manifest php.exe.manifest -outputresource:" . $newfilename . ";#1");
		}
	}

	PreparePHP("..\\php-win-32\\php.exe", "..\\php-win-32\\php-elevated.exe");
	PreparePHP("..\\php-win-32\\php-win.exe", "..\\php-win-32\\php-win-elevated.exe");

	PreparePHP("..\\php-win-64\\php.exe", "..\\php-win-64\\php-elevated.exe");
	PreparePHP("..\\php-win-64\\php-win.exe", "..\\php-win-64\\php-win-elevated.exe");

	@unlink("php.exe.manifest");
?>