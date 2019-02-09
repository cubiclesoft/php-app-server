<?php
	// PHP App Server.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/support/cli.php";
	require_once $rootpath . "/support/str_basics.php";
	require_once $rootpath . "/support/random.php";
	require_once $rootpath . "/support/pas_functions.php";

	// Process the command-line options.
	$options = array(
		"shortmap" => array(
			"?" => "help"
		),
		"rules" => array(
			"home" => array("arg" => true),
			"app" => array("arg" => true),
			"biz" => array("arg" => true),
			"host" => array("arg" => true),
			"port" => array("arg" => true),
			"user" => array("arg" => true),
			"group" => array("arg" => true),
			"sfile" => array("arg" => true),
			"quit" => array("arg" => true),
			"exts" => array("arg" => true),
			"www" => array("arg" => true),
			"help" => array("arg" => false)
		)
	);
	$args = CLI::ParseCommandLine($options);

	if (isset($args["opts"]["help"]))
	{
		echo "PHP App Server\n";
		echo "Purpose:  Runs a pure userland PHP web server with virtual directory support and without any external dependencies.\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options]\n";
		echo "Options:\n";
		echo "\t-home    The home directory to store user files in.  Default is the user's home directory.\n";
		echo "\t-app     The application name to use for storing user files.  Default is the directory name.\n";
		echo "\t-biz     The business name to use for storing configuration and log files.\n";
		echo "\t-host    The IP address to bind to.  Default is 127.0.0.1.\n";
		echo "\t-port    The port to bind to.  Default is 0 (random).\n";
		echo "\t-user    The user to run PHP scripts as when using CGI/FastCGI.  *NIX only.\n";
		echo "\t-group   The group to run PHP scripts as when using CGI/FastCGI.  *NIX only.\n";
		echo "\t-sfile   The file to store startup JSON information into.\n";
		echo "\t-quit    The number of seconds to wait without any connected clients.  The default is to never quit.\n";
		echo "\t-exts    The server extensions directory to use.  Default is the 'extensions' directory.\n";
		echo "\t-www     The document root to use.  Default is the 'www' directory.\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $args["file"] . "\n";
		echo "\tphp " . $args["file"] . " -host=[::1] -port=5582\n";

		exit();
	}

	// Load MIME types.
	$mimetypemap = json_decode(file_get_contents($rootpath . "/support/mime_types.json"), true);

	// Load all server extensions.
	if (isset($args["opts"]["exts"]))  $extspath = $args["opts"]["exts"];
	else  $extspath = $rootpath . "/extensions";

	$serverexts = PAS_LoadServerExtensions($extspath);

	// Initialize server extensions.
	foreach ($serverexts as $serverext)
	{
		$serverext->InitServer();
	}

	require_once $rootpath . "/support/web_server.php";
	require_once $rootpath . "/support/websocket_server.php";
	require_once $rootpath . "/support/process_helper.php";

	function WriteStartupInfo($result)
	{
		global $args;

		if (isset($args["opts"]["sfile"]))  file_put_contents($args["opts"]["sfile"], json_encode($result, JSON_UNESCAPED_SLASHES));

		if (!$result["success"])  CLI::DisplayError("An error occurred while starting the server.", $result);
	}

	// Find 'php-cgi' or 'php-fpm' depending on the platform.
	$os = php_uname("s");
	$windows = (strtoupper(substr($os, 0, 3)) == "WIN");

	if ($windows)
	{
		$cgibin = dirname(PHP_BINARY) . "\\php-cgi.exe";
		if (!file_exists($cgibin))  WriteStartupInfo(array("success" => false, "error" => "Unable to start server due to missing executable.  Expected 'php-cgi.exe'.", "errorcode" => "missing_php_cgi"));
		$cgibin = escapeshellarg($cgibin);
	}
	else
	{
		$cgibin = ProcessHelper::FindExecutable("php-cgi", "/usr/bin");
//$cgibin = false;

		// Certain supported platforms, notably Mac OSX, does not include 'php-cgi'.  However, 'php-fpm' may already be available on the platform.
		// php-cgi generally offers a slightly better security model than php-fpm in UNIX socket mode (TCP mode is insecure) and is easier to work with even if it is a bit slower at actually handling requests.
		if ($cgibin !== false)  $cgibin = escapeshellarg($cgibin);
		else
		{
			$fpmbin = ProcessHelper::FindExecutable("php-fpm", "/usr/sbin");
//			$fpmbin = ProcessHelper::FindExecutable("php-fpm7.2", "/usr/sbin");
			if ($fpmbin === false)  WriteStartupInfo(array("success" => false, "error" => "Unable to start server due to missing executable.  Expected 'php-cgi' or 'php-fpm'.", "errorcode" => "missing_php_cgi"));

			$fpmdir = sys_get_temp_dir();
			$fpmdir = str_replace("\\", "/", $fpmdir);
			if (substr($fpmdir, -1) !== "/")  $fpmdir .= "/";
			$fpmdir .= "php_app_server_fpm_" . getmypid() . "_" . microtime(true);
			@mkdir($fpmdir, 0700, true);

			// Generate a PHP-FPM configuration file.
			$data = "[global]\n";
			$data .= "pid = php-fpm.pid\n";
			$data .= "error_log = error.log\n";
			$data .= "daemonize = no\n";
			$data .= "\n";
			$data .= "[www]\n";
			$data .= "user = " . (isset($args["opts"]["user"]) ? $args["opts"]["user"] : ProcessHelper::GetUserName(posix_geteuid())) . "\n";
			if (isset($args["opts"]["group"]))  $data .= "group = " . $args["opts"]["group"] . "\n";
			$data .= "listen = php-fpm.sock\n";
			$data .= "listen.mode = 0600\n";
			$data .= "pm = ondemand\n";
			$data .= "pm.max_children = 50\n";
			$data .= "pm.max_requests = 500\n";
			file_put_contents($fpmdir . "/php-fpm.conf", $data);

			// Start PHP-FPM.
			$cmd = escapeshellarg($fpmbin) . " -p " . escapeshellarg($fpmdir) . " --fpm-config " . escapeshellarg($fpmdir . "/php-fpm.conf") . " -F -R";
			$fpminfo = ProcessHelper::StartProcess($cmd, array("stdin" => false, "stdout" => false, "stderr" => $fpmdir . "/stderr.log"));
			if (!$fpminfo["success"])  WriteStartupInfo(array("success" => false, "error" => "Unable to start PHP-FPM.  Process failed to start.", "errorcode" => "php_fpm_startup_failed"));

			// Wait for the UNIX socket to come up (or the process to die).
			while (!file_exists($fpmdir . "/php-fpm.sock"))
			{
				usleep(50000);

				$pinfo = @proc_get_status($fpminfo["proc"]);
				if (!$pinfo["running"])  WriteStartupInfo(array("success" => false, "error" => "Unable to start PHP-FPM.  Process terminated prematurely.", "errorcode" => "php_fpm_startup_failed"));
			}

			// Retrieve FastCGI information.
			require_once $rootpath . "/support/fastcgi.php";

			// The FastCGI implementation in PHP, including PHP-FPM, is unable to properly handle basic information requests.
			// See:  https://bugs.php.net/bug.php?id=76922
			$fcgi = new FastCGI();
			$result = $fcgi->Connect("unix://" . $fpmdir . "/php-fpm.sock");
			if (!$result["success"])  WriteStartupInfo(array("success" => false, "error" => "PHP-FPM started successfully but attempting to connect to the UNIX socket failed.", "errorcode" => "fastcgi_connect_failed"));

			$fcgi->RequestUpdatedLimits();
			$result = $fcgi->Wait();
			while ($result["success"] && !$fcgi->GetRecvRecords())
			{
				do
				{
					$result = $fcgi->NextReadyRequest();
					if (!$result["success"] || $result["id"] === false)  break;
				} while (1);

				$result = $fcgi->Wait();
			}

			if (!$fcgi->GetRecvRecords())  WriteStartupInfo(array("success" => false, "error" => "PHP-FPM started and the first connection was successful but attempting to retrieve FastCGI information failed.", "errorcode" => "fastcgi_info_retrieval_failed"));

			$fcgilimits = array(
				"connection" => $fcgi->GetConnectionLimit(),
				"concurrency" => $fcgi->GetConncurrencyLimit(),
				"multiplex" => $fcgi->CanMultiplex(),
			);

			$fcgi->Disconnect();
		}
	}

	function InitClientAppData()
	{
		return array("currext" => false, "url" => false, "path" => false, "cgi" => false, "fcgi" => false, "file" => false, "respcode" => 200, "respmsg" => "OK");
	}

	// Extends the web server class to gather transfer statistics.
	class StatsWebServer extends WebServer
	{
		protected function HandleResponseCompleted($id, $result)
		{
			$client = $this->GetClient($id);
			if ($client === false || $client->appdata === false)  return;

			if ($client->appdata["currext"] !== false)  $handler = "ext";
			else if ($client->appdata["cgi"] !== false)  $handler = "cgi";
			else if ($client->appdata["fcgi"] !== false)  $handler = "fastcgi";
			else if ($client->appdata["file"] !== false)  $handler = "static";
			else  $handler = "other/none";

			$stats = array(
				"rawrecv" => ($result["success"] ? $result["rawrecvsize"] : $client->httpstate["result"]["rawrecvsize"]),
				"rawrecvhead" => ($result["success"] ? $result["rawrecvheadersize"] : $client->httpstate["result"]["rawrecvheadersize"]),
				"rawsend" => ($result["success"] ? $result["rawsendsize"] : $client->httpstate["result"]["rawsendsize"]),
				"rawsendhead" => ($result["success"] ? $result["rawsendheadersize"] : $client->httpstate["result"]["rawsendheadersize"]),
			);

			$info = array(
				"ext" => $client->appdata["currext"],
				"handler" => $handler,
				"code" => $client->appdata["respcode"],
				"msg" => $client->appdata["respmsg"]
			);

			WriteAccessLog("WebServer:" . $id, $client->ipaddr, $client->request, $stats, $info);

			// Reset app data.
			if ($client->appdata["cgi"] !== false)
			{
				foreach ($client->appdata["cgi"]["pipes"] as $fp)  fclose($fp);

				proc_close($client->appdata["cgi"]["proc"]);

				if (trim($client->appdata["cgi"]["stderr"]) !== "")
				{
					WriteErrorLog("CGI Error [" . $id . "]", $client->ipaddr, $client->request, array("msg" => trim($client->appdata["cgi"]["stderr"])));

					echo "\t" . trim($client->appdata["cgi"]["stderr"]) . "\n";
				}
			}

			if ($client->appdata["fcgi"] !== false)
			{
				$client->appdata["fcgi"]["conn"]->Disconnect();

				$request = $client->appdata["fcgi"]["request"];
				if (trim($request->stderr) !== "")
				{
					WriteErrorLog("FastCGI Error [" . $id . "]", $client->ipaddr, $client->request, array("msg" => trim($request->stderr)));

					echo "\t" . trim($request->stderr) . "\n";
				}
			}

			if ($client->appdata["file"] !== false && isset($client->appdata["file"]["fp"]) && $client->appdata["file"]["fp"] !== false)  fclose($client->appdata["file"]["fp"]);

			$client->appdata = InitClientAppData();
		}
	}

	$webserver = new StatsWebServer();

	// Enable writing files to the system.
	$cachedir = sys_get_temp_dir();
	$cachedir = str_replace("\\", "/", $cachedir);
	if (substr($cachedir, -1) !== "/")  $cachedir .= "/";
	$cachedir .= "php_app_server_" . getmypid() . "_" . microtime(true) . "/";
	@mkdir($cachedir, 0770, true);
	$webserver->SetCacheDir($cachedir);

	// Enable longer active client times.
	$webserver->SetDefaultClientTimeout(300);
	$webserver->SetMaxRequests(200);

	if (!isset($args["opts"]["host"]))  $args["opts"]["host"] = "127.0.0.1";
	if (!isset($args["opts"]["port"]))  $args["opts"]["port"] = "0";
	$initresult = $webserver->Start($args["opts"]["host"], $args["opts"]["port"], false);
	if (!$initresult["success"])  WriteStartupInfo($initresult);

	// Prepare the initial response line.
	$tempip = stream_socket_get_name($webserver->GetStream(), false);
	$pos = strrpos($tempip, ":");
	if ($pos !== false)  $args["opts"]["port"] = substr($tempip, $pos + 1);
	$initresult["url"] = "http://" . $args["opts"]["host"] . ":" . $args["opts"]["port"] . "/";
	$initresult["port"] = (int)$args["opts"]["port"];

	// Core function for forwarding and rate limiting incoming data from the browser into PHP CGI stdin.
	function ProcessClientCGIRequestBody($request, $body, $id)
	{
		global $webserver;

		$client = $webserver->GetClient($id);
		if ($client === false || $client->appdata === false)  return false;
		if ($client->appdata["cgi"] === false)  return false;

		$pinfo = @proc_get_status($client->appdata["cgi"]["proc"]);
		if (!$pinfo["running"])  return false;

		$client->appdata["cgi"]["stdin"] .= $body;

		// Write as much data as possible.
		$fp = $client->appdata["cgi"]["pipes"][0];
		$result = fwrite($fp, (strlen($client->appdata["cgi"]["stdin"]) > 16384 ? substr($client->appdata["cgi"]["stdin"], 0, 16384) : $client->appdata["cgi"]["stdin"]));

		if ($result === false || feof($fp))  return false;

		// Serious bug in PHP core for all handle types:  https://bugs.php.net/bug.php?id=73535
		if ($result === 0)
		{
			$fp2 = $client->appdata["cgi"]["pipes"][1];
			$data = fread($fp2, 1);

			if ($data === false)  return false;
			if ($data === "" && feof($fp2))  return false;

			if ($data !== "")  $client->appdata["cgi"]["stdout"] .= $data;
		}
		else
		{
			$client->appdata["cgi"]["stdin"] = substr($client->appdata["cgi"]["stdin"], $result);
			$client->appdata["cgi"]["stdinbytes"] += $result;
		}

		// Dynamically adjust the client receive rate limit so that the amount of input data generally doesn't exceed the OS pipe limits and to keep RAM usage low.
		$difftime = microtime(true) - $client->appdata["cgi"]["start"];
		if ($result === 0 || $difftime > 1.0)
		{
			$sendrate = $client->appdata["cgi"]["stdinbytes"] / $difftime;
			if ($sendrate < strlen($client->appdata["cgi"]["stdin"]))  $sendrate *= 0.5;
			else  $sendrate *= 1.1;

			$sendrate = (int)$sendrate;
			if ($sendrate < 1024)  $sendrate = 1024;
			$client->httpstate["options"]["recvratelimit"] = $sendrate;

			// Reset the rate limit tracker every 10 seconds.
			if ($difftime >= 10.0)
			{
				$client->appdata["cgi"]["start"] += $difftime;
				$client->appdata["cgi"]["stdinbytes"] = 0;
			}
		}

		return true;
	}

	// Core function for forwarding and rate limiting incoming data from the browser into PHP FastCGI stdin.
	function ProcessClientFastCGIRequestBody($request, $body, $id)
	{
		global $webserver;

		$client = $webserver->GetClient($id);
		if ($client === false || $client->appdata === false)  return false;
		if ($client->appdata["fcgi"] === false)  return false;

		$fcgi = $client->appdata["fcgi"]["conn"];

		if ($body !== "")
		{
			$result2 = $fcgi->SendStdin($client->appdata["fcgi"]["request"]->id, $body);
			if (!$result2["success"])  return false;
		}

		$initsize = $fcgi->GetRawSendSize();
		if ($fcgi->NeedsWrite())
		{
			// Process queues.  Always attempts to read one byte of data in order to mitigate a serious bug in PHP:  https://bugs.php.net/bug.php?id=73535
			$result2 = $fcgi->ProcessQueues(true, true, 1);
			if (!$result2["success"])  return false;
		}

		// Dynamically adjust the client receive rate limit so that the amount of input data generally doesn't exceed the FastCGI transfer limit and to keep RAM usage low.
		$diffsize = $fcgi->GetRawSendSize() - $initsize;
		$difftime = microtime(true) - $client->appdata["fcgi"]["start"];
		if ($diffsize === 0 || $difftime > 1.0)
		{
			$sendrate = ($initsize + $diffsize - $client->appdata["fcgi"]["startbytes"]) / $difftime;
			if ($sendrate < $fcgi->GetRawSendQueueSize())  $sendrate *= 0.5;
			else  $sendrate *= 1.1;

			$sendrate = (int)$sendrate;
			if ($sendrate < 1024)  $sendrate = 1024;
			$client->httpstate["options"]["recvratelimit"] = $sendrate;

			// Reset the rate limit tracker every 10 seconds.
			if ($difftime >= 10.0)
			{
				$client->appdata["fcgi"]["start"] += $difftime;
				$client->appdata["fcgi"]["startbytes"] = $initsize + $diffsize;
			}
		}

		return true;
	}

	$accessfpnum = 0;
	function WriteAccessLog($trace, $ipaddr, $request, $stats, $info)
	{
		global $accessfp, $accessfpnum;

		$accessfpnum++;
		fwrite($accessfp, json_encode(array("#" => $accessfpnum, "ts" => time(), "gmt" => gmdate("Y-m-d H:i:s"), "trace" => $trace, "ip" => $ipaddr, "req" => $request, "stats" => $stats, "info" => $info), JSON_UNESCAPED_SLASHES) . "\n");
		fflush($accessfp);

		echo $trace . " - ";
		if (is_string($request))  echo $request;
		else if (isset($request["line"]))  echo $request["line"];
		else  echo json_encode($request, JSON_UNESCAPED_SLASHES);
		echo "\n";

		echo "\tReceived " . number_format($stats["rawrecv"], 0) . " bytes; Sent " . number_format($stats["rawsend"], 0) . " bytes\n";
		echo "\t" . json_encode($info, JSON_UNESCAPED_SLASHES) . "\n";
	}

	$errorfpnum = 0;
	function WriteErrorLog($trace, $ipaddr, $request, $info)
	{
		global $errorfp, $errorfpnum;

		$errorfpnum++;
		fwrite($errorfp, json_encode(array("#" => $errorfpnum, "ts" => time(), "gmt" => gmdate("Y-m-d H:i:s"), "trace" => $trace, "ip" => $ipaddr, "req" => $request, "info" => $info), JSON_UNESCAPED_SLASHES) . "\n");
		fflush($errorfp);
	}

	function SendHTTPErrorResponse($client)
	{
		// Reset the response headers.
		if (!$client->responsefinalized)
		{
			$client->responseheaders = array();
			$client->responsebodysize = true;

			$client->SetResponseContentType("text/html; charset=UTF-8");
		}

		$client->SetResponseCode($client->appdata["respcode"]);

		// Prevent browsers and proxies from doing bad things.
		$client->SetResponseNoCache();

		$client->AddResponseContent($client->appdata["respcode"] . " " . $client->appdata["respmsg"]);
		$client->FinalizeResponse();
	}

	// Final initialization.
	$wsserver = new WebSocketServer();

	$baseenv = ProcessHelper::GetCleanEnvironment();
	if (isset($args["opts"]["www"]))  $docroot = $args["opts"]["www"];
	else  $docroot = $rootpath . "/www";

	// Prepare various files and directories.
	if ($windows)
	{
		if (isset($args["opts"]["home"]))
		{
			$path = $args["opts"]["home"];

			$pfilespath = $path;
			$ufilespath = $path;
		}
		else
		{
			if (getenv("ProgramData") !== false)  $pfilespath = getenv("ProgramData");
			else if (getenv("ALLUSERSPROFILE") !== false)  $pfilespath = getenv("ALLUSERSPROFILE");
			else  WriteStartupInfo(array("success" => false, "error" => "Unable to start server due to missing environment variables.  Expected 'ProgramData' or 'ALLUSERSPROFILE'.", "errorcode" => "missing_environment_var"));

			if (getenv("LOCALAPPDATA") !== false)  $ufilespath = getenv("LOCALAPPDATA");
			else if (getenv("APPDATA") !== false)  $ufilespath = getenv("APPDATA");
			else  WriteStartupInfo(array("success" => false, "error" => "Unable to start server due to missing environment variables.  Expected 'LOCALAPPDATA' or 'APPDATA'.", "errorcode" => "missing_environment_var"));
		}
	}
	else
	{
		if (isset($args["opts"]["home"]))
		{
			$path = $args["opts"]["home"];
			$path = rtrim($path, "\\/");
		}
		else if (function_exists("posix_geteuid") && posix_geteuid() == 0)
		{
			$path = "/root";
		}
		else
		{
			if (getenv("HOME") === false)  WriteStartupInfo(array("success" => false, "error" => "Unable to start server due to missing environment variable.  Expected 'HOME'.", "errorcode" => "missing_environment_var"));

			$path = getenv("HOME");
			$path = rtrim($path, "/");

			if (function_exists("posix_geteuid") && fileowner($path . "/") !== posix_geteuid())  WriteStartupInfo(array("success" => false, "error" => "Unable to start server due to mismatched user.  The path of the 'HOME' environment variable does not match the effective user ID.", "errorcode" => "mismatched_environment_var"));
		}

		$pfilespath = $path . "/.config";
		$ufilespath = $path . "/.config";
	}

	$pfilespath = rtrim(str_replace("\\", "/", $pfilespath), "/");
	$ufilespath = rtrim(str_replace("\\", "/", $ufilespath), "/");

	if (isset($args["opts"]["biz"]))
	{
		$pfilespath .= "/" . $args["opts"]["biz"];
		$ufilespath .= "/" . $args["opts"]["biz"];
	}

	if (isset($args["opts"]["app"]))  $appname = $args["opts"]["app"];
	else
	{
		// When not supplied, attempt to determine the name of the app based on the root path.
		// Mac OSX has specific requirements for application structure.
		$paths = explode("/", $rootpath);
		do
		{
			$appname = array_pop($paths);
			if (substr($appname, -4) === ".app")  $appname = substr($appname, 0, -4);
		} while (($appname === "" || $appname === "MacOS" || $appname === "Contents") && count($paths));

		if ($appname === "")  $appname = "php-app-server";
	}

	@cli_set_process_title((isset($args["opts"]["biz"]) ? $args["opts"]["biz"] . " " : "") . $appname);

	$pfilespath .= "/" . $appname;
	$ufilespath .= "/" . $appname;

	@mkdir($pfilespath . "/logs", 0770, true);
	@mkdir($ufilespath . "/www", 0770, true);

	$baseenv["DOCUMENT_ROOT_USER"] = $ufilespath . "/www";
	$baseenv["PAS_PROG_FILES"] = $pfilespath;
	$baseenv["PAS_USER_FILES"] = $ufilespath;

	$rng = new CSPRNG();
	$baseenv["PAS_SECRET"] = $rng->GenerateToken();

	if (file_exists($pfilespath . "/logs/access.log") && filesize($pfilespath . "/logs/access.log") > 10000000)  @unlink($pfilespath . "/logs/access.log");
	$accessfp = fopen($pfilespath . "/logs/access.log", "ab");

	if (file_exists($pfilespath . "/logs/error.log") && filesize($pfilespath . "/logs/error.log") > 100000)  @unlink($pfilespath . "/logs/error.log");
	$errorfp = fopen($pfilespath . "/logs/error.log", "ab");

	// Record the start time of this application.
	$info = array(
		"progfiles" => $pfilespath,
		"userfiles" => $ufilespath,
		"args" => $args
	);

	WriteErrorLog(__FILE__ . ":" . __LINE__, "", "STARTUP_INFO", $info);

	foreach ($serverexts as $serverext)
	{
		$serverext->ServerReady();
	}

	// Write out the initial response line.
	WriteStartupInfo($initresult);
	echo "Server URL:  " . $initresult["url"] . "\n";
	echo "Ready.\n";

	$cgis = array();
	$fcgis = array();
	$lastclient = microtime(true);
	$running = true;

	do
	{
		// Implement the stream_select() call directly since multiple server instances are involved.
		$timeout = 3;
		$readfps = array();
		$writefps = array();
		$exceptfps = NULL;

		$webserver->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);
		foreach ($serverexts as $ext)  $ext->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);
		$wsserver->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);

		// Add CGI handles.
		foreach ($cgis as $id => $val)
		{
			$client = $webserver->GetClient($id);
			if ($client === false || $client->appdata === false || $client->appdata["cgi"] === false)
			{
				unset($cgis[$id]);

				continue;
			}

			if ($client->appdata["cgi"]["stdin"] !== "")  $writefps["cgi_in_" . $id] = $client->appdata["cgi"]["pipes"][0];

			if (isset($client->appdata["cgi"]["pipes"][1]) && strlen($client->writedata) + strlen($client->appdata["cgi"]["stdout"]) < 262144)  $readfps["cgi_out_" . $id] = $client->appdata["cgi"]["pipes"][1];

			if (isset($client->appdata["cgi"]["pipes"][2]))  $readfps["cgi_err_" . $id] = $client->appdata["cgi"]["pipes"][2];
		}

		// Add FastCGI handles.
		foreach ($fcgis as $id => $val)
		{
			$client = $webserver->GetClient($id);
			if ($client === false || $client->appdata === false || $client->appdata["fcgi"] === false)
			{
				unset($fcgis[$id]);

				continue;
			}

			if ($client->appdata["fcgi"]["conn"]->NeedsWrite())  $writefps["fcgi_send_" . $id] = $client->appdata["fcgi"]["fp"];

			$request = $client->appdata["fcgi"]["request"];
			if (($request->stdoutopen || $request->stderropen) && strlen($client->writedata) + strlen($request->stdout) < 262144)  $readfps["fcgi_recv_" . $id] = $client->appdata["fcgi"]["fp"];
		}

		$result = @stream_select($readfps, $writefps, $exceptfps, $timeout);
		if ($result === false)  break;

		$result = $webserver->Wait(0);

		// Always add CGI clients.
		foreach ($cgis as $id => $val)
		{
			$client = $webserver->GetClient($id);
			if ($client !== false && $client->appdata !== false && $client->appdata["cgi"] !== false)  $result["clients"][$id] = $client;
		}

		// Always add FastCGI clients.
		foreach ($fcgis as $id => $val)
		{
			$client = $webserver->GetClient($id);
			if ($client !== false && $client->appdata !== false && $client->appdata["fcgi"] !== false)  $result["clients"][$id] = $client;
		}

		// Handle active clients.
		foreach ($result["clients"] as $id => $client)
		{
			if ($client->appdata === false)
			{
				echo "Client ID " . $id . " connected.\n";

				$client->appdata = InitClientAppData();
			}

			if ($client->appdata["url"] === false)
			{
				// Parse the incoming URL.
				$client->appdata["url"] = HTTP::ExtractURL($client->url);
				$path = explode("/", $client->appdata["url"]["path"]);
				$path2 = array("");
				foreach ($path as $part)
				{
					$part = trim($part, " \t\n\r\0\x0B.");
					if ($part !== "")  $path2[] = $part;
				}
				$client->appdata["path"] = implode("/", $path2);
				if (substr($client->appdata["url"]["path"], -1) === "/")  $client->appdata["path"] .= "/";

				// See if any server extensions want to handle the request.
				$client->appdata["currext"] = false;
				foreach ($serverexts as $name => $ext)
				{
					if ($ext->CanHandleRequest($client->request["method"], $client->appdata["url"], $client->appdata["path"], $client))
					{
						$client->appdata["currext"] = $name;

						break;
					}
				}

				// Attempt to find a file.
				$options = $client->GetHTTPOptions();
				if ($client->appdata["currext"] === false)
				{
					clearstatcache();

					$found = false;
					$extra = "";
					if (is_file($docroot . $client->appdata["path"]))  $found = $client->appdata["path"];
					else if (substr($client->appdata["path"], -4) !== ".php" && is_file($ufilespath . "/www" . $client->appdata["path"]))  $found = $client->appdata["path"];
					else
					{
						$path = $client->appdata["path"];
						if (substr($path, -1) !== "/")  $path .= "/";

						if (is_file($docroot . $path . "index.html"))  $found = $path . "index.html";
						else
						{
							// Find a parent PHP file.
							while ($path !== "" && !$found)
							{
								if (is_file($docroot . $path . "index.php"))  $found = $path . "index.php";
								else if ($path !== "/" && is_file($docroot . substr($path, 0, -1) . ".php"))  $found = substr($path, 0, -1) . ".php";
								else
								{
									$pos = ($path !== "/" ? strrpos($path, "/", -2) : false);
									if ($pos === false)
									{
										$extra = "";
										$path = "";
									}
									else
									{
										$extra = substr($path, $pos + 1) . $extra;
										$path = substr($path, 0, $pos + 1);
									}
								}
							}
						}
					}

					if ($found === false)
					{
						$client->appdata["respcode"] = 404;
						$client->appdata["respmsg"] = "File Not Found";
					}
					else
					{
//echo $found . "\n";
						if ($extra !== "")  $extra = "/" . $extra;

						if (substr($found, -4) !== ".php")  $client->appdata["file"] = array("name" => (is_file($docroot . $found) ? $docroot : $ufilespath) . $found);
						else
						{
							// Start a CGI process or connect to FastCGI before retrieving any additional data from the client.
							// CGI is preferred over FastCGI for a number of reasons.  For faster performance, use an extension.
							$env = $baseenv;
							$env["SERVER_SOFTWARE"] = "PHP App Server/1.0";
							$env["SERVER_NAME"] = $args["opts"]["host"];
							$env["SERVER_ADDR"] = $args["opts"]["host"];
							$env["SERVER_ADMIN"] = "admin@localhost";
							$env["GATEWAY_INTERFACE"] = "CGI/1.1";

							$env["SERVER_PROTOCOL"] = $client->request["httpver"];
							$env["SERVER_PORT"] = $args["opts"]["port"];
							$env["REQUEST_METHOD"] = $client->request["method"];
							$env["DOCUMENT_ROOT"] = $docroot;
							$env["PATH_INFO"] = $extra;
							$env["PATH_TRANSLATED"] = $docroot . $client->appdata["path"];
							$env["QUERY_STRING"] = $client->appdata["url"]["query"];
							if (isset($client->headers["Content-Length"]))  $env["CONTENT_LENGTH"] = $client->headers["Content-Length"];
							if (isset($client->headers["Content-Type"]))  $env["CONTENT_TYPE"] = $client->headers["Content-Type"];

							$pos = strrpos($client->ipaddr, ":");
							if ($pos === false)  $pos = strlen($client->ipaddr);
							$env["REMOTE_ADDR"] = (string)substr($client->ipaddr, 0, $pos);
							$env["REMOTE_PORT"] = (string)substr($client->ipaddr, $pos + 1);

							$env["REQUEST_URI"] = $client->request["path"];
							$env["SCRIPT_FILENAME"] = $docroot . $found;
							$env["SCRIPT_NAME"] = $found;

							// Required environment variable for PHP CGI to function.
							$env["REDIRECT_STATUS"] = "200";

							foreach ($client->headers as $key => $val)
							{
								$env["HTTP_" . preg_replace('/[^A-Z0-9]/', "_", strtoupper($key))] = $val;
							}
//var_dump($client);
//var_dump($docroot);
//var_dump($client->appdata);
//var_dump($env);
//exit();

							if ($cgibin !== false)
							{
								// Start the process.  Note that this is a blocking operation.
								// On Windows, an intermediate process is used to enable non-blocking transfer of data to and from the process.
								$options2 = array(
									"stdin" => (!$client->requestcomplete),
									"tcpstdin" => false,
									"dir" => $docroot,
									"env" => $env
								);

								if (isset($args["opts"]["user"]))  $options2["user"] = $args["opts"]["user"];
								if (isset($args["opts"]["group"]))  $options2["group"] = $args["opts"]["group"];

								$result2 = ProcessHelper::StartProcess($cgibin, $options2);
								if (!$result2["success"])
								{
									$client->appdata["respcode"] = 500;
									$client->appdata["respmsg"] = "Internal Server Error<br><br>See log file for details.";

									WriteErrorLog("500 Internal Server Error - ProcessHelper::StartProcess()", $client->ipaddr, $client->request, $result2);
								}
								else
								{
									// Successfully started the CGI process.
									$result2["stdin"] = "";
									$result2["start"] = microtime(true);
									$result2["stdinbytes"] = 0;
									$result2["headersdone"] = false;
									$result2["headerssize"] = 0;
									$result2["stdout"] = "";
									$result2["stderr"] = "";

									$client->appdata["cgi"] = $result2;

									// Switch the body read callback to a local callback to route any additional incoming data to the CGI handler.
									// There is an extra call to the original callback when urlencoded form data comes in but the callback does nothing.
									$options["read_body_callback"] = "ProcessClientCGIRequestBody";

									$cgis[$id] = true;
								}
							}
							else
							{
								// Initiate a FastCGI connection.  Note that the connect call is a blocking operation.
								// Use the previously retrieved FastCGI limits to initialize the new FastCGI instance.
								$fcgi = new FastCGI();
								$fcgi->SetConnectionLimit($fcgilimits["connection"]);
								$fcgi->SetConncurrencyLimit($fcgilimits["concurrency"]);
								$fcgi->SetMultiplex($fcgilimits["multiplex"]);

								$cmd = "Connect";
								$result2 = $fcgi->Connect("unix://" . $fpmdir . "/php-fpm.sock");

								// Initialize the request.  Requests connection termination at the end of the request.
								if ($result2["success"])
								{
									$cmd = "BeginRequest";
									$result2 = $fcgi->BeginRequest(FastCGI::ROLE_RESPONDER, false);
								}

								// Send params.
								if ($result2["success"])
								{
									$requestid = $result2["id"];
									$request = $result2["request"];

									$cmd = "SendParams";
									$result2 = $fcgi->SendParams($requestid, $env);
								}

								// Finalize params.
								if ($result2["success"])  $result2 = $fcgi->SendParams($requestid, array());

								// Finalize stdin if the request is already finished.
								if ($result2["success"] && $client->requestcomplete)
								{
									$cmd = "SendStdin";
									$result2 = $fcgi->SendStdin($requestid, "");
								}

								if ($result2["success"])
								{
									// Successfully initialized the FastCGI process.
									$client->appdata["fcgi"] = array(
										"conn" => $fcgi,
										"fp" => $fcgi->GetStream(),
										"request" => $request,
										"start" => microtime(true),
										"startbytes" => $fcgi->GetRawSendSize(),
										"headersdone" => false,
										"headerssize" => 0,
									);

									// Switch the body read callback to a local callback to route any additional incoming data to the FastCGI handler.
									// There is an extra call to the original callback when urlencoded form data comes in but the callback does nothing.
									$options["read_body_callback"] = "ProcessClientFastCGIRequestBody";

									$fcgis[$id] = true;
								}
								else
								{
									$client->appdata["respcode"] = 500;
									$client->appdata["respmsg"] = "Internal Server Error<br><br>See log file for details.";

									WriteErrorLog("500 Internal Server Error - FastCGI::" . $cmd . "()", $client->ipaddr, $client->request, $result2);
								}
							}
						}
					}
				}

				// Remove the receive size limit for PHP app server.  This doesn't eliminate PHP's file upload limits.
				unset($options["recvlimit"]);
				$client->SetHTTPOptions($options);
			}

			if ($client->requestcomplete)
			{
				if ($client->appdata["currext"] !== false)
				{
					// Let the server extension handle the request.
					if ($client->mode === "init_response")
					{
						// Handle WebSocket upgrade requests.
						$id2 = $wsserver->ProcessWebServerClientUpgrade($webserver, $client);
						if ($id2 !== false)
						{
							echo "Client ID " . $id . " upgraded to WebSocket.  WebSocket client ID is " . $id2 . ".\n";

							// Log the upgrade to WebSocket.
							$stats = array(
								"rawrecv" => $client->httpstate["result"]["rawrecvsize"],
								"rawrecvhead" => $client->httpstate["result"]["rawrecvheadersize"],
								"rawsend" => 0,
								"rawsendhead" => 0,
							);

							$info = array(
								"ext" => $client->appdata["currext"],
								"handler" => "websocket_upgrade",
								"code" => 101,
								"msg" => "Switching Protocols",
								"ws_id" => $id2
							);

							WriteAccessLog("WebServer:" . $id, $client->ipaddr, $client->request, $stats, $info);
						}
						else
						{
							// Attempt to normalize input.
							if ($client->contenthandled)  $data = $client->requestvars;
							else if (!is_object($client->readdata))  $data = @json_decode($client->readdata, true);
							else
							{
								$client->readdata->Open();
								$data = @json_decode($client->readdata->Read(1000000), true);
							}

							// Process the request.
							if (!is_array($data))
							{
								$result2 = array("success" => false, "error" => "Data sent was not able to be decoded.", "errorcode" => "invalid_data");

								$client->SetResponseCode(400);

								// Prevent browsers and proxies from doing bad things.
								$client->SetResponseNoCache();

								$client->SetResponseContentType("application/json");
								$client->AddResponseContent(json_encode($result2));
								$client->FinalizeResponse();
							}
							else
							{
								$result2 = $serverexts[$client->appdata["currext"]]->ProcessRequest($client->request["method"], $client->appdata["path"], $client, $data);
								if ($result2 === false)
								{
									$webserver->RemoveClient($id);

									echo "Client ID " . $id . " removed.\n";

									unset($client->appdata);
								}
								else if (!$client->responsefinalized)
								{
									$client->appdata["data"] = $data;
								}
							}
						}
					}
					else
					{
						// Continue processing the request where it left off.
						$result2 = $serverexts[$client->appdata["currext"]]->ProcessRequest($client->request["method"], $client->appdata["path"], $client, $client->appdata["data"]);
						if ($result2 === false)
						{
							$webserver->RemoveClient($id);

							echo "Client ID " . $id . " removed.\n";
						}
					}
				}
				else if ($client->appdata["cgi"] !== false)
				{
					// Finalize transfer of data to the CGI application.
					if (isset($client->appdata["cgi"]["pipes"][0]) && ($client->appdata["cgi"]["stdin"] === "" || !ProcessClientCGIRequestBody(false, "", $id) || $client->appdata["cgi"]["stdin"] === ""))
					{
						fclose($client->appdata["cgi"]["pipes"][0]);

						unset($client->appdata["cgi"]["pipes"][0]);
					}

					// Read response data from the CGI application.
					if (isset($client->appdata["cgi"]["pipes"][1]) && strlen($client->writedata) + strlen($client->appdata["cgi"]["stdout"]) < 262144)
					{
						$data = fread($client->appdata["cgi"]["pipes"][1], 65536);
						if ($data === false || ($data === "" && feof($client->appdata["cgi"]["pipes"][1])))
						{
							fclose($client->appdata["cgi"]["pipes"][1]);

							unset($client->appdata["cgi"]["pipes"][1]);
						}
						else
						{
							$client->appdata["cgi"]["stdout"] .= $data;
						}
					}

					if (isset($client->appdata["cgi"]["pipes"][2]))
					{
						$data = fread($client->appdata["cgi"]["pipes"][2], 65536);
						if ($data === false || ($data === "" && feof($client->appdata["cgi"]["pipes"][2])))
						{
							fclose($client->appdata["cgi"]["pipes"][2]);

							unset($client->appdata["cgi"]["pipes"][2]);
						}
						else
						{
							$client->appdata["cgi"]["stderr"] .= $data;
						}
					}

					// Process headers.
					if (!$client->appdata["cgi"]["headersdone"])
					{
						$pos = strpos($client->appdata["cgi"]["stdout"], "\n");
						while ($pos !== false)
						{
							$line = trim(substr($client->appdata["cgi"]["stdout"], 0, $pos));
							$client->appdata["cgi"]["stdout"] = (string)substr($client->appdata["cgi"]["stdout"], $pos + 1);
							$client->appdata["cgi"]["headerssize"] += $pos + 1;

							if ($line === "")
							{
								$client->appdata["cgi"]["headersdone"] = true;

								break;
							}

							$pos = strpos($line, ":");
							if ($pos !== false)
							{
								$name = strtolower(trim(substr($line, 0, $pos)));
								$val = trim(substr($line, $pos + 1));

								if ($name === "status" && (int)$val >= 200)  $client->SetResponseCode((int)$val);
								else if ($name === "content-length" && (int)$val >= 0)  $client->SetResponseContentLength((int)$val);
								else if ($name === "content-type")  $client->AddResponseHeader("Content-Type", $val, true);
								else  $client->AddResponseHeader(HTTP::HeaderNameCleanup($name), $val);
							}

							$pos = strpos($client->appdata["cgi"]["stdout"], "\n");
						}

						if ($client->appdata["cgi"]["headerssize"] >= 262144)
						{
							$client->appdata["respcode"] = 500;
							$client->appdata["respmsg"] = "Internal Server Error<br><br>See log file for details.";

							WriteErrorLog("500 Internal Server Error - CGI headers too large", $client->ipaddr, $client->request, array("success" => false, "error" => "CGI response headers exceed 262,144 bytes.", "errorcode" => "cgi_headers_too_large"));

							SendHTTPErrorResponse($client);
						}
					}

					if ($client->appdata["cgi"]["headersdone"])
					{
						// Write response content.
						if ($client->appdata["cgi"]["stdout"] !== "")
						{
							$client->AddResponseContent($client->appdata["cgi"]["stdout"]);
							$client->appdata["cgi"]["stdout"] = "";
						}

						// When stdout and stderr are both closed, the response is complete.
						if (!isset($client->appdata["cgi"]["pipes"][1]) && !isset($client->appdata["cgi"]["pipes"][2]))  $client->FinalizeResponse();
					}
					else if (!isset($client->appdata["cgi"]["pipes"][1]) && !isset($client->appdata["cgi"]["pipes"][2]))
					{
						// For some reason, both stdout and stderr are closed and no headers were sent.
						$client->appdata["cgi"]["stdout"] = "";

						$client->appdata["respcode"] = 500;
						$client->appdata["respmsg"] = "Internal Server Error<br><br>See log file for details.";

						WriteErrorLog("500 Internal Server Error - Invalid CGI response", $client->ipaddr, $client->request, array("success" => false, "error" => "An invalid or insufficient response was produced by the CGI program.", "errorcode" => "invalid_cgi_response"));

						SendHTTPErrorResponse($client);
					}
//var_dump($client->headers);
//var_dump($client->appdata["cgi"]);
				}
				else if ($client->appdata["fcgi"] !== false)
				{
					// Finalize transfer of data to the FastCGI application.
					$request = $client->appdata["fcgi"]["request"];
					if (!$request->stdinopen)  $result2 = array("success" => true);
					else
					{
						$cmd = "SendStdin";
						$result2 = $client->appdata["fcgi"]["conn"]->SendStdin($request->id, "");
					}

					// Process the FastCGI queues.
					if ($result2["success"] && !$request->ended)
					{
						$cmd = "ProcessQueues";
						$read = (($request->stdoutopen || $request->stderropen) && strlen($client->writedata) + strlen($request->stdout) < 262144);
						$write = $client->appdata["fcgi"]["conn"]->NeedsWrite();

						$result2 = $client->appdata["fcgi"]["conn"]->ProcessQueues($read, $write);
					}

					if (!$result2["success"])
					{
						// For some reason, a transfer error occurred.
						$request->stdout = "";

						$client->appdata["respcode"] = 500;
						$client->appdata["respmsg"] = "Internal Server Error<br><br>See log file for details.";

						WriteErrorLog("500 Internal Server Error - FastCGI encountered an unexpected error", $client->ipaddr, $client->request, $result2);

						SendHTTPErrorResponse($client);
					}
					else
					{
						// Process headers.
						if (!$client->appdata["fcgi"]["headersdone"])
						{
							$pos = strpos($request->stdout, "\n");
							while ($pos !== false)
							{
								$line = trim(substr($request->stdout, 0, $pos));
								$request->stdout = (string)substr($request->stdout, $pos + 1);
								$client->appdata["fcgi"]["headerssize"] += $pos + 1;

								if ($line === "")
								{
									$client->appdata["fcgi"]["headersdone"] = true;

									break;
								}

								$pos = strpos($line, ":");
								if ($pos !== false)
								{
									$name = strtolower(trim(substr($line, 0, $pos)));
									$val = trim(substr($line, $pos + 1));

									if ($name === "status" && (int)$val >= 200)  $client->SetResponseCode((int)$val);
									else if ($name === "content-length" && (int)$val >= 0)  $client->SetResponseContentLength((int)$val);
									else if ($name === "content-type")  $client->AddResponseHeader("Content-Type", $val, true);
									else  $client->AddResponseHeader(HTTP::HeaderNameCleanup($name), $val);
								}

								$pos = strpos($request->stdout, "\n");
							}

							if ($client->appdata["fcgi"]["headerssize"] >= 262144)
							{
								$client->appdata["respcode"] = 500;
								$client->appdata["respmsg"] = "Internal Server Error<br><br>See log file for details.";

								WriteErrorLog("500 Internal Server Error - FastCGI headers too large", $client->ipaddr, $client->request, array("success" => false, "error" => "FastCGI response headers exceed 262,144 bytes.", "errorcode" => "fastcgi_headers_too_large"));

								SendHTTPErrorResponse($client);
							}
						}

						if ($client->appdata["fcgi"]["headersdone"])
						{
							// Write response content.
							if ($request->stdout !== "")
							{
								$client->AddResponseContent($request->stdout);
								$request->stdout = "";
							}

							// When stdout and stderr are both closed, the response is complete.
							if (!$request->stdoutopen && !$request->stderropen)  $client->FinalizeResponse();
						}
						else if (!$request->stdoutopen && !$request->stderropen)
						{
							// For some reason, both stdout and stderr are closed and no headers were sent.
							$request->stdout = "";

							$client->appdata["respcode"] = 500;
							$client->appdata["respmsg"] = "Internal Server Error<br><br>See log file for details.";

							WriteErrorLog("500 Internal Server Error - Invalid FastCGI response", $client->ipaddr, $client->request, array("success" => false, "error" => "An invalid or insufficient response was produced by the FastCGI program.", "errorcode" => "invalid_fastcgi_response"));

							SendHTTPErrorResponse($client);
						}
					}
//var_dump($client->headers);
//var_dump($client->appdata["fcgi"]);
				}
				else if ($client->appdata["file"] !== false)
				{
					if (!isset($client->appdata["file"]["fp"]))
					{
						$filename = $client->appdata["file"]["name"];
						$client->appdata["file"]["ts"] = filemtime($filename);

						if (isset($client->headers["If-Modified-Since"]) && HTTP::GetDateTimestamp($client->headers["If-Modified-Since"]) === $client->appdata["file"]["ts"])
						{
							$client->appdata["respcode"] = 304;
							$client->appdata["respmsg"] = "Not Modified";

							unset($client->responseheaders["Content-Type"]);

							$client->SetResponseCode(304);
							$client->FinalizeResponse();
						}
						else
						{
							$client->appdata["file"]["fp"] = fopen($filename, "rb");
							if ($client->appdata["file"]["fp"] === false)
							{
								$client->appdata["respcode"] = 403;
								$client->appdata["respmsg"] = "Forbidden<br><br>See log file for details.";

								WriteErrorLog("403 Forbidden - fopen()", $client->ipaddr, $client->request, array("success" => false, "error" => "Unable to open '" . $filename . "' for reading.", "errorcode" => "fopen_failed"));

								SendHTTPErrorResponse($client);
							}
							else
							{
								$size = filesize($filename);

								// Code swiped from the Barebones CMS PHP SDK.
								// Calculate the amount of data to transfer.  Only implement partial support for the Range header (coalesce requests into a single range).
								$start = 0;
								if (isset($client->headers["Range"]) && $size > 0)
								{
									$min = false;
									$max = false;
									$ranges = explode(";", $client->headers["Range"]);
									foreach ($ranges as $range)
									{
										$range = explode("=", trim($range));
										if (count($range) > 1 && strtolower($range[0]) === "bytes")
										{
											$chunks = explode(",", $range[1]);
											foreach ($chunks as $chunk)
											{
												$chunk = explode("-", trim($chunk));
												if (count($chunk) == 2)
												{
													$pos = trim($chunk[0]);
													$pos2 = trim($chunk[1]);

													if ($pos === "" && $pos2 === "")
													{
														// Ignore invalid range.
													}
													else if ($pos === "")
													{
														if ($min === false || $min > $size - (int)$pos)  $min = $size - (int)$pos;
													}
													else if ($pos2 === "")
													{
														if ($min === false || $min > (int)$pos)  $min = (int)$pos;
													}
													else
													{
														if ($min === false || $min > (int)$pos)  $min = (int)$pos;
														if ($max === false || $max < (int)$pos2)  $max = (int)$pos2;
													}
												}
											}
										}
									}

									// Normalize and cap byte ranges.
									if ($min === false)  $min = 0;
									if ($max === false)  $max = $size - 1;
									if ($min < 0)  $min = 0;
									if ($min > $size - 1)  $min = $size - 1;
									if ($max < 0)  $max = 0;
									if ($max > $size - 1)  $max = $size - 1;
									if ($max < $min)  $max = $min;

									// Translate to start and size.
									$start = $min;
									$size = $max - $min + 1;
								}

								$client->appdata["file"]["size"] = $size;

								if ($start)  fseek($client->appdata["file"]["fp"], $start);

								// Set various headers.
								$pos = strrpos($filename, ".");
								$ext = ($pos === false ? "" : strtolower(substr($filename, $pos + 1)));

								if (isset($mimetypemap[$ext]))  $client->SetResponseContentType($mimetypemap[$ext]);
								else  unset($client->responseheaders["Content-Type"]);

								$client->AddResponseHeader("Accept-Ranges", "bytes");

								$client->AddResponseHeader("Last-Modified", gmdate("D, d M Y H:i:s", $client->appdata["file"]["ts"]) . " GMT");
								$client->SetResponseContentLength($client->appdata["file"]["size"]);

								// Read first chunk of data.
								$data = fread($client->appdata["file"]["fp"], ($client->appdata["file"]["size"] >= 262144 ? 262144 : $client->appdata["file"]["size"]));

								$client->AddResponseContent($data);
								$client->appdata["file"]["size"] -= strlen($data);

								if (!$client->appdata["file"]["size"])  $client->FinalizeResponse();
							}
						}
					}
					else
					{
						// Continue reading data.
						$data = fread($client->appdata["file"]["fp"], ($client->appdata["file"]["size"] >= 262144 ? 262144 : $client->appdata["file"]["size"]));

						$client->AddResponseContent($data);
						$client->appdata["file"]["size"] -= strlen($data);

						if (!$client->appdata["file"]["size"])  $client->FinalizeResponse();
					}

//var_dump($client->headers);
				}
				else
				{
					// WriteErrorLog() should have been called before this point.
					SendHTTPErrorResponse($client);
				}
			}
		}

		foreach ($result["removed"] as $id => $result2)
		{
			if ($result2["client"]->appdata !== false)
			{
				echo "Client ID " . $id . " disconnected.\n";

//				echo "Client ID " . $id . " disconnected.  Reason:\n";
//				var_dump($result2["result"]);
//				echo "\n";
			}
		}

		// WebSocket server.
		$result = $wsserver->Wait(0);

		// Handle active clients.
		foreach ($result["clients"] as $id => $client)
		{
			// Read the input.
			$ws = $client->websocket;

			$result2 = $ws->Read();
			while ($result2["success"] && $result2["data"] !== false)
			{
				// Attempt to normalize the input.
				$data = @json_decode($result2["data"]["payload"], true);

				$stats = array(
					"rawrecv" => strlen($result2["data"]["payload"]),
					"rawsend" => 0,
				);

				$result3 = $serverexts[$client->appdata["currext"]]->ProcessRequest(false, $client->appdata["path"], $client, $data);
				if ($result3 === false)
				{
					$wsserver->RemoveClient($id);

					echo "WebSocket client ID " . $id . " removed.\n";

					unset($client->appdata);
				}
				else if (is_array($result3) && isset($result3["success"]))
				{
					// Send the response.
					$data = json_encode($result3, JSON_UNESCAPED_SLASHES);
					$result2 = $ws->Write($data, $result2["data"]["opcode"]);

					$stats["send"] = strlen($data);

					$info = array(
						"ext" => $client->appdata["currext"],
						"handler" => "websocket",
						"code" => 0,
						"msg" => ""
					);

					WriteAccessLog("WebSocket:" . $id, $client->ipaddr, $client->appdata["path"], $stats, $info);
				}

				$result2 = $ws->Read();
			}
		}

		foreach ($result["removed"] as $id => $result2)
		{
			if ($result2["client"]->appdata !== false)
			{
				echo "WebSocket client ID " . $id . " disconnected.\n";

//				echo "WebSocket client ID " . $id . " disconnected.  Reason:\n";
//				var_dump($result2["result"]);
//				echo "\n";
			}
		}

		// Automatically quit the server at the configured time (if any).
		if (isset($args["opts"]["quit"]) && $args["opts"]["quit"] >= 60)
		{
			if ($webserver->NumClients() || $wsserver->NumClients())  $lastclient = microtime(true);
			else if ($lastclient < microtime(true) - (int)$args["opts"]["quit"])  $running = false;
		}

	} while ($running);

	foreach ($serverexts as $serverext)
	{
		$serverext->ServerDone();
	}

	// Terminate PHP-FPM.
	if ($cgibin === false && isset($fpminfo))  ProcessHelper::TerminateProcess($fpminfo["pid"]);

	if (isset($args["opts"]["sfile"]))  @unlink($args["opts"]["sfile"]);
?>