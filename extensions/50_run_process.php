<?php
	// Runs any process on the system that the user has rights to run.
	// This extension should never be used without the security token extension.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class PAS_Extension_50_run_process
	{
		private $processes, $stats, $tagstats, $nextstart, $monitors, $nextid;

		public function InitServer()
		{
			$this->processes = array();
			$this->stats = array("queued" => 0, "running" => 0, "terminated" => 0, "removed" => 0, "failed" => 0);
			$this->tagstats = array();
			$this->nextstart = array();
			$this->monitors = array();
			$this->nextid = 1;
		}

		public function ServerReady()
		{
		}

		// Walk the queue looking for processes that are ready to start.
		public function StartProcesses()
		{
			global $baseenv;

			$ts = microtime(true);

			foreach ($this->nextstart as $cid => $ts2)
			{
				if ($ts2 > $ts)  break;

				// Check the start rules.
				$data = &$this->processes[$cid]["data"];
				if (isset($data["rules"]["maxrunning"]) && $data["rules"]["maxrunning"] > 0 && isset($this->tagstats[$data["tag"]]) && $this->tagstats[$data["tag"]]["running"] >= $data["rules"]["maxrunning"])
				{
					continue;
				}

				unset($this->nextstart[$cid]);

				$options = array();

				if (isset($data["stdin"]))  $options["stdin"] = $data["stdin"];
				if (isset($data["stdout"]))  $options["stdout"] = $data["stdout"];
				if (isset($data["stderr"]))  $options["stderr"] = $data["stderr"];
				if (isset($data["dir"]) && is_string($data["dir"]))  $options["dir"] = $data["dir"];

				if (isset($data["env"]) && is_array($data["env"]))
				{
					$env = array();
					foreach ($data["env"] as $key => $val)
					{
						if (is_string($val))  $env[$key] = $val;
					}

					$options["env"] = $env;
				}

				if (!isset($options["env"]))  $options["env"] = ProcessHelper::GetCleanEnvironment();

				if (isset($data["extraenv"]) && is_array($data["extraenv"]))
				{
					foreach ($data["extraenv"] as $key => $val)
					{
						if (is_string($val))  $options["env"][$key] = $val;
					}
				}

				// PHP App Server specific environment variables.
				if (!isset($options["env"]["DOCUMENT_ROOT_USER"]))  $options["env"]["DOCUMENT_ROOT_USER"] = $baseenv["DOCUMENT_ROOT_USER"];
				if (!isset($options["env"]["PAS_PROG_FILES"]))  $options["env"]["PAS_PROG_FILES"] = $baseenv["PAS_PROG_FILES"];
				if (!isset($options["env"]["PAS_USER_FILES"]))  $options["env"]["PAS_USER_FILES"] = $baseenv["PAS_USER_FILES"];
				if (!isset($options["env"]["PAS_ROOT"]))  $options["env"]["PAS_ROOT"] = $baseenv["PAS_ROOT"];
				if (!isset($options["env"]["PAS_SECRET"]))  $options["env"]["PAS_SECRET"] = $baseenv["PAS_SECRET"];

				// When using these async options, the target process has 3 seconds to connect back into the TCP/IP server with the correct token.
				// Requires the target process to support this feature.
				if ((isset($data["async_stdin"]) && $data["async_stdin"]) || (isset($data["async_stdcmd"]) && $data["async_stdcmd"]))
				{
					$result = ProcessHelper::StartTCPServer();
					$servertoken = $result["token"];

					// Once connected, this closes any open stdin pipe and this pipe becomes a non-blocking stdin.
					if (isset($data["async_stdin"]) && $data["async_stdin"])
					{
						$options["env"]["X_ASYNC_STDIN_HOST"] = $result["ip"];
						$options["env"]["X_ASYNC_STDIN_PORT"] = $result["port"];
						$options["env"]["X_ASYNC_STDIN_TOKEN"] = $result["token"];
					}

					if (isset($data["async_stdcmd"]) && $data["async_stdcmd"])
					{
						$options["env"]["X_ASYNC_STDCMD_HOST"] = $result["ip"];
						$options["env"]["X_ASYNC_STDCMD_PORT"] = $result["port"];
						$options["env"]["X_ASYNC_STDCMD_TOKEN"] = $result["token"];
					}
				}

				$tag = $data["tag"];

				$this->stats["queued"]--;
				$this->tagstats[$tag]["queued"]--;

				$result = ProcessHelper::StartProcess($data["cmd"], $options);
				if (!$result["success"])
				{
					$result["state"] = "error";

					$this->processes[$cid] = $result;

					$this->stats["failed"]++;

					if ($this->tagstats[$tag]["queued"] < 1 && $this->tagstats[$tag]["running"] < 1 && $this->tagstats[$tag]["terminated"] < 1)  unset($this->tagstats[$tag]);
				}
				else
				{
					// Finalize special pipes.
					if ((isset($data["async_stdin"]) && $data["async_stdin"]) || (isset($data["async_stdcmd"]) && $data["async_stdcmd"]))
					{
						$pipes = array();

						if (isset($data["async_stdin"]) && $data["async_stdin"])  $pipes[0] = false;
						if (isset($data["async_stdcmd"]) && $data["async_stdcmd"])  $pipes[3] = false;

						ProcessHelper::GetTCPPipes($pipes, $servertoken, false, 3);

						if ($pipes[0] !== false)
						{
							fclose($result["pipes"][0]);

							$result["pipes"][0] = $pipes[0];
						}

						if ($pipes[3] !== false)  $result["pipes"][3] = $pipes[3];

						// Don't leak information.
						unset($result["info"]["env"]["X_ASYNC_STDIN_HOST"]);
						unset($result["info"]["env"]["X_ASYNC_STDIN_PORT"]);
						unset($result["info"]["env"]["X_ASYNC_STDIN_TOKEN"]);

						unset($result["info"]["env"]["X_ASYNC_STDCMD_HOST"]);
						unset($result["info"]["env"]["X_ASYNC_STDCMD_PORT"]);
						unset($result["info"]["env"]["X_ASYNC_STDCMD_TOKEN"]);
					}

					// The process started successfully.
					$ts = microtime(true);
					$result["tag"] = $tag;
					$result["state"] = "running";
					$result["stats"] = array(
						"queued" => $this->processes[$cid]["stats"]["queued"],
						"start" => $ts,
						"lastmonitor" => $ts,
						"terminated" => false,
						"stdin" => 0,
						"stdout" => 0,
						"stderr" => 0,
						"stdcmd" => 0
					);
					$result["attached"] = false;
					$result["streams"] = false;
					$result["stdinopen"] = (isset($result["pipes"][0]));
					$result["stdindata"] = "";
					$result["stdoutdata"] = "";
					$result["stderrdata"] = "";
					$result["stdcmdopen"] = (isset($result["pipes"][3]));
					$result["stdcmddata"] = "";
					$result["hadstderr"] = false;
					$result["currline"] = "";
					$result["scrollback"] = array();
					$result["history"] = array();
					$result["extra"] = (isset($data["extra"]) ? $data["extra"] : false);

					$this->processes[$cid] = $result;

					$this->NotifyMonitors($cid, "start");

					$this->stats["running"]++;
					$this->tagstats[$tag]["running"]++;
				}
			}
		}

		public function UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
		{
			global $wsserver;

			$prefix .= "ext_50_rp_";

			// Start processes.
			$this->StartProcesses();

			foreach ($this->processes as $cid => &$info)
			{
				// Skip queued items and remove error states.
				if ($info["state"] === "queued")
				{
					$diff = (int)($info["data"]["rules"]["start"] - microtime(true));
					if ($diff < 1)  $diff = 1;
					if ($timeout > $diff)  $timeout = $diff;

					continue;
				}

				if ($info["state"] === "error")
				{
					unset($this->processes[$cid]);

					continue;
				}

				// Handle stdout and stderr.
				$streams = false;
				if (isset($info["pipes"][1]) && $info["stdoutdata"] < 65536)
				{
					$data = fread($info["pipes"][1], 65536);
					if ($data === false || ($data === "" && feof($info["pipes"][1])))
					{
						fclose($info["pipes"][1]);
						unset($info["pipes"][1]);

						$this->NotifyMonitors($cid, "close_stdout");
					}
					else if ($data !== "")
					{
						$info["stdoutdata"] .= $data;
						$info["stats"]["stdout"] += strlen($data);
						$streams = true;
					}
				}

				if (isset($info["pipes"][2]) && $info["stderrdata"] < 65536)
				{
					$data = fread($info["pipes"][2], 65536);
					if ($data === false || ($data === "" && feof($info["pipes"][2])))
					{
						fclose($info["pipes"][2]);
						unset($info["pipes"][2]);

						$this->NotifyMonitors($cid, "close_stderr");
					}
					else if ($data !== "")
					{
						$info["stderrdata"] .= $data;
						$info["stats"]["stderr"] += strlen($data);
						if (!$info["hadstderr"])
						{
							$info["hadstderr"] = true;

							$this->NotifyMonitors($cid, "had_stderr");
						}
						$streams = true;
					}
				}

				if (isset($info["pipes"][1]) && $info["stdoutdata"] < 65536)  $readfps[$prefix . $cid . "_o"] = $info["pipes"][1];
				if (isset($info["pipes"][2]) && $info["stderrdata"] < 65536)  $readfps[$prefix . $cid . "_e"] = $info["pipes"][2];

				$ws = false;
				if ($info["attached"] !== false)
				{
					$client = $wsserver->GetClient($info["attached"]);

					if ($client === false)
					{
						$info["attached"] = false;

						$this->NotifyMonitors($cid, "detach");
					}
					else
					{
						$ws = $client->websocket;

						$ws->NeedsWrite();
					}
				}

				// Move output data into scrollback.
				do
				{
					$data2 = "";
					while ($info["stdoutdata"] !== "" && ($ws === false || !$ws->NumWriteMessages()))
					{
						// Try to find a newline in the data.
						$pos = strpos($info["stdoutdata"], "\n");
						if ($pos === false)
						{
							$data = $info["stdoutdata"];
							$info["stdoutdata"] = "";
						}
						else
						{
							$data = substr($info["stdoutdata"], 0, $pos + 1);
							$info["stdoutdata"] = (string)substr($info["stdoutdata"], $pos + 1);
						}

						$streams = true;

						if ($ws !== false)
						{
							$data2 .= $data;

							if (strlen($data2 > 1000))
							{
								$data2 = array(
									"channel" => $cid,
									"success" => true,
									"action" => "stdout",
									"data" => base64_encode($data2)
								);

								$ws->Write(json_encode($data2, JSON_UNESCAPED_SLASHES), WebSocket::FRAMETYPE_TEXT);

								$ws->NeedsWrite();

								$data2 = "";
							}
						}

						$info["currline"] .= $data;
						while (strlen($info["currline"]) > 1000)
						{
							$info["scrollback"][] = substr($info["currline"], 0, 1000);
							$info["currline"] = (string)substr($info["currline"], 1000);

							if (count($info["scrollback"]) > 10000)  array_shift($info["scrollback"]);
						}

						if ($pos !== false || !isset($info["pipes"][1]))
						{
							$info["scrollback"][] = $info["currline"];
							$info["currline"] = "";

							if (count($info["scrollback"]) > 10000)  array_shift($info["scrollback"]);

							// Go handle stderr (if any).
							if ($info["stderrdata"] !== "")  break;
						}
					}

					if ($ws !== false && $data2 !== "")
					{
						$data2 = array(
							"channel" => $cid,
							"success" => true,
							"action" => "stdout",
							"data" => base64_encode($data2)
						);

						$ws->Write(json_encode($data2, JSON_UNESCAPED_SLASHES), WebSocket::FRAMETYPE_TEXT);

						$ws->NeedsWrite();
					}

					if ($info["currline"] === "")
					{
						$data2 = "";
						while ($info["stderrdata"] !== "" && ($ws === false || !$ws->NumWriteMessages()))
						{
							// Try to find a newline in the data.
							$pos = strpos($info["stderrdata"], "\n");
							if ($pos === false)
							{
								// Don't write strerr into the channel if stdout is still open or has data to send.
								if (isset($info["pipes"][1]) || $info["stdoutdata"] !== "")  break;

								$data = $info["stderrdata"];
								$info["stderrdata"] = "";
							}
							else
							{
								$data = substr($info["stderrdata"], 0, $pos + 1);
								$info["stderrdata"] = (string)substr($info["stderrdata"], $pos + 1);
							}

							$streams = true;

							if ($ws !== false)
							{
								$data2 .= $data;

								if (strlen($data2 > 1000))
								{
									$data2 = array(
										"channel" => $cid,
										"success" => true,
										"action" => "stderr",
										"data" => base64_encode($data2)
									);

									$ws->Write(json_encode($data2, JSON_UNESCAPED_SLASHES), WebSocket::FRAMETYPE_TEXT);

									$ws->NeedsWrite();

									$data2 = "";
								}
							}

							$info["currline"] .= $data;
							while (strlen($info["currline"]) > 1000)
							{
								$info["scrollback"][] = substr($info["currline"], 0, 1000);
								$info["currline"] = (string)substr($info["currline"], 1000);

								if (count($info["scrollback"]) > 10000)  array_shift($info["scrollback"]);
							}

							if ($pos !== false || !isset($info["pipes"][2]))
							{
								$info["scrollback"][] = $info["currline"];
								$info["currline"] = "";

								if (count($info["scrollback"]) > 10000)  array_shift($info["scrollback"]);
							}
						}

						if ($ws !== false && $data2 !== "")
						{
							$data2 = array(
								"channel" => $cid,
								"success" => true,
								"action" => "stderr",
								"data" => base64_encode($data2)
							);

							$ws->Write(json_encode($data2, JSON_UNESCAPED_SLASHES), WebSocket::FRAMETYPE_TEXT);

							$ws->NeedsWrite();
						}
					}
				} while ($info["stdoutdata"] !== "" && ($ws === false || !$ws->NumWriteMessages()));

				// Handle stdin.
				if (isset($info["pipes"][0]))
				{
					if ($info["stdindata"] !== "")
					{
						$result = fwrite($info["pipes"][0], substr($info["stdindata"], 0, 4096));
						if ($result)
						{
							$info["stdindata"] = (string)substr($info["stdindata"], $result);

							$streams = true;
						}
					}

					if (!$info["stdinopen"] && $info["stdindata"] === "")
					{
						fclose($info["pipes"][0]);
						unset($info["pipes"][0]);

						$this->NotifyMonitors($cid, "eof_stdin");
					}
					else if ($info["stdindata"] !== "")
					{
						$writefps[$prefix . $cid . "_i"] = $info["pipes"][0];
					}
				}

				// Handle stdcmd.
				if (isset($info["pipes"][3]))
				{
					if ($info["stdcmddata"] !== "")
					{
						$result = fwrite($info["pipes"][3], substr($info["stdcmddata"], 0, 4096));
						if ($result)
						{
							$info["stdcmddata"] = (string)substr($info["stdcmddata"], $result);

							$streams = true;
						}
					}

					if (!$info["stdcmdopen"] && $info["stdcmddata"] === "")
					{
						fclose($info["pipes"][3]);
						unset($info["pipes"][3]);

						$this->NotifyMonitors($cid, "eof_stdcmd");
					}
					else if ($info["stdcmddata"] !== "")
					{
						$writefps[$prefix . $cid . "_i"] = $info["pipes"][3];
					}
				}

				if ($streams)  $info["streams"] = true;

				$pinfo = @proc_get_status($info["proc"]);
				if (!$pinfo["running"] && $info["state"] === "running")
				{
					$info["state"] = "terminated";
					$info["stats"]["terminated"] = microtime(true);
					$info["stdinopen"] = false;
					$info["stdindata"] = "";
					$info["stdcmdopen"] = false;
					$info["stdcmddata"] = "";

					$tag = $info["tag"];

					$this->stats["running"]--;
					if (isset($this->tagstats[$tag]))  $this->tagstats[$tag]["running"]--;
					$this->stats["terminated"]++;
					if (isset($this->tagstats[$tag]))  $this->tagstats[$tag]["terminated"]++;

					$this->NotifyMonitors($cid, "terminated");
				}
				else if ($info["streams"] && count($this->monitors))
				{
					$ts = microtime(true);
					if ($ts - $info["stats"]["lastmonitor"] > 0.75)
					{
						$info["streams"] = false;
						$info["stats"]["lastmonitor"] = $ts;

						$this->NotifyMonitors($cid, "streams", false);
					}
					else if ($timeout > 1)
					{
						$timeout = 1;
					}
				}

				// If all data has been sent and received, the process has ended, and there is an attached WebSocket, then notify everyone and cleanup the process.
				if ($info["attached"] !== false && $ws !== false && $info["state"] === "terminated" && !count($info["pipes"]) && $info["stdoutdata"] === "" && $info["stderrdata"] === "")
				{
					proc_close($info["proc"]);

					$this->stats["terminated"]--;
					$this->stats["removed"]++;

					$this->NotifyMonitors($cid, "removed");

					$tag = $info["tag"];

					if (isset($this->tagstats[$tag]))
					{
						$this->tagstats[$tag]["terminated"]--;

						if ($this->tagstats[$tag]["queued"] < 1 && $this->tagstats[$tag]["running"] < 1 && $this->tagstats[$tag]["terminated"] < 1)  unset($this->tagstats[$tag]);
					}

					unset($this->processes[$cid]);
				}
			}
		}

		public function CanHandleRequest($method, $url, $path, $client)
		{
			global $serverexts;

			// Verify that the security token is valid.  Probably redundant but it's better to be safe than have a security hole.
			if (!isset($serverexts["1_security_token"]) || $serverexts["1_security_token"]->CanHandleRequest($method, $url, $path, $client))  return false;

			if ($path === "/run-process/")  return true;

			return false;
		}

		public function RequireAuthToken()
		{
			return true;
		}

		public function NotifyMonitors($channel, $action, $important = true)
		{
			global $wsserver;

			if (!count($this->monitors) && !$important)  return;

			$info = &$this->processes[$channel];
			if (count($this->monitors))  $info["stats"]["lastmonitor"] = microtime(true);

			$data = array(
				"channel" => $channel,
				"success" => true,
				"action" => "info",
				"monitor" => $action,
				"state" => $info["state"],
				"realpid" => $info["pid"],
				"tag" => $info["tag"],
				"stats" => $info["stats"],
				"attached" => ($info["attached"] !== false),
				"stdinopen" => $info["stdinopen"],
				"stdindata" => strlen($info["stdindata"]),
				"stdoutdata" => strlen($info["stdoutdata"]),
				"stderrdata" => strlen($info["stderrdata"]),
				"stdcmdopen" => $info["stdcmdopen"],
				"stdcmddata" => strlen($info["stdcmddata"]),
				"hadstderr" => $info["hadstderr"],
				"scrollback" => count($info["scrollback"]),
				"extra" => $info["extra"],
				"stats" => $this->stats,
				"tagstats" => $this->tagstats[$info["tag"]]
			);

			foreach ($this->monitors as $id => $all)
			{
				// Skip sending to a monitor if the channel's attached WebSocket is the same as this monitor's WebSocket.
				if ($important && $info["attached"] === $id)  continue;

				$client = $wsserver->GetClient($id);

				if ($client === false)  unset($this->monitors[$id]);
				else if ($all || $important)
				{
					// If this is a new process, also include the full information blob for superusers.
					if ($action === "start")
					{
						if ($client->appdata["auth"] === true)  $data["info"] = $info["info"];
						else  unset($data["info"]);
					}

					$data2 = json_encode($data, JSON_UNESCAPED_SLASHES);

					$client->websocket->Write($data2, WebSocket::FRAMETYPE_TEXT);
				}
			}

			// If this is important, then send the message to an attached websocket.
			if ($important && $info["attached"] !== false)
			{
				$client = $wsserver->GetClient($info["attached"]);

				if ($client !== false)
				{
					$data["action"] = $action;
					unset($data["monitor"]);

					// Pass 'attachextra' + history when attaching.
					if ($action === "attach")
					{
						$data["attachextra"] = $info["attachextra"];
						$data["history"] = $info["history"];
					}

					$data2 = json_encode($data, JSON_UNESCAPED_SLASHES);

					$client->websocket->Write($data2, WebSocket::FRAMETYPE_TEXT);
				}
			}
		}

		public function ProcessRequest($method, $path, $client, &$data)
		{
			if (!is_array($data))  return false;

			if (!isset($data["action"]))
			{
				$result = array("channel" => 0, "success" => false, "error" => "Missing action.", "errorcode" => "missing_action");
			}
			else if ($data["action"] == "test")
			{
				$result = array(
					"channel" => 0,
					"success" => true,
					"action" => "test",
					"allowed" => array(
						"clear" => true,
						"start" => true,
						"close_stdin" => true,
						"close_stdcmd" => true,
						"terminate" => true,
						"remove" => true
					)
				);
			}
			else if ($data["action"] == "list")
			{
				$result = array(
					"channel" => 0,
					"success" => true,
					"action" => "list",
					"channels" => array(
					),
					"stats" => $this->stats,
					"tagstats" => array()
				);

				foreach ($this->processes as $cid => &$info)
				{
					if (!isset($data["tag"]) || $data["tag"] === $info["tag"])
					{
						if (!isset($result["tagstats"][$info["tag"]]))  $result["tagstats"][$info["tag"]] = $this->tagstats[$info["tag"]];

						if ($info["state"] === "queued")
						{
							$cinfo = array(
								"channel" => $cid,
								"tag" => $info["tag"],
								"state" => $info["state"],
								"stats" => $info["stats"]
							);

							if ($client->appdata["auth"] === true)  $cinfo["data"] = $info["data"];

							$result["channels"][] = $cinfo;
						}
						else if ($info["state"] !== "error")
						{
							$cinfo = array(
								"channel" => $cid,
								"tag" => $info["tag"],
								"state" => $info["state"],
								"stats" => $info["stats"],
								"attached" => ($info["attached"] !== false),
								"stdinopen" => $info["stdinopen"],
								"stdindata" => strlen($info["stdindata"]),
								"stdoutdata" => strlen($info["stdoutdata"]),
								"stderrdata" => strlen($info["stderrdata"]),
								"stdcmdopen" => $info["stdcmdopen"],
								"stdcmddata" => strlen($info["stdcmddata"]),
								"hadstderr" => $info["hadstderr"],
								"scrollback" => count($info["scrollback"]),
								"extra" => $info["extra"]
							);

							if ($client->appdata["auth"] === true)  $cinfo["info"] = $info["info"];

							$result["channels"][] = $cinfo;
						}
					}
				}
			}
			else if ($data["action"] == "monitor")
			{
				if ($method !== false)  $result = array("channel" => 0, "success" => false, "error" => "A WebSocket connection is required for monitoring support.", "errorcode" => "websocket_required");
				else if (!isset($data["mode"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'monitor'.", "errorcode" => "missing_mode");
				else if (!is_string($data["mode"]) || ($data["mode"] !== "all" && $data["mode"] !== "important" && $data["mode"] !== "none"))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'mode'.  Expected 'all', 'important', or 'none'.", "errorcode" => "invalid_mode");
				else
				{
					if ($data["mode"] === "all")  $this->monitors[$client->id] = true;
					else if ($data["mode"] === "important")  $this->monitors[$client->id] = false;
					else  unset($this->monitors[$client->id]);

					$result = array(
						"channel" => 0,
						"success" => true,
						"action" => "monitor",
						"mode" => $data["mode"]
					);
				}
			}
			else if ($data["action"] == "clear")
			{
				if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["state"] === "queued")  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's process has not started running.", "errorcode" => "process_queued");
				else
				{
					// Clear scrollback.
					$channel = (int)$data["channel"];
					$this->processes[$channel]["scrollback"] = array();

					// Clear stdoutdata, stderrdata, and currline if the process has ended.
					if ($this->processes[$channel]["state"] === "running")
					{
						$this->processes[$channel]["stdoutdata"] = "";
						$this->processes[$channel]["stderrdata"] = "";
						$this->processes[$channel]["currline"] = "";
					}

					$this->NotifyMonitors($channel, "clear");

					$result = array(
						"channel" => $channel,
						"success" => true,
						"action" => "clear"
					);
				}
			}
			else if ($data["action"] == "attach")
			{
				// WebSocket required to attach to a process.
				if ($method !== false)  $result = array("channel" => 0, "success" => false, "error" => "A WebSocket connection is required to attach to a running process channel.", "errorcode" => "websocket_required");
				else if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["state"] === "queued")  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's process has not started running.", "errorcode" => "process_queued");
				else if ($this->processes[$data["channel"]]["attached"] !== false && $wsserver->GetClient($this->processes[$data["channel"]]["attached"]) !== false)  $result = array("channel" => 0, "success" => false, "error" => "Another client is already attached to the specified channel.", "errorcode" => "channel_already_attached");
				else
				{
					$channel = (int)$data["channel"];
					$info = &$this->processes[$channel];
					$info["attached"] = $client->id;

					// Rewrite stdout so all scrollback gets fed into the data stream for the client.
					$info["stdoutdata"] = implode("", $info["scrollback"]) . $info["currline"] . $info["stdoutdata"];
					$info["currline"] = "";
					$info["scrollback"] = array();

					$info["attachextra"] = (isset($data["extra"]) ? $data["extra"] : false);
					$this->NotifyMonitors($channel, "attach");
					unset($info["attachextra"]);

					$result = array(
						"channel" => $channel,
						"success" => true,
						"action" => "attach",
						"state" => $info["state"],
						"realpid" => $info["pid"],
						"stdinopen" => $info["stdinopen"],
						"stdcmdopen" => $info["stdcmdopen"],
						"hadstderr" => $info["hadstderr"],
						"extra" => $info["extra"]
					);

					if ($client->appdata["auth"] === true)  $result["info"] = $info["info"];
				}
			}
			else if ($data["action"] == "detach")
			{
				// Attached WebSocket required.
				if ($method !== false)  $result = array("channel" => 0, "success" => false, "error" => "An attached WebSocket connection is required to detach from a running process channel.", "errorcode" => "websocket_required");
				else if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["attached"] !== $client->id)  $result = array("channel" => 0, "success" => false, "error" => "Another client is attached to the specified channel.", "errorcode" => "client_not_attached");
				else
				{
					$channel = (int)$data["channel"];

					$this->NotifyMonitors($channel, "detach");

					$this->processes[$channel]["attached"] = false;

					$result = array(
						"channel" => $channel,
						"success" => true,
						"action" => "detach"
					);
				}
			}
			else if ($data["action"] == "start")
			{
				// Start a new process.
				if (!isset($data["cmd"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'cmd'.", "errorcode" => "missing_cmd");
				else if (!is_string($data["cmd"]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'cmd'.  Expected a string.", "errorcode" => "invalid_cmd");
				else if (!isset($data["tag"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'tag'.", "errorcode" => "missing_tag");
				else if (!is_string($data["tag"]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'tag'.  Expected a string.", "errorcode" => "invalid_tag");
				else
				{
					if (!isset($data["rules"]) || !is_array($data["rules"]))  $data["rules"] = array();
					if (!isset($data["rules"]["start"]) || !is_numeric($data["rules"]["start"]))  $data["rules"]["start"] = time();

					if (isset($data["rules"]["maxqueued"]) && $data["rules"]["maxqueued"] > 0 && isset($this->tagstats[$data["tag"]]) && $this->tagstats[$data["tag"]]["queued"] >= $data["rules"]["maxqueued"])
					{
						$result = array("channel" => 0, "success" => false, "error" => "The maximum queue limit as per the input rules has been reached.", "errorcode" => "max_queued");
					}
					else
					{
						// Add the process to the queue.
						$result = array(
							"success" => true,
							"tag" => $data["tag"],
							"state" => "queued",
							"stats" => array(
								"queued" => microtime(true)
							),
							"data" => $data
						);

						$channel = $this->nextid;
						$this->nextid++;

						$this->processes[$channel] = $result;
						$this->stats["queued"]++;

						if (!isset($this->tagstats[$data["tag"]]))  $this->tagstats[$data["tag"]] = array("queued" => 0, "running" => 0, "terminated" => 0);
						$this->tagstats[$data["tag"]]["queued"]++;

						$this->nextstart[$channel] = $data["rules"]["start"];
						asort($this->nextstart);

						// Start processes.
						$this->StartProcesses();

						$info = $this->processes[$channel];
						if ($info["state"] === "error")
						{
							$result = $info;
							$result["channel"] = 0;
						}
						else if ($info["state"] === "queued")
						{
							$result = array(
								"channel" => $channel,
								"success" => true,
								"tag" => $info["tag"],
								"state" => $info["state"],
								"stats" => $info["stats"]
							);

							if ($client->appdata["auth"] === true)  $result["data"] = $info["data"];
						}
						else
						{
							$result = array(
								"channel" => $channel,
								"success" => true,
								"action" => "start",
								"state" => $info["state"],
								"realpid" => $info["pid"],
								"tag" => $info["tag"],
								"stdinopen" => $info["stdinopen"],
								"stdcmdopen" => $info["stdcmdopen"],
								"history" => $info["history"],
								"extra" => $info["extra"]
							);

							if ($client->appdata["auth"] === true)  $result["info"] = $info["info"];
						}
					}
				}
			}
			else if ($data["action"] == "send_stdin")
			{
				if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["state"] !== "running")  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's process is not running.", "errorcode" => "process_not_running");
				else if (!$this->processes[$data["channel"]]["stdinopen"])  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's stdin pipe is closed.", "errorcode" => "stdin_closed");
				else if ($this->processes[$data["channel"]]["attached"] !== false && $this->processes[$data["channel"]]["attached"] !== $client->id)  $result = array("channel" => 0, "success" => false, "error" => "Another client is attached to the specified channel.", "errorcode" => "client_mismatch");
				else if (!isset($data["data"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'data'.", "errorcode" => "missing_data");
				else if (!is_string($data["data"]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'data'.  Expected a string.", "errorcode" => "invalid_data");
				else
				{
					$channel = (int)$data["channel"];
					$info = &$this->processes[$channel];

					$data2 = base64_decode($data["data"]);
					$info["stdindata"] .= $data2;
					$info["stats"]["stdin"] += strlen($data2);

					if (isset($data["history"]) && is_int($data["history"]))
					{
						// Add the line to scrollback to attempt to preserve the text display when reattaching.
						$info["scrollback"][] = $info["currline"] . $data2;
						$info["currline"] = "";
						if (count($info["scrollback"]) > 10000)  array_shift($info["scrollback"]);

						// Don't add whitespace prefixed items to the history.
						if (ltrim($data2) === $data2)
						{
							$info["history"][] = rtrim($data2);

							while (count($info["history"]) > $data["history"])  array_shift($info["history"]);
						}
					}

					$this->NotifyMonitors($channel, "send_stdin", false);

					$result = array(
						"channel" => $channel,
						"success" => true,
						"action" => "send_stdin"
					);
				}
			}
			else if ($data["action"] == "close_stdin")
			{
				if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["state"] !== "running")  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's process is not running.", "errorcode" => "process_not_running");
				else
				{
					$channel = (int)$data["channel"];
					$this->processes[$channel]["stdinopen"] = false;

					$this->NotifyMonitors($channel, "close_stdin");

					$result = array(
						"channel" => $channel,
						"success" => true,
						"action" => "close_stdin"
					);
				}
			}
			else if ($data["action"] == "send_stdcmd")
			{
				if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["state"] !== "running")  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's process is not running.", "errorcode" => "process_not_running");
				else if (!$this->processes[$data["channel"]]["stdcmdopen"])  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's stdcmd pipe is closed.", "errorcode" => "stdcmd_closed");
				else if ($this->processes[$data["channel"]]["attached"] !== false && $this->processes[$data["channel"]]["attached"] !== $client->id)  $result = array("channel" => 0, "success" => false, "error" => "Another client is attached to the specified channel.", "errorcode" => "client_mismatch");
				else if (!isset($data["data"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'data'.", "errorcode" => "missing_data");
				else if (!is_string($data["data"]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'data'.  Expected a string.", "errorcode" => "invalid_data");
				else
				{
					$channel = (int)$data["channel"];

					$data2 = base64_decode($data["data"]);
					$this->processes[$channel]["stdcmddata"] .= $data2;
					$info["stats"]["stdcmd"] += strlen($data2);

					$this->NotifyMonitors($channel, "send_stdcmd", false);

					$result = array(
						"channel" => $channel,
						"success" => true,
						"action" => "send_stdcmd"
					);
				}
			}
			else if ($data["action"] == "close_stdcmd")
			{
				if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["state"] !== "running")  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's process is not running.", "errorcode" => "process_not_running");
				else
				{
					$channel = (int)$data["channel"];
					$this->processes[$channel]["stdcmdopen"] = false;

					$this->NotifyMonitors($channel, "close_stdcmd");

					$result = array(
						"channel" => $channel,
						"success" => true,
						"action" => "close_stdcmd"
					);
				}
			}
			else if ($data["action"] == "terminate")
			{
				if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["state"] === "queued")  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's process has not started running.", "errorcode" => "process_queued");
				else
				{
					// Verify that the process is still running.
					$channel = (int)$data["channel"];
					$pinfo = @proc_get_status($this->processes[$channel]["proc"]);

					if ($pinfo["running"] && !ProcessHelper::TerminateProcess($this->processes[$channel]["pid"]))  $result = array("channel" => 0, "success" => false, "error" => "Unable to terminate the process.  Expected a sufficient system application.", "errorcode" => "missing_system_app");
					else
					{
						// Note that only the process has been terminated.  Removal is a separate step either automated via WebSocket or using the remove action.
						// Nothing else needs to be done here since the main loop will update the status information.
						$result = array(
							"channel" => $channel,
							"success" => true,
							"action" => "terminate"
						);
					}
				}
			}
			else if ($data["action"] == "remove")
			{
				if (!isset($data["channel"]))  $result = array("channel" => 0, "success" => false, "error" => "Missing 'channel'.", "errorcode" => "missing_channel");
				else if (!is_numeric($data["channel"]) || !isset($this->processes[$data["channel"]]))  $result = array("channel" => 0, "success" => false, "error" => "Invalid 'channel'.", "errorcode" => "invalid_channel");
				else if ($this->processes[$data["channel"]]["state"] === "running")  $result = array("channel" => 0, "success" => false, "error" => "The specified channel's process is still running.", "errorcode" => "process_running");
				else if ($this->processes[$data["channel"]]["attached"] !== false && $this->processes[$data["channel"]]["attached"] !== $client->id)  $result = array("channel" => 0, "success" => false, "error" => "Another client is attached to the specified channel.", "errorcode" => "client_mismatch");
				else
				{
					// Remove the channel from the list.
					$channel = (int)$data["channel"];
					$state = $this->processes[$channel]["state"];
					$tag = $this->processes[$channel]["tag"];

					if ($state === "running")
					{
						foreach ($this->processes[$channel]["pipes"] as $fp)  fclose($fp);

						proc_close($this->processes[$channel]["proc"]);
					}

					$this->NotifyMonitors($channel, "removed");

					unset($this->nextstart[$channel]);

					if (isset($this->tagstats[$tag]))
					{
						if (isset($this->tagstats[$tag][$state]))  $this->tagstats[$tag][$state]--;

						if ($this->tagstats[$tag]["queued"] < 1 && $this->tagstats[$tag]["running"] < 1 && $this->tagstats[$tag]["terminated"] < 1)  unset($this->tagstats[$tag]);
					}

					unset($this->processes[$channel]);
					if (isset($this->stats[$state]))  $this->stats[$state]--;
					$this->stats["removed"]++;

					$result = array(
						"channel" => $channel,
						"success" => true,
						"action" => "remove"
					);
				}
			}
			else
			{
				$result = array("channel" => 0, "success" => false, "error" => "Invalid action.", "errorcode" => "invalid_action");
			}

			if (isset($data["msg_id"]))  $result["msg_id"] = $data["msg_id"];

			// WebSocket is preferred.
			if ($method === false)  return $result;

			// Prevent browsers and proxies from doing bad things.
			$client->SetResponseNoCache();

			$client->SetResponseContentType("application/json");
			$client->AddResponseContent(json_encode($result, JSON_UNESCAPED_SLASHES));
			$client->FinalizeResponse();

			return true;
		}

		public function ServerDone()
		{
			// Attempt to gracefully shutdown running processes.
			foreach ($this->processes as $cid => &$info)
			{
				if ($info["state"] !== "running")  unset($this->processes[$id]);
				else
				{
					$info["attached"] = false;
					$info["stdinopen"] = false;
					$info["stdcmdopen"] = false;
				}
			}

			$this->nextstart = array();
			$this->monitors = array();

			// Wait for up to 5 seconds for remaining processes to complete.
			$ts = microtime(true);
			do
			{
				$timeout = 1;
				$readfps = array();
				$writefps = array();
				$exceptfps = NULL;

				$this->UpdateStreamsAndTimeout("", $timeout, $readfps, $writefps);

				foreach ($this->processes as $cid => &$info)
				{
					if ($info["state"] !== "running")
					{
						foreach ($info["pipes"] as $fp)  fclose($fp);

						proc_close($info["proc"]);

						unset($this->processes[$cid]);
					}
				}

				if (!count($readfps) && !count($writefps))  usleep(250000);
				else
				{
					$result = @stream_select($readfps, $writefps, $exceptfps, $timeout);
					if ($result === false)  break;
				}
			} while (count($this->processes) && microtime(true) - $ts < 5);

			// Terminate remaining running processes.
			foreach ($this->processes as $cid => &$info)
			{
				ProcessHelper::TerminateProcess($info["pid"]);
			}
		}
	}
?>