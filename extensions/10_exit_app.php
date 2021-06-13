<?php
	// Terminate the web server in a timely fashion after the user closes the last browser tab.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class PAS_Extension_10_exit_app
	{
		private $delay, $lastclient;

		public function InitServer()
		{
			$this->delay = false;
			$this->lastclient = 0;
		}

		public function ServerReady()
		{
		}

		public function UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
		{
			global $wsserver, $cgis, $fcgis, $running;

			if ($this->delay !== false)
			{
				if ($wsserver->NumClients() || count($cgis) || count($fcgis))  $this->lastclient = microtime(true);
				else if ($this->lastclient < microtime(true) - $this->delay)
				{
					$timeout = 0;
					$running = false;
				}
				else
				{
					$timeout = (int)($this->delay - (microtime(true) - $this->lastclient)) + 1;
				}
			}
		}

		public function CanHandleRequest($method, $url, $path, $client)
		{
			if ($path === "/exit-app/")  return true;

			return false;
		}

		public function RequireAuthToken()
		{
			return true;
		}

		public function ProcessRequest($method, $path, $client, &$data)
		{
			if (!is_array($data))  return false;

			$this->delay = (isset($data["delay"]) && $data["delay"] > 0 ? (int)$data["delay"] : false);

			$result = array("success" => true);

			// WebSocket expected.
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
		}
	}
?>