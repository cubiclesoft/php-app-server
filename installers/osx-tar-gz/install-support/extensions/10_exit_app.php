<?php
	// Terminate the web server in a timely fashion after the user closes the last browser tab.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class PAS_Extension_10_exit_app
	{
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

		public function ProcessRequest($method, $path, $client, &$data)
		{
			$this->delay = (isset($data["delay"]) && $data["delay"] > 0 ? (int)$data["delay"] : false);

			// WebSocket expected.
			if ($method === false)  return array("success" => true);

			// Prevent browsers and proxies from doing bad things.
			$client->SetResponseNoCache();

			$client->AddResponseContent("OK");
			$client->FinalizeResponse();

			return true;
		}

		public function ServerDone()
		{
		}
	}
?>