<?php
	// License Update/Activation Tool.  Requires License Server.
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
	require_once $rootpath . "/pas_functions.php";

	// Process the command-line options.
	$options = array(
		"shortmap" => array(
			"d" => "debug",
			"f" => "file",
			"s" => "suppressoutput",
			"?" => "help"
		),
		"rules" => array(
			"debug" => array("arg" => false),
			"file" => array("arg" => true),
			"suppressoutput" => array("arg" => false),
			"help" => array("arg" => false),
			"zfile" => array("arg" => true)
		),
		"allow_opts_after_param" => false
	);
	$args = CLI::ParseCommandLine($options);

	if (isset($args["opts"]["help"]))
	{
		echo "The license activation command-line tool for PHP App Server\n";
		echo "Purpose:  Stores license information from the PHP-based License Server with activation options for PHP App Server applications.\n";
		echo "\n";
		echo "This tool is question/answer enabled.  Just running it will provide a guided interface.  It can also be run entirely from the command-line if you know all the answers.\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options] [cmd [cmdoptions]]\n";
		echo "Options:\n";
		echo "\t-f   Alternate license file location/name.  Default is 'license.json' in the parent directory.\n";
		echo "\t-d   Enable raw API debug mode.  Dumps the raw data sent and received on the wire.\n";
		echo "\t-s   Suppress most output.  Useful for capturing JSON output.\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $args["file"] . "\n";
		echo "\tphp " . $args["file"] . " update -serial_num 'xxxx-xxxx-xxxx-xxxx' -userinfo 'test@cubiclesoft.com' -password 'support-password'\n";
		echo "\tphp " . $args["file"] . " -s activate -url 'https://cubiclesoft.com/some-product/license/?ver=1&serial_num=xxxx-xxxx-xxxx-xxxx&userinfo=test@cubiclesoft.com&activate=1'\n";

		exit();
	}

	$debug = (isset($args["opts"]["debug"]) && $args["opts"]["debug"]);
	$suppressoutput = (isset($args["opts"]["suppressoutput"]) && $args["opts"]["suppressoutput"]);
	$origargs = $args;
	$abnormalexit = true;

	function DisplayResult($result)
	{
		global $origargs, $abnormalexit;

		$abnormalexit = false;

		echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

		if (isset($origargs["opts"]["zfile"]))  file_put_contents($origargs["opts"]["zfile"], json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		exit();
	}

	function AbnormalExitHandler()
	{
		global $abnormalexit, $origargs;

		if ($abnormalexit)  DisplayResult(array("success" => false, "error" => "The application licensing process exited abnormally.  Not enough parameters were passed or the process was user terminated.", "errorcode" => "unexpected_exit", "info" => array("php" => PHP_BINARY, "args" => $origargs)));
	}

	register_shutdown_function("AbnormalExitHandler");

	// License file location.
	if (isset($args["opts"]["file"]))  $filename = $args["opts"]["file"];
	else  $filename = realpath($rootpath . "/..") . "/license.json";
	$filename = str_replace("\\", "/", $filename);

	// Get the parent directory and normalize the path.
	$pos = strrpos($filename, "/");
	if ($pos !== false)  $path = substr($filename, 0, $pos);
	else
	{
		$path = str_replace("\\", "/", (getcwd() !== false ? getcwd() : $rootpath));

		$filename = $path . "/" . $filename;
	}

	@mkdir($path, 0755, true);

	// Attempt to directly write to the file.
	$fp = @fopen($filename, "ab");

	// If the file is not able to be written to, start an elevated/sudo process with the same options.
//if (!isset($args["opts"]["zfile"]))  $fp = false;
	if ($fp === false)
	{
		// Make sure this isn't a loop of some sort.
		if (isset($args["opts"]["zfile"]))  DisplayResult(array("success" => false, "error" => "Unable to create license file at '" . $filename . "'.", "errorcode" => "invalid_filename"));

		$cmd = escapeshellarg(PAS_GetPHPBinary(true));

		$cmd .= " " . escapeshellarg($rootpath . "/elevate.php");
		if ($suppressoutput)  $cmd .= " -detach";
		$cmd .= " " . escapeshellarg(PAS_GetPHPBinary($suppressoutput));
		$cmd .= " " . escapeshellarg(__FILE__);
		$cmd .= " -file " . escapeshellarg($filename);
		if ($debug)  $cmd .= " -d";
		if ($suppressoutput)  $cmd .= " -s";

		PAS_InitStartupFile($sdir, $sfile);

		$cmd .= " -zfile " . escapeshellarg($sfile);

		foreach ($args["params"] as $param)  $cmd .= " " . escapeshellarg($param);
//echo $cmd . "\n";

		$options2 = array(
			"stdin" => false,
			"stdout" => false,
			"stderr" => false,
			"dir" => $rootpath
		);

		$result = ProcessHelper::StartProcess($cmd, $options2);
//var_dump($result);
		if (!$result["success"])
		{
			@unlink($sfile);
			@rmdir($sdir);

			DisplayResult($result);
		}

		$proc = $result["proc"];

		// Wait for the file to be written to.
//echo "Waiting...";
		$ts = time();
		do
		{
			usleep(250000);

			$sinfo = @json_decode(file_get_contents($sfile), true);
			$pinfo = @proc_get_status($proc);
//echo ".";
		} while (!is_array($sinfo) && $pinfo["running"]);
//echo "\n";

//var_dump($sinfo);
//var_dump($pinfo);
		$sinfo = @json_decode(file_get_contents($sfile), true);
		@unlink($sfile);
		@rmdir($sdir);
		if (!is_array($sinfo))  exit();

		DisplayResult($sinfo);
	}

	fclose($fp);

	// Get the command.
	$cmds = array(
		"update" => "Update license",
		"remove" => "Remove license",
		"activate" => "Activate license",
		"deactivate" => "Deactivate license"
	);

	$cmd = CLI::GetLimitedUserInputWithArgs($args, false, "Command", false, "Available commands:", $cmds, true, $suppressoutput);

	function ReinitArgs($newargs)
	{
		global $args;

		// Process the parameters.
		$options = array(
			"shortmap" => array(
				"?" => "help"
			),
			"rules" => array(
			)
		);

		foreach ($newargs as $arg)  $options["rules"][$arg] = array("arg" => true, "multiple" => true);
		$options["rules"]["help"] = array("arg" => false);

		$args = CLI::ParseCommandLine($options, array_merge(array(""), $args["params"]));

		if (isset($args["opts"]["help"]))  DisplayResult(array("success" => true, "options" => array_keys($options["rules"])));
	}

	if ($cmd === "update")
	{
		ReinitArgs(array("serial_num", "userinfo", "password"));

		$serialnum = CLI::GetUserInputWithArgs($args, "serial_num", "Serial number", false, "", $suppressoutput);
		$userinfo = CLI::GetUserInputWithArgs($args, "userinfo", "User information", false, "The next question is the string that is associated with the license (e.g. an email address).", $suppressoutput);
		$password = CLI::GetUserInputWithArgs($args, "password", "Support password", "", "The next question allows a product support password to be stored with the license for easy access to product updates and support.", $suppressoutput);

		$data = array(
			"serial_num" => $serialnum,
			"userinfo" => $userinfo,
			"password" => $password
		);

		file_put_contents($filename, json_encode($data, JSON_UNESCAPED_SLASHES));

		DisplayResult(array("success" => true, "filename" => $filename));
	}
	else if ($cmd === "remove")
	{
		unlink($filename);

		DisplayResult(array("success" => true));
	}
	else if ($cmd === "activate" || $cmd === "deactivate")
	{
		ReinitArgs(array("url"));

		$url = CLI::GetUserInputWithArgs($args, "url", ($cmd === "activate" ? "Activation URL" : "Deactivation URL"), false, "", $suppressoutput);

		// Saves a few KB but loses cookies, redirects, etc.
		require_once $rootpath . "/http.php";

		$options = array(
			"headers" => array(
				"Accept" => "text/html, application/xhtml+xml, */*",
				"Accept-Language" => "en-us,en;q=0.5",
				"Cache-Control" => "max-age=0",
				"User-Agent" => HTTP::GetUserAgent("firefox")
			)
		);

		$result = HTTP::RetrieveWebpage($url, $options);
		if (!$result["success"])  DisplayResult($result);

		$data = @json_decode($result["body"], true);
		if (!is_array($data))  DisplayResult(array("success" => false, "error" => "Unable to decode server response as JSON.", "errorcode" => "invalid_data", "info" => $result["body"]));
		if (!isset($data["success"]))  DisplayResult(array("success" => false, "error" => "Invalid license server response.  Expected 'success'.", "errorcode" => "invalid_json", "info" => $result["body"]));
		if (!$data["success"])  DisplayResult($data);

		if (!isset($data["serial_num"]) || !isset($data["userinfo"]))  DisplayResult(array("success" => false, "error" => "Invalid license server response.  Expected 'serial_num' and 'userinfo'.", "errorcode" => "invalid_json", "info" => $result["body"]));

		if ($cmd === "activate")
		{
			file_put_contents($filename, json_encode($data, JSON_UNESCAPED_SLASHES));

			DisplayResult(array("success" => true, "filename" => $filename));
		}
		else
		{
			unlink($filename);

			DisplayResult(array("success" => true));
		}
	}
?>