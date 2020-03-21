<?php
	// Elevation helper tool.
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
			"?" => "help"
		),
		"rules" => array(
			"detach" => array("arg" => false),
			"stdin" => array("arg" => false),
			"stdout" => array("arg" => false),
			"stderr" => array("arg" => false),
			"port" => array("arg" => true),
			"token" => array("arg" => true),
			"help" => array("arg" => false)
		),
		"allow_opts_after_param" => false
	);
	$args = CLI::ParseCommandLine($options);

	if (isset($args["opts"]["help"]) || (!(isset($args["opts"]["port"]) && isset($args["opts"]["token"])) && !count($args["params"])))
	{
		echo "The elevation command-line helper tool for PHP App Server\n";
		echo "Purpose:  Launches elevated processes temporarily to sudo/Administrator rights for PHP App Server applications and routes stdin, stdout, and stderr accordingly.\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options] exe [exeoptions]\n";
		echo "Options:\n";
		echo "\t-detach   Detach on relevant platforms (mostly Windows).\n";
		echo "\t-stdin    Pass stdin through.\n";
		echo "\t-stdout   Pass stdout through.\n";
		echo "\t-stderr   Pass stderr through.\n";
		echo "\n";
		echo "Example:\n";
		echo "\tphp " . $args["file"] . " myexe.exe -option\n";

		exit();
	}

	if (isset($args["opts"]["port"]) && isset($args["opts"]["token"]))
	{
		// Establish TCP/IP connections to the TCP/IP server.
		$result = ProcessHelper::ConnectTCPPipe("127.0.0.1", $args["opts"]["port"], 3, $args["opts"]["token"]);
		if (!$result["success"])  CLI::DisplayError("Elevation server command channel connection failed.", $result);
		$fp = $result["fp"];

		$data = fgets($fp);
		if ($data === false || ($data === "" && feof($fp)))  CLI::DisplayError("Elevation server went away before retrieving command data.");

		$options = @json_decode(trim($data), true);
		if (!is_array($options) || !isset($options["params"]) || !isset($options["stdin"]) || !isset($options["stdout"]) || !isset($options["stderr"]) || !isset($options["dir"]) || !isset($options["env"]))
		{
			CLI::DisplayError("Elevation server sent bad data.");
		}

		$params = $options["params"];
		$token = $options["token"];
		unset($options["params"]);
		unset($options["token"]);

		foreach ($params as $num => $val)  $params[$num] = escapeshellarg($val);
		$cmd = implode(" ", $params);

		// Immutable options.
		$options["tcpstdin"] = true;
		$options["tcpstdout"] = true;
		$options["tcpstderr"] = true;

		unset($options["createprocess_exe"]);

		if ($options["detach"])  unset($options["createprocess_exe_opts"]);
		else  $options["createprocess_exe_opts"] = "/attach";

		// Establish any pipes.
		$pipes = array();
		if ($options["stdin"] !== false)
		{
			$options["stdin"] = true;
			$result = ProcessHelper::ConnectTCPPipe("127.0.0.1", $args["opts"]["port"], 0, $token);
			if (!$result["success"])  CLI::DisplayError("Elevation server stdin connection failed.", $result);
			$pipes[0] = $result["fp"];
		}
		if ($options["stdout"] !== false)
		{
			$options["stdout"] = true;
			$result = ProcessHelper::ConnectTCPPipe("127.0.0.1", $args["opts"]["port"], 1, $token);
			if (!$result["success"])  CLI::DisplayError("Elevation server stdout connection failed.", $result);
			$pipes[1] = $result["fp"];
		}
		if ($options["stderr"] !== false)
		{
			$options["stderr"] = true;
			$result = ProcessHelper::ConnectTCPPipe("127.0.0.1", $args["opts"]["port"], 2, $token);
			if (!$result["success"])  CLI::DisplayError("Elevation server stderr connection failed.", $result);
			$pipes[2] = $result["fp"];
		}

		if (!$options["detach"])
		{
			$options["stdin"] = "";
			$options["stdout"] = "";
			$options["stderr"] = "";

			unset($pipes[0]);
			unset($pipes[1]);
			unset($pipes[2]);
		}

		// Start the process.
		$result = ProcessHelper::StartProcess($cmd, $options);
		if (!$result["success"])  CLI::DisplayError("Unable to start the target process.", $result);
		$proc = $result["proc"];
		$procpipes = $result["pipes"];
//echo $cmd . "\n";
//var_dump($result);
//sleep(10);
	}
	else
	{
		// Start a TCP/IP server and get a security token.
		$result = ProcessHelper::StartTCPServer();
		if (!$result["success"])  CLI::DisplayError("An error occurred while starting the TCP/IP server for command channel and standard handle transport.", $result);
		$token = $result["token"];

		$detach = (isset($args["opts"]["detach"]) && $args["opts"]["detach"]);

		// Get an elevated PHP binary and start it.
		$cmd = PAS_GetAdminPHPBinary($detach);
		$cmd .= " " . escapeshellarg(__FILE__) . " -port " . $result["port"] . " -token " . escapeshellarg($token);
//echo $cmd . "\n";

		$options = array(
			"stdin" => false,
			"stdout" => false,
			"stderr" => false,
			"dir" => $rootpath
		);

		$result = ProcessHelper::StartProcess($cmd, $options);
//var_dump($result);
		if (!$result["success"])  CLI::DisplayError("An error occurred while starting the elevation client process.", $result);
		$proc = $result["proc"];

		$procpipes = array(3 => false);
		$result = ProcessHelper::GetTCPPipes($procpipes, $token, $proc);
		if (!$result["success"] && $result["errorcode"] === "broken_tcp_pipe")  $result = ProcessHelper::GetTCPPipes($procpipes, $token, false, 5);
		if (!$result["success"])  CLI::DisplayError("An error occurred while starting the elevation client process.", $result);

		// Get a new security token.
		$result = ProcessHelper::StartTCPServer();
		if (!$result["success"])  CLI::DisplayError("An error occurred while getting an updated security token.", $result);
		$token = $result["token"];

		// Build the command data.
		$options = array(
			"params" => $args["params"],
			"token" => $token,
			"detach" => $detach,
			"stdin" => (isset($args["opts"]["stdin"]) && $args["opts"]["stdin"]),
			"stdout" => (isset($args["opts"]["stdout"]) && $args["opts"]["stdout"]),
			"stderr" => (isset($args["opts"]["stderr"]) && $args["opts"]["stderr"]),
			"dir" => getcwd(),
			"env" => ProcessHelper::GetCleanEnvironment()
		);

		// Send it to the client.
		$data = json_encode($options, JSON_UNESCAPED_SLASHES) . "\n";
		do
		{
			$result = @fwrite($procpipes[3], $data);
			if ($result)
			{
				$data = (string)substr($data, $result);
				if ($data === "")  break;
			}
			else
			{
				$data2 = @fread($procpipes[3], 1);
				if ($data2 === false || ($data2 === "" && feof($procpipes[3])))  break;
			}

			$readfps = array($procpipes[3]);
			$writefps = array($procpipes[3]);
			$exceptfps = NULL;
			$result = @stream_select($readfps, $writefps, $exceptfps, 1);
			if ($result === false)  break;
		} while (1);

		if ($data !== "")  CLI::DisplayError("An error occurred while initializing the elevation client process.");

		// Establish various pipes as needed.
		if ($options["stdin"])  $procpipes[0] = false;
		if ($options["stdout"])  $procpipes[1] = false;
		if ($options["stderr"])  $procpipes[2] = false;

		// If the command pipe closes, then the process died.
		function CheckCommandPipe($pipes)
		{
			$data2 = @fread($pipes[3], 1);
			if ($data2 === false || ($data2 === "" && feof($pipes[3])))  return false;

			return true;
		}

		$result = ProcessHelper::GetTCPPipes($procpipes, $token, false, 0.5, "CheckCommandPipe");
		if (!$result["success"])  CLI::DisplayError("An error occurred while initializing the elevation client process.", $result);

		// Set up local pipes and attempt to convert them to non-blocking.
		$pipes = array();
		if (isset($procpipes[0]))  $pipes[0] = STDIN;
		if (isset($procpipes[1]))  $pipes[1] = STDOUT;
		if (isset($procpipes[2]))  $pipes[2] = STDERR;
		foreach ($pipes as $fp)  @stream_set_blocking($fp, 0);

		// There is no child process to watch.
		$proc = false;
	}

	// Wait for the process and all pipes to close, passing any data through the connected pipes.
	$stdindata = "";
	$stdoutdata = "";
	$stderrdata = "";
	do
	{
		$readfps = array();
		if (isset($pipes[0]) && strlen($stdindata) < 65536)  $readfps[] = $pipes[0];
		if (isset($pipes[1]))  $readfps[] = $pipes[1];
		if (isset($pipes[2]))  $readfps[] = $pipes[2];
		if (isset($procpipes[1]) && strlen($stdoutdata) < 65536)  $readfps[] = $procpipes[1];
		if (isset($procpipes[2]) && strlen($stderrdata) < 65536)  $readfps[] = $procpipes[2];
		if (isset($procpipes[3]))  $readfps[] = $procpipes[3];

		$writefps = array();
		if ($stdindata !== "")  $writefps[] = $procpipes[0];
		if ($stdoutdata !== "")  $writefps[] = $pipes[1];
		if ($stderrdata !== "")  $writefps[] = $pipes[2];
//echo count($readfps) . ", " . count($writefps) . ", " . count($procpipes) . ", " . count($pipes) . "\n";

		if (!count($readfps) && !count($writefps))  usleep(250000);
		else
		{
			$exceptfps = NULL;
			$result = @stream_select($readfps, $writefps, $exceptfps, 1);
			if ($result === false)  break;

			// Handle stdin.
			if ($stdindata !== "")
			{
				$result = @fwrite($procpipes[0], substr($stdindata, 0, 4096));
				if ($result)  $stdindata = (string)substr($stdindata, $result);
			}

			if (isset($pipes[0]) && strlen($stdindata) < 65536)
			{
				$data = @fread($pipes[0], 65536 - strlen($stdindata));
				if ($data === false || ($data === "" && feof($pipes[0])))
				{
					fclose($pipes[0]);

					unset($pipes[0]);
				}
				else
				{
					$stdindata .= $data;
				}
			}

			if ($stdindata === "" && !isset($pipes[0]) && isset($procpipes[0]))
			{
				fclose($procpipes[0]);

				unset($procpipes[0]);
			}

			// Handle stdout.
			if ($stdoutdata !== "")
			{
				$result = @fwrite($pipes[1], substr($stdoutdata, 0, 4096));
				if ($result)  $stdoutdata = (string)substr($stdoutdata, $result);
			}

			if (isset($procpipes[1]) && strlen($stdoutdata) < 65536)
			{
				$data = @fread($procpipes[1], 65536 - strlen($stdoutdata));
				if ($data === false || ($data === "" && feof($procpipes[1])))
				{
					fclose($procpipes[1]);

					unset($procpipes[1]);
				}
				else
				{
					$stdoutdata .= $data;
				}
			}

			if ($stdoutdata === "" && !isset($procpipes[1]) && isset($pipes[1]))
			{
				fclose($pipes[1]);

				unset($pipes[1]);
			}

			// Handle stderr.
			if ($stderrdata !== "")
			{
				$result = @fwrite($pipes[2], substr($stderrdata, 0, 4096));
				if ($result)  $stderrdata = (string)substr($stderrdata, $result);
			}

			if (isset($procpipes[2]) && strlen($stderrdata) < 65536)
			{
				$data = @fread($procpipes[2], 65536 - strlen($stderrdata));
				if ($data === false || ($data === "" && feof($procpipes[2])))
				{
					fclose($procpipes[2]);

					unset($procpipes[2]);
				}
				else
				{
					$stderrdata .= $data;
				}
			}

			if ($stderrdata === "" && !isset($procpipes[2]) && isset($pipes[2]))
			{
				fclose($pipes[2]);

				unset($pipes[2]);
			}
		}

		// Verify that the process is stll running.
		if ($proc !== false)
		{
			$pinfo = @proc_get_status($proc);
			if (!$pinfo["running"])
			{
				if (isset($procpipes[0]))  fclose($procpipes[0]);
				unset($procpipes[0]);

				if (isset($pipes[0]))  fclose($pipes[0]);
				unset($pipes[0]);

				$stdindata = "";

				$proc = false;
			}
		}

		if (isset($procpipes[3]))
		{
			$data = @fread($procpipes[3], 1);
			if ($data === false || ($data === "" && feof($procpipes[3])))
			{
				if (isset($procpipes[0]))  fclose($procpipes[0]);
				unset($procpipes[0]);

				if (isset($pipes[0]))  fclose($pipes[0]);
				unset($pipes[0]);

				$stdindata = "";

				fclose($procpipes[3]);

				unset($procpipes[3]);
			}
		}
	} while ($proc !== false || count($pipes) || count($procpipes));
?>