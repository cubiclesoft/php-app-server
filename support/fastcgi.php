<?php
	// FastCGI client class.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class FastCGI
	{
		protected $fp, $client, $debug;
		protected $requests, $readyrequests, $readdata, $writedata, $writerecords;
		protected $connlimit, $reqlimit, $multiplex, $emptydisconnect;
		protected $rawrecvsize, $rawsendsize, $rawsendqueuesize;

		const RECORD_TYPE_BEGIN_REQUEST = 1;
		const RECORD_TYPE_ABORT_REQUEST = 2;
		const RECORD_TYPE_END_REQUEST = 3;
		const RECORD_TYPE_PARAMS = 4;
		const RECORD_TYPE_STDIN = 5;
		const RECORD_TYPE_STDOUT = 6;
		const RECORD_TYPE_STDERR = 7;
		const RECORD_TYPE_DATA = 8;
		const RECORD_TYPE_GET_VALUES = 9;
		const RECORD_TYPE_GET_VALUES_RESULT = 10;
		const RECORD_TYPE_UNKNOWN_TYPE = 11;

		const ROLE_RESPONDER = 1;
		const ROLE_AUTHORIZER = 2;
		const ROLE_FILTER = 3;

		const PROTOCOL_STATUS_REQUEST_COMPLETE = 0;
		const PROTOCOL_STATUS_CANT_MPX_CONN = 1;
		const PROTOCOL_STATUS_OVERLOADED = 2;
		const PROTOCOL_STATUS_UNKNOWN_ROLE = 3;

		public function __construct()
		{
			$this->Reset();
		}

		public function __destruct()
		{
			$this->Disconnect();
		}

		public function Reset()
		{
			$this->fp = false;
			$this->client = true;
			$this->debug = false;
			$this->requests = array();
			$this->readyrequests = array();
			$this->readdata = "";
			$this->writedata = "";
			$this->writerecords = array();
			$this->connlimit = 50;
			$this->reqlimit = 1;
			$this->multiplex = false;
			$this->emptydisconnect = false;
			$this->recvrecords = 0;
			$this->rawrecvsize = 0;
			$this->sendrecords = 0;
			$this->rawsendsize = 0;
			$this->rawsendqueuesize = 0;
		}

		public function SetServerMode($fp)
		{
			$this->fp = $fp;
			$this->client = false;

			stream_set_blocking($this->fp, 0);
		}

		public function SetClientMode()
		{
			$this->client = true;
		}

		public function SetDebug($debug)
		{
			$this->debug = (bool)$debug;
		}

		public function GetConnectionLimit()
		{
			return $this->connlimit;
		}

		public function SetConnectionLimit($limit)
		{
			$this->connlimit = (int)$limit;
		}

		public function GetConncurrencyLimit()
		{
			return $this->reqlimit;
		}

		public function SetConncurrencyLimit($limit)
		{
			$this->reqlimit = (int)$limit;
		}

		public function CanMultiplex()
		{
			return $this->multiplex;
		}

		public function SetMultiplex($multiplex)
		{
			$this->multiplex = (bool)$multiplex;
		}

		public function GetRecvRecords()
		{
			return $this->recvrecords;
		}

		public function GetRawRecvSize()
		{
			return $this->rawrecvsize;
		}

		public function GetSendRecords()
		{
			return $this->sendrecords;
		}

		public function GetRawSendSize()
		{
			return $this->rawsendsize;
		}

		public function GetRawSendQueueSize()
		{
			return $this->rawsendqueuesize;
		}

		public function Connect($host, $port = -1, $timeout = 10, $async = false)
		{
			$this->Disconnect();

			if (!function_exists("stream_socket_client") && !function_exists("fsockopen"))  return array("success" => false, "error" => self::FCGITranslate("The functions 'stream_socket_client' and 'fsockopen' do not exist."), "errorcode" => "function_check");

			if (!function_exists("stream_socket_client"))
			{
				if ($this->debug)  $this->fp = fsockopen($host, $port, $errornum, $errorstr, $timeout);
				else  $this->fp = @fsockopen($host, $port, $errornum, $errorstr, $timeout);
			}
			else
			{
				$context = @stream_context_create();

				if ($this->debug)  $this->fp = stream_socket_client($host . ($port > -1 ? ":" . $port : ""), $errornum, $errorstr, $timeout, ($async ? STREAM_CLIENT_ASYNC_CONNECT : STREAM_CLIENT_CONNECT), $context);
				else $this->fp = @stream_socket_client($host . ($port > -1 ? ":" . $port : ""), $errornum, $errorstr, $timeout, ($async ? STREAM_CLIENT_ASYNC_CONNECT : STREAM_CLIENT_CONNECT), $context);
			}

			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Unable to establish a connection to '%s'.", $host . ($port > -1 ? ":" . $port : "")), "info" => $errorstr . " (" . $errornum . ")", "errorcode" => "connect_failed");

			// Enable non-blocking mode.
			stream_set_blocking($this->fp, 0);

			return array("success" => true);
		}

		public function Disconnect()
		{
			if ($this->fp !== false)
			{
				@fclose($this->fp);

				$this->fp = false;
			}

			$this->requests = array();
			$this->readyrequests = array();
			$this->readdata = "";
			$this->writedata = "";
			$this->writerecords = array();
			$this->connlimit = 50;
			$this->reqlimit = 1;
			$this->multiplex = false;
			$this->emptydisconnect = false;
			$this->rawsendqueuesize = 0;
		}

		// Client only.  Depending on the FastCGI server, it may disconnect after this "request" is completed.
		public function RequestUpdatedLimits()
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The UpdateLimits() function is only available in client mode."), "errorcode" => "client_mode_only");

			$namevalues = array(
				"FCGI_MAX_CONNS" => "",
				"FCGI_MAX_REQS" => "",
				"FCGI_MPXS_CONNS" => "",
			);

			$contents = self::CreateNameValueChunks($namevalues);
			foreach ($contents as $content)
			{
				$this->WriteRecord(self::RECORD_TYPE_GET_VALUES, 0, $content);
			}

			return array("success" => true);
		}

		// Client only.
		public function BeginRequest($role, $keepalive = true)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The BeginRequest() function is only available in client mode."), "errorcode" => "client_mode_only");

			// Check limits.
			if (count($this->requests) && !$this->multiplex)  return array("success" => false, "error" => self::FCGITranslate("Multiplexing requests is not allowed."), "errorcode" => "no_multiplexing");
			if (count($this->requests) >= $this->reqlimit)  return array("success" => false, "error" => self::FCGITranslate("Maximum conncurrent requests reached for this connection."), "errorcode" => "conncurrency_limit");

			// Find an open ID.
			for ($x = 1; $x < 65536 && isset($this->requests[$x]); $x++);
			if ($x > 65535)  return array("success" => false, "error" => self::FCGITranslate("No request space left."), "errorcode" => "too_many_requests");

			$request = new stdClass();
			$request->id = $x;
			$request->role = (int)$role;
			$request->flags = ($keepalive ? 0x01 : 0x00);
			$request->stdinopen = true;
			$request->dataopen = ($request->role === self::ROLE_FILTER);
			$request->stdout = "";
			$request->stdoutcompleted = false;
			$request->stderr = "";
			$request->stderrcompleted = false;
			$request->ended = false;

			$this->requests[$request->id] = $request;

			// 2 bytes role, 1 byte flags, 5 bytes reserved.
			$content = pack("n", $request->role) . chr($request->flags) . "\x00\x00\x00\x00\x00";
			$this->WriteRecord(self::RECORD_TYPE_BEGIN_REQUEST, $request->id, $content);

			return array("success" => true, "id" => $request->id, "request" => $request);
		}

		public function GetRequest($requestid)
		{
			return (isset($this->requests[$requestid]) ? $this->requests[$requestid] : false);
		}

		// Client only.
		public function AbortRequest($requestid)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The AbortRequest() function is only available in client mode."), "errorcode" => "client_mode_only");
			if (!isset($this->requests[$requestid]))  return array("success" => false, "error" => self::FCGITranslate("The specified request ID does not exist."), "errorcode" => "invalid_request_id");

			$this->WriteRecord(self::RECORD_TYPE_ABORT_REQUEST, $request->id, $content);

			return array("success" => true);
		}

		// Server only.
		public function EndRequest($requestid, $appstatus, $protocolstatus = self::PROTOCOL_STATUS_REQUEST_COMPLETE)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The EndRequest() function is only available in server mode."), "errorcode" => "server_mode_only");

			// 4 bytes application status, 1 byte protocol status, 3 bytes reserved.
			$content = pack("N", (int)$appstatus) . chr($protocolstatus) . "\x00\x00\x00";
			$this->WriteRecord(self::RECORD_TYPE_END_REQUEST, $requestid, $content);

			return array("success" => true);
		}

		// Client only.
		public function SendParams($requestid, $params)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The SendParams() function is only available in client mode."), "errorcode" => "client_mode_only");
			if (!isset($this->requests[$requestid]))  return array("success" => false, "error" => self::FCGITranslate("The specified request ID does not exist."), "errorcode" => "invalid_request_id");

			$contents = self::CreateNameValueChunks($params);
			foreach ($contents as $content)
			{
				$this->WriteRecord(self::RECORD_TYPE_PARAMS, $requestid, $content);
			}

			return array("success" => true);
		}

		// Client only.
		public function IsStdinOpen($requestid)
		{
			if ($this->fp === false)  return false;
			if (!$this->client)  return false;
			if (!isset($this->requests[$requestid]))  return false;

			return $this->requests[$requestid]->stdinopen;
		}

		// Client only.
		public function SendStdin($requestid, $data)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The SendStdin() function is only available in client mode."), "errorcode" => "client_mode_only");
			if (!isset($this->requests[$requestid]))  return array("success" => false, "error" => self::FCGITranslate("The specified request ID does not exist."), "errorcode" => "invalid_request_id");
			if (!$this->requests[$requestid]->stdinopen)  return array("success" => false, "error" => self::FCGITranslate("The specified request ID has already closed stdin."), "errorcode" => "stdin_closed");

			$y = strlen($data);
			for ($x = 0; $x + 65535 < $y; $x += 65535)
			{
				$this->WriteRecord(self::RECORD_TYPE_STDIN, $requestid, substr($data, $x, 65535));
			}

			if ($x < $y || !$y)  $this->WriteRecord(self::RECORD_TYPE_STDIN, $requestid, (string)substr($data, $x));

			if (!$y)  $this->requests[$requestid]->stdinopen = false;

			return array("success" => true);
		}

		// Server only.
		public function SendStdout($requestid, $data)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The SendStdout() function is only available in server mode."), "errorcode" => "server_mode_only");
			if (!isset($this->requests[$requestid]))  return array("success" => false, "error" => self::FCGITranslate("The specified request ID does not exist."), "errorcode" => "invalid_request_id");

			$y = strlen($data);
			for ($x = 0; $x + 65535 < $y; $x += 65535)
			{
				$this->WriteRecord(self::RECORD_TYPE_STDOUT, $requestid, substr($data, $x, 65535));
			}

			if ($x < $y || !$y)  $this->WriteRecord(self::RECORD_TYPE_STDOUT, $requestid, (string)substr($data, $x));

			return array("success" => true);
		}

		// Server only.
		public function SendStderr($requestid, $data)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The SendStderr() function is only available in server mode."), "errorcode" => "server_mode_only");
			if (!isset($this->requests[$requestid]))  return array("success" => false, "error" => self::FCGITranslate("The specified request ID does not exist."), "errorcode" => "invalid_request_id");

			$y = strlen($data);
			for ($x = 0; $x + 65535 < $y; $x += 65535)
			{
				$this->WriteRecord(self::RECORD_TYPE_STDERR, $requestid, substr($data, $x, 65535));
			}

			if ($x < $y || !$y)  $this->WriteRecord(self::RECORD_TYPE_STDERR, $requestid, (string)substr($data, $x));

			return array("success" => true);
		}

		// Client only.
		public function IsDataOpen($requestid)
		{
			if ($this->fp === false)  return false;
			if (!$this->client)  return false;
			if (!isset($this->requests[$requestid]))  return false;

			return $this->requests[$requestid]->dataopen;
		}

		// Client only.
		public function SendData($requestid, $data)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");
			if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The SendStdin() function is only available in client mode."), "errorcode" => "client_mode_only");
			if (!isset($this->requests[$requestid]))  return array("success" => false, "error" => self::FCGITranslate("The specified request ID does not exist."), "errorcode" => "invalid_request_id");
			if (!$this->requests[$requestid]->dataopen)  return array("success" => false, "error" => self::FCGITranslate("The specified request ID has already closed the data channel."), "errorcode" => "data_closed");

			$y = strlen($data);
			for ($x = 0; $x + 65535 < $y; $x += 65535)
			{
				$this->WriteRecord(self::RECORD_TYPE_DATA, $requestid, substr($data, $x, 65535));
			}

			if ($x < $y || !$y)  $this->WriteRecord(self::RECORD_TYPE_DATA, $requestid, (string)substr($data, $x));

			if (!$y)  $this->requests[$requestid]->dataopen = false;

			return array("success" => true);
		}

		// Gets the next ready request.  Returns immediately unless $wait is not false.
		public function NextReadyRequest($wait = false)
		{
			if ($wait)
			{
				while (!count($this->readyrequests))
				{
					$result = $this->Wait();
					if (!$result["success"])  return $result;
				}
			}

			foreach ($this->readyrequests as $requestid)
			{
				unset($this->readyrequests[$requestid]);

				return array("success" => true, "id" => $requestid, "request" => $this->requests[$requestid]);
			}

			return array("success" => true, "id" => false, "request" => false);
		}

		// Forcibly removes the request from the queue.  Should only be called after the application has finished with the request.
		public function RemoveRequest($requestid)
		{
			unset($this->requests[$requestid]);
			unset($this->readyrequests[$requestid]);
		}

		public function NeedsWrite()
		{
			$this->FillWriteData();

			return ($this->writedata !== "");
		}

		// Dangerous but allows for stream_select() calls on multiple, separate stream handles.
		public function GetStream()
		{
			return $this->fp;
		}

		// Waits until one or more events time out, handles reading and writing, processes the queues (handle certain types automatically), and returns the latest status.
		public function Wait($timeout = false)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");

			$result = $this->ProcessReadData();
			if (!$result["success"])  return $result;

			$this->FillWriteData();

			if ($this->emptydisconnect && !count($this->requests) && $this->writedata === "")
			{
				$this->Disconnect();

				return array("success" => false, "error" => self::FCGITranslate("Connection closed."), "errorcode" => "no_connection");
			}

			$readfp = array($this->fp);
			$writefp = ($this->writedata !== "" ? array($this->fp) : NULL);
			$exceptfp = NULL;
			if (count($this->readyrequests))  $timeout = 0;
			else if ($timeout === false)  $timeout = NULL;

			if ($this->debug)  $result = stream_select($readfp, $writefp, $exceptfp, $timeout);
			else  $result = @stream_select($readfp, $writefp, $exceptfp, $timeout);

			if ($result === false)  return array("success" => false, "error" => self::FCGITranslate("Wait() failed due to stream_select() failure.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed");

			// Process queues and timeouts.
			$result = $this->ProcessQueues(($result > 0 && count($readfp)), ($result > 0 && $writefp !== NULL && count($writefp)));

			return $result;
		}

		// A mostly internal function.  Useful for managing multiple simultaneous connections.
		public function ProcessQueues($read, $write, $readsize = 65536)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::FCGITranslate("Connection not established."), "errorcode" => "no_connection");

			if ($read)
			{
				if ($this->debug)  $result = fread($this->fp, $readsize);
				else  $result = @fread($this->fp, $readsize);

				if ($result === false || ($result === "" && feof($this->fp)))  return array("success" => false, "error" => self::FCGITranslate("ProcessQueues() failed due to fread() failure.  Most likely cause:  Connection failure."), "errorcode" => "fread_failed");

				if ($result !== "")
				{
					$this->rawrecvsize += strlen($result);
					$this->readdata .= $result;

					$result = $this->ProcessReadData();
					if (!$result["success"])  return $result;
				}
			}

			if ($write)
			{
				if ($this->debug)  $result = fwrite($this->fp, $this->writedata);
				else  $result = @fwrite($this->fp, $this->writedata);

				if ($result === false || ($this->writedata === "" && feof($this->fp)))  return array("success" => false, "error" => self::FCGITranslate("ProcessQueues() failed due to fwrite() failure.  Most likely cause:  Connection failure."), "errorcode" => "fwrite_failed");

				if ($result)
				{
					$this->rawsendsize += $result;
					$this->rawsendqueuesize -= $result;
					$this->writedata = (string)substr($this->writedata, $result);
				}
			}

			return array("success" => true);
		}

		protected function ProcessReadData()
		{
			while (($record = $this->ReadRecord()) !== false)
			{
//var_dump($record);
				if ($record["ver"] != 1)  return array("success" => false, "error" => self::FCGITranslate("Unsupported FastCGI version (%u).", $record["ver"]), "errorcode" => "unsupported_fastcgi_version");

				switch ($record["type"])
				{
					case self::RECORD_TYPE_BEGIN_REQUEST:
					{
						if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The server attempted to begin a request."), "errorcode" => "server_mode_only");
						if ($record["reqid"] == 0)  return array("success" => false, "error" => self::FCGITranslate("Client sent an invalid request ID for a new request."), "errorcode" => "invalid_request_id");
						if (strlen($record["content"]) < 3)  return array("success" => false, "error" => self::FCGITranslate("Client sent invalid record content."), "errorcode" => "invalid_record_content");

						// Ignore requests to start the same ID.
						if ($this->requests[$record["reqid"]])  continue;

						// Deny multiplexing if it isn't being supported.
						if (count($this->requests) && !$this->multiplex)
						{
							$this->EndRequest($record["reqid"], 0, self::PROTOCOL_STATUS_CANT_MPX_CONN);

							continue;
						}

						$role = unpack("n", substr($record["content"], 0, 2))[1];
						$flags = ord($record["content"]{2});
						if ($flags & 0x01 == 0)  $this->emptydisconnect = true;

						// Deny request if there isn't room for the request.
						if (count($this->requests) >= $this->reqlimit)
						{
							$this->EndRequest($record["reqid"], 0, self::PROTOCOL_STATUS_OVERLOADED);

							continue;
						}

						// Deny the request if the requested role is impossible.
						if ($role < 1 || $role > 3)
						{
							$this->EndRequest($record["reqid"], 0, self::PROTOCOL_STATUS_UNKNOWN_ROLE);

							continue;
						}

						$request = new stdClass();
						$request->id = $record["reqid"];
						$request->role = $role;
						$request->flags = $flags;
						$request->abort = false;
						$request->params = "";
						$request->stdin = "";
						$request->stdincompleted = false;
						$request->data = "";
						$request->datacompleted = ($role !== self::ROLE_FILTER);

						$this->requests[$request->id] = $request;
						$this->readyrequests[$request->id] = true;

						break;
					}
					case self::RECORD_TYPE_ABORT_REQUEST:
					{
						if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The server attempted to abort a request."), "errorcode" => "server_mode_only");
						if ($record["reqid"] == 0)  return array("success" => false, "error" => self::FCGITranslate("Client sent an invalid request ID to abort."), "errorcode" => "invalid_request_id");

						if (!isset($this->requests[$record["reqid"]]))  continue;

						$this->requests[$record["reqid"]]->abort = true;
						$this->readyrequests[$record["reqid"]] = true;

						break;
					}
					case self::RECORD_TYPE_END_REQUEST:
					{
						if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The client attempted to end a request."), "errorcode" => "client_mode_only");
						if ($record["reqid"] == 0)  return array("success" => false, "error" => self::FCGITranslate("Server sent an invalid request ID to end."), "errorcode" => "invalid_request_id");
						if (strlen($record["content"]) < 5)  return array("success" => false, "error" => self::FCGITranslate("Server sent invalid record content."), "errorcode" => "invalid_record_content");

						if (!isset($this->requests[$record["reqid"]]))  continue;

						$request = $this->requests[$record["reqid"]];

						$request->stdinopen = false;
						$request->dataopen = false;
						$request->stdoutcompleted = true;
						$request->stderrcompleted = true;
						$request->ended = true;
						$request->appstatus = unpack("N", substr($record["content"], 0, 4))[1];
						$request->protocolstatus = ord($record["content"]{4});

						$this->readyrequests[$record["reqid"]] = true;

						break;
					}
					case self::RECORD_TYPE_PARAMS:
					{
						if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The server attempted to send params."), "errorcode" => "server_mode_only");
						if ($record["reqid"] == 0)  return array("success" => false, "error" => self::FCGITranslate("Client sent an invalid request ID for adding to 'params'."), "errorcode" => "invalid_request_id");

						if (!isset($this->requests[$record["reqid"]]))  continue;

						$request = $this->requests[$record["reqid"]];
						if (!is_string($request->params))  continue;

						if ($record["content"] === "")  $request->params = self::ParseNameValues($request->params);
						else  $request->params .= $record["content"];

						$this->readyrequests[$record["reqid"]] = true;

						break;
					}
					case self::RECORD_TYPE_STDIN:
					{
						if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The server attempted to send stdin."), "errorcode" => "server_mode_only");
						if ($record["reqid"] == 0)  return array("success" => false, "error" => self::FCGITranslate("Client sent an invalid request ID for adding to 'stdin'."), "errorcode" => "invalid_request_id");

						if (!isset($this->requests[$record["reqid"]]))  continue;

						$request = $this->requests[$record["reqid"]];
						if ($request->stdincompleted)  continue;

						if ($record["content"] === "")  $request->stdincompleted = true;
						else  $request->stdin .= $record["content"];

						$this->readyrequests[$record["reqid"]] = true;

						break;
					}
					case self::RECORD_TYPE_STDOUT:
					{
						if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The client attempted to send stdout."), "errorcode" => "client_mode_only");
						if ($record["reqid"] == 0)  return array("success" => false, "error" => self::FCGITranslate("Server sent an invalid request ID for adding to 'stdout'."), "errorcode" => "invalid_request_id");

						if (!isset($this->requests[$record["reqid"]]))  continue;

						$request = $this->requests[$record["reqid"]];
						if ($request->stdoutcompleted || $request->ended !== false)  continue;

						if ($record["content"] === "")  $request->stdoutcompleted = true;
						else  $request->stdout .= $record["content"];

						$this->readyrequests[$record["reqid"]] = true;

						break;
					}
					case self::RECORD_TYPE_STDERR:
					{
						if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The client attempted to send stderr."), "errorcode" => "client_mode_only");
						if ($record["reqid"] == 0)  return array("success" => false, "error" => self::FCGITranslate("Server sent an invalid request ID for adding to 'stderr'."), "errorcode" => "invalid_request_id");

						if (!isset($this->requests[$record["reqid"]]))  continue;

						$request = $this->requests[$record["reqid"]];
						if ($request->stderrcompleted || $request->ended !== false)  continue;

						if ($record["content"] === "")  $request->stderrcompleted = true;
						else  $request->stderr .= $record["content"];

						$this->readyrequests[$record["reqid"]] = true;

						break;
					}
					case self::RECORD_TYPE_DATA:
					{
						if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The server attempted to send filter data."), "errorcode" => "server_mode_only");
						if ($record["reqid"] == 0)  return array("success" => false, "error" => self::FCGITranslate("Client sent an invalid request ID for adding to 'data'."), "errorcode" => "invalid_request_id");

						if (!isset($this->requests[$record["reqid"]]))  continue;

						$request = $this->requests[$record["reqid"]];
						if ($request->datacompleted)  continue;

						if ($record["content"] === "")  $request->datacompleted = true;
						else  $request->data .= $record["content"];

						$this->readyrequests[$record["reqid"]] = true;

						break;
					}
					case self::RECORD_TYPE_GET_VALUES:
					{
						if ($this->client)  return array("success" => false, "error" => self::FCGITranslate("The server attempted to get values."), "errorcode" => "server_mode_only");

						$namevals = self::ParseNameValues($record["content"]);
						$namevals2 = array();
						foreach ($namevals as $name => $val)
						{
							if ($name === "FCGI_MAX_CONNS")  $namevals2["FCGI_MAX_CONNS"] = (string)$this->connlimit;
							else if ($name === "FCGI_MAX_REQS")  $namevals2["FCGI_MAX_REQS"] = (string)$this->reqlimit;
							else if ($name === "FCGI_MPXS_CONNS")  $namevals2["FCGI_MPXS_CONNS"] = (string)(int)$this->multiplex;
						}

						$contents = self::CreateNameValueChunks($namevals2);
						foreach ($contents as $content)
						{
							$this->WriteRecord(self::RECORD_TYPE_GET_VALUES_RESULT, 0, $content);
						}

						break;
					}
					case self::RECORD_TYPE_GET_VALUES_RESULT:
					{
						if (!$this->client)  return array("success" => false, "error" => self::FCGITranslate("The client attempted to set values."), "errorcode" => "client_mode_only");

						$namevals = self::ParseNameValues($record["content"]);
						foreach ($namevals as $name => $val)
						{
							if ($name === "FCGI_MAX_CONNS")  $this->connlimit = (int)$val;
							else if ($name === "FCGI_MAX_REQS")  $this->reqlimit = (int)$val;
							else if ($name === "FCGI_MPXS_CONNS")  $this->multiplex = (bool)(int)$val;
						}

						break;
					}
					case self::RECORD_TYPE_UNKNOWN_TYPE:
					{
						return array("success" => false, "error" => self::FCGITranslate("Unknown record type (%u).", (strlen($record["content"]) ? ord($record["content"][0]) : 0)), "errorcode" => "unknown_record_type");
					}
					default:
					{
						return array("success" => false, "error" => self::FCGITranslate("Unknown or unsupported record type (%u).", ord($record["type"])), "errorcode" => "unknown_record_type");
					}
				}
			}

			return array("success" => true);
		}

		// Parses the current input data to see if there is enough information to extract a single record.
		// Does not do any validation beyond loading the record.
		protected function ReadRecord()
		{
			// Each FastCGI v1 (the only version) record has an 8-byte intro.
			if (strlen($this->readdata) < 8)  return false;

			// Version, 1 byte type, 2 bytes request ID (big endian), 2 bytes content length, 1 byte padding length, 1 byte reserved (unused)
			$ver = ord($this->readdata{0});
			$type = ord($this->readdata{1});
			$reqid = unpack("n", substr($this->readdata, 2, 2))[1];
			$contentlen = unpack("n", substr($this->readdata, 4, 2))[1];
			$paddinglen = ord($this->readdata{6});

			if (strlen($this->readdata) < 8 + $contentlen + $paddinglen)  return false;

			$content = substr($this->readdata, 8, $contentlen);

			$this->readdata = substr($this->readdata, 8 + $contentlen + $paddinglen);

			$result = array(
				"ver" => $ver,
				"type" => $type,
				"reqid" => $reqid,
				"content" => $content
			);

			$this->recvrecords++;

			return $result;
		}

		// Parses a complete name-value stream into an array of key-value pairs.
		protected static function ParseNameValues($data)
		{
			$result = array();
			$x = 0;
			$y = strlen($data);
			while ($x < $y)
			{
				$namelen = ord($data{$x});
				if ($namelen < 128)  $x++;
				else if ($x + 4 > $y)  break;
				else
				{
					$data{$x} = chr($namelen & 0x7F);
					$namelen = unpack("N", substr($data{$x}, $x, 4))[1];
					$x += 4;
				}

				$vallen = ord($data{$x});
				if ($vallen < 128)  $x++;
				else if ($x + 4 > $y)  break;
				else
				{
					$data{$x} = chr($vallen & 0x7F);
					$vallen = unpack("N", substr($data{$x}, $x, 4))[1];
					$x += 4;
				}

				if ($x + $namelen + $vallen > $y)  break;

				$name = substr($data, $x, $namelen);
				$x += $namelen;
				$val = substr($data, $x, $vallen);
				$x += $vallen;

				$result[$name] = $val;
			}

			return $result;
		}

		// The maximum size of each content chunk is 65535 bytes.
		protected static function CreateNameValueChunks($namevalues)
		{
			$result = array();
			$data = "";
			foreach ($namevalues as $name => $val)
			{
				$data .= (strlen($name) > 127 ? pack("N", 0x80000000 | strlen($name)) : chr(strlen($name)));
				$data .= (strlen($val) > 127 ? pack("N", 0x80000000 | strlen($val)) : chr(strlen($val)));
				$data .= $name;
				$data .= $val;

				while (strlen($data) > 65535)
				{
					$result[] = substr($data, 0, 65535);
					$data = substr($data, 65535);
				}
			}

			$result[] = $data;

			return $result;
		}

		// Moves the next records in the queue onto the data stream.  This is done to keep writedata fairly small and avoid memory thrashing.
		protected function FillWriteData()
		{
			while (strlen($this->writedata) < 65536 && count($this->writerecords))
			{
				$this->writedata .= array_shift($this->writerecords);
				$this->sendrecords++;
			}
		}

		protected function WriteRecord($type, $requestid, $content)
		{
			// Record format:
			// Version (1), 1 byte type, 2 bytes request ID (big endian), 2 bytes content length, 1 byte padding length, 1 byte reserved (unused)
			// Followed by the content and padding.  The FastCGI specification recommends padding to 8-byte boundaries for performance reasons.
			$paddinglen = (strlen($content) % 8 == 0 ? 0 : 8 - (strlen($content) % 8));
			$data = "\x01" . chr($type) . pack("n", $requestid) . pack("n", strlen($content)) . chr($paddinglen) . "\x00" . $content;
			if ($paddinglen)  $data .= str_repeat("\x00", $paddinglen);

			$this->writerecords[] = $data;
			$this->rawsendqueuesize += strlen($data);
		}

		public static function FCGITranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>