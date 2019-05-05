<?php
	// Process helper functions.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class ProcessHelper
	{
		protected static $usercache = array(), $groupcache = array(), $serverfp = false;

		public static function FindExecutable($file, $path = false)
		{
			if ($path !== false && file_exists($path . "/" . $file))  return str_replace(array("\\", "/"), DIRECTORY_SEPARATOR, $path . "/" . $file);

			$paths = getenv("PATH");
			if ($paths === false)  return false;

			$paths = explode(PATH_SEPARATOR, $paths);
			foreach ($paths as $path)
			{
				$path = trim($path);
				if ($path !== false && file_exists($path . "/" . $file))  return str_replace(array("\\", "/"), DIRECTORY_SEPARATOR, $path . "/" . $file);
			}

			return false;
		}

		public static function GetUserInfoByID($uid)
		{
			if (!function_exists("posix_getpwuid"))  return false;

			if (!isset(self::$usercache[$uid]))
			{
				$user = @posix_getpwuid($uid);
				if ($user === false || !is_array($user))  self::$usercache[$uid] = false;
				else
				{
					self::$usercache[$uid] = $user;
					self::$usercache["_" . $user["name"]] = $user;
				}
			}

			return self::$usercache[$uid];
		}

		public static function GetUserInfoByName($name)
		{
			if (!function_exists("posix_getpwnam"))  return false;

			if (!isset(self::$usercache["_" . $name]))
			{
				$user = @posix_getpwnam($name);
				if ($user === false || !is_array($user))  self::$usercache["_" . $name] = false;
				else
				{
					self::$usercache[$user["uid"]] = $user;
					self::$usercache["_" . $name] = $user;
				}
			}

			return self::$usercache["_" . $name];
		}

		public static function GetUserName($uid)
		{
			$user = self::GetUserInfoByID($uid);

			return ($user !== false ? $user["name"] : "");
		}

		public static function GetGroupInfoByID($gid)
		{
			if (!function_exists("posix_getgrgid"))  return false;

			if (!isset(self::$groupcache[$gid]))
			{
				$group = @posix_getgrgid($gid);
				if ($group === false || !is_array($group))  self::$groupcache[$gid] = "";
				else
				{
					self::$groupcache[$gid] = $group;
					self::$groupcache["_" . $group["name"]] = $group;
				}
			}

			return self::$groupcache[$gid];
		}

		public static function GetGroupInfoByName($name)
		{
			if (!function_exists("posix_getgrnam"))  return false;

			if (!isset(self::$groupcache["_" . $name]))
			{
				$group = @posix_getgrnam($name);
				if ($group === false || !is_array($group))  self::$groupcache["_" . $name] = "";
				else
				{
					self::$groupcache[$group["gid"]] = $group;
					self::$groupcache["_" . $name] = $group;
				}
			}

			return self::$groupcache["_" . $name];
		}

		public static function GetGroupName($gid)
		{
			$group = self::GetGroupInfoByID($gid);

			return ($group !== false ? $group["name"] : "");
		}

		public static function GetCleanEnvironment()
		{
			$ignore = array(
				"PHP_SELF" => true,
				"SCRIPT_NAME" => true,
				"SCRIPT_FILENAME" => true,
				"PATH_TRANSLATED" => true,
				"DOCUMENT_ROOT" => true,
				"REQUEST_TIME_FLOAT" => true,
				"REQUEST_TIME" => true,
				"argv" => true,
				"argc" => true,
			);

			$result = array();
			foreach ($_SERVER as $key => $val)
			{
				if (!isset($ignore[$key]) && is_string($val))  $result[$key] = $val;
			}

			return $result;
		}

		public static function StartTCPServer()
		{
			if (self::$serverfp === false)
			{
				// Oddly, this server starts up in about 0.002 seconds BUT calling fclose() on this handle takes 0.5 seconds.
				// So it doesn't really hurt to keep the server alive.
				self::$serverfp = stream_socket_server("tcp://127.0.0.1:0", $errornum, $errorstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
				if (self::$serverfp === false)  return array("success" => false, "error" => self::PHTranslate("Failed to start localhost TCP/IP server."), "errorcode" => "bind_failed");

				stream_set_blocking(self::$serverfp, 0);
			}

			$info = stream_socket_get_name(self::$serverfp, false);
			$pos = strrpos($info, ":");
			$ip = substr($info, 0, $pos);
			$port = (int)substr($info, $pos + 1);

			if (!class_exists("CSPRNG", false))  $token = bin2hex(random_bytes(64));
			else
			{
				$rng = new CSPRNG();
				$token = $rng->GenerateToken();
			}

			return array("success" => true, "fp" => self::$serverfp, "ip" => $ip, "port" => $port, "token" => $token);
		}

		public static function GetTCPPipes(&$pipes, $servertoken, $proc, $waitfor = 0.5, $checkcallback = false)
		{
			$pipesleft = 0;
			foreach ($pipes as $val)
			{
				if ($val === false)  $pipesleft++;
			}

			$servertokenlen = strlen($servertoken);

			$ts = microtime(true);
			$clients = array();
			while ($pipesleft)
			{
				$readfps = array(self::$serverfp);
				foreach ($clients as $client)  $readfps[] = $client->fp;
				$writefps = array();
				$exceptfps = NULL;
				$result = @stream_select($readfps, $writefps, $exceptfps, 1);
				if ($result === false)  break;

				if (in_array(self::$serverfp, $readfps) && ($fp = stream_socket_accept(self::$serverfp)) !== false)
				{
					stream_set_blocking($fp, 0);

					$client = new stdClass();
					$client->fp = $fp;
					$client->data = "";

					$clients[] = $client;
				}

				foreach ($clients as $num => $client)
				{
					$data = @fread($client->fp, $servertokenlen + 1 - strlen($client->data));
					if ($data === false || ($data === "" && feof($client->fp)))
					{
						fclose($client->fp);

						unset($clients[$num]);
					}
					else
					{
						$client->data .= $data;

						if (strlen($client->data) == $servertokenlen + 1)
						{
							// Compare the input token to the one sent to the process.
							if (self::CTstrcmp($servertoken, substr($client->data, 0, -1)))  fclose($client->fp);
							else
							{
								$num2 = ord(substr($client->data, -1));

								if (!isset($pipes[$num2]) || $pipes[$num2] !== false)  fclose($client->fp);
								else
								{
									$pipes[$num2] = $fp;

									$pipesleft--;

									$ts = microtime(true);
								}
							}

							unset($clients[$num]);
						}
					}
				}

				// If the process died, then bail out.
				if ($pipesleft && microtime(true) - $ts > $waitfor)
				{
					if ($proc !== false)
					{
						$pinfo = @proc_get_status($proc);
						if (!$pinfo["running"])  break;
					}
					else if (!is_callable($checkcallback) || !call_user_func_array($checkcallback, array($pipes)))
					{
						break;
					}

					$ts = microtime(true);
				}
			}

			if ($pipesleft)  return array("success" => false, "error" => self::PHTranslate("The process started but failed to connect to the localhost TCP/IP server before terminating."), "errorcode" => "broken_tcp_pipe");

			return array("success" => true);
		}

		// Starts a process with non-blocking pipes.  Windows may require 'createprocess.exe' from:  https://github.com/cubiclesoft/createprocess-windows
		// Non-blocking is required for scenarios when using more than one pipe or a deadlock can happen.
		// For example, one process blocks on stdin to be read while another is blocking on stdout to be read.
		public static function StartProcess($cmd, $options = array())
		{
			$os = php_uname("s");
			$windows = (strtoupper(substr($os, 0, 3)) == "WIN");

			// Determine how input and output will be handled.
			$procpipes = array();

			if (!isset($options["stdin"]))  $options["stdin"] = true;
			if (!isset($options["stdout"]))  $options["stdout"] = true;
			if (!isset($options["stderr"]))  $options["stderr"] = true;

			if (!is_string($options["stdin"]) || $options["stdin"] !== "")
			{
				if (is_string($options["stdin"]))  $procpipes[0] = array("file", $options["stdin"], "r");
				else if (is_resource($options["stdin"]) || is_array($options["stdin"]))  $procpipes[0] = $options["stdin"];
				else if ($options["stdin"] === false)  $procpipes[0] = array("file", ($windows ? "NUL" : "/dev/null"), "r");
				else  $procpipes[0] = array("pipe", "r");
			}

			if (!is_string($options["stdout"]) || $options["stdout"] !== "")
			{
				if (is_string($options["stdout"]))  $procpipes[1] = array("file", $options["stdout"], "w");
				else if (is_resource($options["stdout"]) || is_array($options["stdout"]))  $procpipes[1] = $options["stdout"];
				else if ($options["stdout"] === false)  $procpipes[1] = array("file", ($windows ? "NUL" : "/dev/null"), "w");
				else  $procpipes[1] = array("pipe", "w");
			}

			if (!is_string($options["stderr"]) || $options["stderr"] !== "")
			{
				if (is_string($options["stderr"]))  $procpipes[2] = array("file", $options["stderr"], "w");
				else if (is_resource($options["stderr"]) || is_array($options["stderr"]))  $procpipes[2] = $options["stderr"];
				else if ($options["stderr"] === false)  $procpipes[2] = array("file", ($windows ? "NUL" : "/dev/null"), "w");
				else  $procpipes[2] = array("pipe", "w");
			}

			// Windows requires redirecting pipes through sockets so they can be configured to be non-blocking.
			if ($windows)
			{
				// Don't open a socket if the application really does want a pipe.
				if (!isset($options["tcpstdin"]))  $options["tcpstdin"] = false;
				if (!isset($options["tcpstdout"]))  $options["tcpstdout"] = true;
				if (!isset($options["tcpstderr"]))  $options["tcpstderr"] = true;
				$tcpused = ((isset($procpipes[0]) && is_array($procpipes[0]) && $procpipes[0][0] === "pipe" && $options["tcpstdin"]) || (isset($procpipes[1]) && is_array($procpipes[1]) && $procpipes[1][0] === "pipe" && $options["tcpstdout"]) || (isset($procpipes[2]) && is_array($procpipes[2]) && $procpipes[2][0] === "pipe" && $options["tcpstderr"]));

				// Attempt to locate 'createprocess.exe'.
				if (isset($options["createprocess_exe"]) && !file_exists($options["createprocess_exe"]))  unset($options["createprocess_exe"]);

				if (!isset($options["createprocess_exe"]))
				{
					$filename = str_replace("\\", "/", dirname(__FILE__)) . "/windows/createprocess.exe";
					if (!file_exists($filename))  $filename = str_replace("\\", "/", dirname(__FILE__)) . "/createprocess.exe";
					if (file_exists($filename))  $options["createprocess_exe"] = $filename;
				}

				if (!isset($options["createprocess_exe"]))
				{
					$filename = str_replace("\\", "/", dirname(__FILE__)) . "/windows/createprocess-win.exe";
					if (!file_exists($filename))  $filename = str_replace("\\", "/", dirname(__FILE__)) . "/createprocess-win.exe";
					if (file_exists($filename))  $options["createprocess_exe"] = $filename;
				}

				if (!isset($options["createprocess_exe"]))  return array("success" => false, "error" => self::PHTranslate("Required executable 'createprocess.exe' or 'createprocess-win.exe' was not found.  See:  https://github.com/cubiclesoft/createprocess-windows"), "errorcode" => "missing_createprocess_exe");

				$cmd2 = escapeshellarg(str_replace("/", "\\", $options["createprocess_exe"]));
				$cmd2 .= (isset($options["createprocess_exe_opts"]) ? " " . $options["createprocess_exe_opts"] : " /f=SW_HIDE /f=DETACHED_PROCESS") . " /w";

				if ($tcpused)
				{
					$result = self::StartTCPServer();
					if (!$result["success"])  return $result;

					$serverport = $result["port"];
					$servertoken = $result["token"];

					$cmd2 .= " /socketip=127.0.0.1 /socketport=" . $serverport . " /sockettoken=" . $servertoken;
					if (isset($procpipes[0]) && is_array($procpipes[0]) && $procpipes[0][0] === "pipe" && $options["tcpstdin"])  $cmd2 .= " /stdin=socket";
					if (isset($procpipes[1]) && is_array($procpipes[1]) && $procpipes[1][0] === "pipe" && $options["tcpstdout"])  $cmd2 .= " /stdout=socket";
					if (isset($procpipes[2]) && is_array($procpipes[2]) && $procpipes[2][0] === "pipe" && $options["tcpstderr"])  $cmd2 .= " /stderr=socket";
				}

				$cmd = $cmd2 . " " . $cmd;
			}
			else if (function_exists("posix_geteuid"))
			{
				// Set effective user and group (*NIX only).
				$prevuid = posix_geteuid();
				$prevgid = posix_getegid();

				if (isset($options["user"]))
				{
					$userinfo = self::GetUserInfoByName($options["user"]);
					if ($userinfo !== false)
					{
						posix_seteuid($userinfo["uid"]);
						posix_setegid($userinfo["gid"]);
					}
				}

				if (isset($options["group"]))
				{
					$groupinfo = self::GetGroupInfoByName($options["group"]);
					if ($groupinfo !== false)  posix_setegid($groupinfo["gid"]);
				}
			}

			// Start the process.
			if (!isset($options["env"]))  $options["env"] = self::GetCleanEnvironment();
			$proc = @proc_open($cmd, $procpipes, $pipes, (isset($options["dir"]) ? $options["dir"] : NULL), $options["env"], array("suppress_errors" => true, "bypass_shell" => true));

			// Restore effective user and group.
			if (!$windows && function_exists("posix_geteuid"))
			{
				posix_seteuid($prevuid);
				posix_setegid($prevgid);
			}

			// Verify that the process started.
			if (!is_resource($proc))  return array("success" => false, "error" => self::PHTranslate("Failed to start the process."), "errorcode" => "proc_open_failed", "info" => array("cmd" => $cmd, "dir" => (isset($options["dir"]) ? $options["dir"] : NULL), "env" => $options["env"]));

			// Rebuild the pipes on Windows by waiting for a valid inbound TCP/IP connection for each pipe.
			if ($windows && $tcpused)
			{
				if (isset($procpipes[0]) && is_array($procpipes[0]) && $procpipes[0][0] === "pipe" && $options["tcpstdin"])
				{
					fclose($pipes[0]);

					$pipes[0] = false;
				}
				if (isset($procpipes[1]) && is_array($procpipes[1]) && $procpipes[1][0] === "pipe" && $options["tcpstdout"])
				{
					fclose($pipes[1]);

					$pipes[1] = false;
				}
				if (isset($procpipes[2]) && is_array($procpipes[2]) && $procpipes[2][0] === "pipe" && $options["tcpstderr"])
				{
					fclose($pipes[2]);

					$pipes[2] = false;
				}

				$result = self::GetTCPPipes($pipes, $servertoken, $proc);
				if (!$result["success"])
				{
					$result["info"] = array("cmd" => $cmd, "dir" => (isset($options["dir"]) ? $options["dir"] : NULL), "env" => $options["env"]);

					return $result;
				}
			}

			// Change all pipes to non-blocking.
			if (!isset($options["blocking"]) || !$options["blocking"])
			{
				foreach ($pipes as $fp)  stream_set_blocking($fp, 0);
			}

			$pinfo = @proc_get_status($proc);

			return array("success" => true, "proc" => $proc, "pid" => $pinfo["pid"], "pipes" => $pipes, "info" => array("cmd" => $cmd, "dir" => (isset($options["dir"]) ? $options["dir"] : NULL), "env" => $options["env"]));
		}

		public static function Wait($proc, &$pipes, $stdindata = "", $timeout = -1)
		{
			$stdindata = (string)$stdindata;
			$stdoutdata = "";
			$stderrdata = "";

			$startts = microtime(true);
			do
			{
				$readfps = array();
				if (isset($pipes[1]))  $readfps[] = $pipes[1];
				if (isset($pipes[2]))  $readfps[] = $pipes[2];

				$writefps = array();
				if (isset($pipes[0]))
				{
					if ($stdindata !== "")  $writefps[] = $pipes[0];
					else
					{
						fclose($pipes[0]);

						unset($pipes[0]);
					}
				}

				$ts = microtime(true);
				if ($timeout < 0)  $timeleft = false;
				else
				{
					$timeleft = $timeout - ($ts - $startts);
					if ($timeleft < 0)  $timeleft = 0;
				}

				if (!count($readfps) && !count($writefps))  usleep(($timeleft !== false && $timeleft < 0.25 ? $timeleft : 250000));
				else
				{
					$exceptfps = NULL;
					$result = @stream_select($readfps, $writefps, $exceptfps, ($timeleft > 1 ? 1 : 0), ($timeleft > 1 ? 0 : ($timeleft - (int)$timeleft) * 1000000));
					if ($result === false)  break;

					// Handle stdin.
					if (isset($pipes[0]) && $stdindata !== "")
					{
						$result = @fwrite($pipes[0], substr($stdindata, 0, 4096));
						if ($result)  $stdindata = (string)substr($stdindata, $result);
					}

					// Handle stdout.
					if (isset($pipes[1]))
					{
						$data = @fread($pipes[1], 65536);
						if ($data === false || ($data === "" && feof($pipes[1])))
						{
							fclose($pipes[1]);

							unset($pipes[1]);
						}
						else
						{
							$stdoutdata .= $data;
						}
					}

					// Handle stderr.
					if (isset($pipes[2]))
					{
						$data = @fread($pipes[2], 65536);
						if ($data === false || ($data === "" && feof($pipes[2])))
						{
							fclose($pipes[2]);

							unset($pipes[2]);
						}
						else
						{
							$stderrdata .= $data;
						}
					}
				}

				// Verify that the process is stll running.
				if ($proc !== false)
				{
					$pinfo = @proc_get_status($proc);
					if (!$pinfo["running"])
					{
						if (isset($pipes[0]))  fclose($pipes[0]);
						unset($pipes[0]);

						$proc = false;
					}
				}

				if ($timeleft === 0)  break;
			} while ($proc !== false || count($pipes));

			return array("success" => true, "proc" => $proc, "stdinleft" => $stdindata, "stdout" => $stdoutdata, "stderr" => $stderrdata);
		}

		public static function TerminateProcess($id, $children = true, $force = true)
		{
			$id = (int)$id;
			$os = php_uname("s");
			$windows = (strtoupper(substr($os, 0, 3)) == "WIN");

			if ($windows)
			{
				if (($exefile = self::FindExecutable("taskkill.exe")) !== false)
				{
					// Included with Windows XP Pro and later.
					$cmd = escapeshellarg($exefile);
					if ($children)  $cmd .= " /T";
					if ($force)  $cmd .= " /F";
					$cmd .= " /PID " . $id . " 2>&1 > NUL";

					ob_start();
					@system($cmd);
					ob_end_clean();

					return true;
				}
				else if (($exefile = self::FindExecutable("pskill.exe", __DIR__)) !== false)
				{
					// Gently terminating isn't possible with pskill.  Taskkill is more frequently available these days though.
					$cmd = escapeshellarg($exefile);
					if ($children)  $cmd .= " -t";
					$cmd .= " " . $id . " 2>&1 > NUL";

					ob_start();
					@system($cmd);
					ob_end_clean();

					return true;
				}
			}
			else
			{
				// Other OSes require parsing output from 'ps'.
				$ps = self::FindExecutable("ps", "/bin");
				$kill = self::FindExecutable("kill", "/bin");

				if ($ps !== false && (function_exists("posix_kill") || $kill !== false))
				{
					$lines = array();
					$cmd = escapeshellarg($ps) . " -ax -o ppid,pid";
					@exec($cmd, $lines);

					$ids = array($id);

					if ($children)
					{
						$childmap = array();
						foreach ($lines as $line)
						{
							if (preg_match('/^\s*?(\d+)\s+?(\d+)\s*$/', $line, $matches))
							{
								$ppid = (int)$matches[1];

								if (!isset($childmap[$ppid]))  $childmap[$ppid] = array();
								$childmap[$ppid][] = (int)$matches[2];
							}
						}

						$ids2 = $ids;
						while (count($ids2))
						{
							$id = array_shift($ids2);

							if (isset($childmap[$id]))
							{
								foreach ($childmap[$id] as $id2)
								{
									$ids[] = $id2;
									$ids2[] = $id2;
								}
							}
						}
					}

					foreach ($ids as $id)
					{
						if (function_exists("posix_kill"))  posix_kill($id, ($force ? 9 : 15));
						else
						{
							$cmd = escapeshellarg($kill) . ($force ? " -9" : "") . " " . $id . " 2>&1 >/dev/null";

							ob_start();
							@system($cmd);
							ob_end_clean();
						}
					}

					return true;
				}
			}

			return false;
		}

		// Constant-time string comparison.  Ported from CubicleSoft C++ code.
		// Copied from string basics file.
		protected static function CTstrcmp($secret, $userinput)
		{
			$sx = 0;
			$sy = strlen($secret);
			$uy = strlen($userinput);
			$result = $sy - $uy;
			for ($ux = 0; $ux < $uy; $ux++)
			{
				$result |= ord($userinput{$ux}) ^ ord($secret{$sx});
				$sx = ($sx + 1) % $sy;
			}

			return $result;
		}

		protected static function PHTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>