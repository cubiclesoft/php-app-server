<?php
	// Uses a one-time use security token to handle the rare case of crossing local user account boundaries.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	// The '1' prefix helps this extension to run before other extensions.
	class PAS_Extension_1_security_token
	{
		private $inittoken, $realtoken;

		public function InitServer()
		{
			$this->inittoken = false;
			$this->realtoken = false;
		}

		public function ServerReady()
		{
			global $args, $initresult;

			// Only activate this extension when using the startup JSON file option (i.e. production mode).
			if (isset($args["opts"]["sfile"]))
			{
				$rng = new CSPRNG();
				$this->inittoken = $rng->GenerateToken();
				$this->realtoken = $rng->GenerateToken();

				$initresult["url"] .= "?pas_sec_token=" . urlencode($this->inittoken);
			}
		}

		public function UpdateStreamsAndTimeout($prefix, &$timeout, &$readfps, &$writefps)
		{
		}

		public function CanHandleRequest($method, $url, $path, $client)
		{
			global $docroot;

			// Make one minor concession to allow retrieval of a favicon as it tends to happen out-of-band at the same time as the first request to the host.
			// There's no way for the web browser to know that the first request unlocks the host.
			if ($path === "/favicon.ico" && is_file($docroot . "/favicon.ico"))  return false;

			// Route all initial token requests and requests without a valid real token (via cookies) to the request handler.
			if ($this->inittoken !== false || ($this->realtoken !== false && (!isset($client->cookievars["pas_rst"]) || Str::CTstrcmp($this->realtoken, $client->cookievars["pas_rst"]) != 0)))
			{
				// Attempt to hide WebSocket upgrade requests from the server.
				unset($client->headers["Upgrade"]);

				return true;
			}

			return false;
		}

		// Nearly all extensions should return true.  Since this is a security extension, it provides its own authentication mechanism.
		public function RequireAuthToken()
		{
			return false;
		}

		public function ProcessRequest($method, $path, $client, &$data)
		{
			// Remove WebSocket connections.
			if ($method === false || !is_array($data))  return false;

			if ($this->inittoken !== false)
			{
				if (isset($data["pas_sec_token"]) && is_string($data["pas_sec_token"]) && Str::CTstrcmp($this->inittoken, $data["pas_sec_token"]) == 0)
				{
					// This is the first request with a valid token.
					$this->origtoken = $this->inittoken;
					$this->inittoken = false;

					$client->appdata["respcode"] = 301;
					$client->appdata["respmsg"] = "Moved Permanently";

					$client->SetResponseCode($client->appdata["respcode"]);

					// Prevent browsers and proxies from doing bad things.
					$client->SetResponseNoCache();

					$client->SetResponseCookie("pas_rst", $this->realtoken, 0, "", "", false, true);

					// Redirect the browser.
					unset($client->appdata["url"]["query"]);
					unset($client->appdata["url"]["queryvars"]["pas_sec_token"]);
					$client->AddResponseHeader("Location", HTTP::CondenseURL($client->appdata["url"]), true);

					$client->FinalizeResponse();
				}
				else
				{
					// Emit a generic 403 Forbidden error.
					$client->appdata["respcode"] = 403;
					$client->appdata["respmsg"] = "Forbidden<br><br>See log file for details.";

					if (!isset($data["pas_sec_token"]))  WriteErrorLog("403 Forbidden - Missing token", $client->ipaddr, $client->request, array("success" => false, "error" => "A PAS security token was not used.", "errorcode" => "missing_pas_sec_token", "server_ext" => "security_token"));
					else  WriteErrorLog("403 Forbidden - Invalid token", $client->ipaddr, $client->request, array("success" => false, "error" => "An invalid PAS security token was used.", "errorcode" => "invalid_pas_sec_token", "server_ext" => "security_token"));

					SendHTTPErrorResponse($client);
				}
			}
			else if (isset($data["pas_sec_token"]) && is_string($data["pas_sec_token"]) && Str::CTstrcmp($this->origtoken, $data["pas_sec_token"]) == 0)
			{
				// The user has a valid token BUT someone else beat them to using it.
				$client->appdata["respcode"] = 403;
				$client->appdata["respmsg"] = "Forbidden - Token Reused<br><br>See error log for details.";

				WriteErrorLog("403 Forbidden - Token reused", $client->ipaddr, $client->request, array("success" => false, "error" => "A valid PAS security token was reused.  A PAS security token may only be used precisely one time per server instance.  Seeing this message in this log file may be an indicator of a serious security problem.", "errorcode" => "pas_sec_token_reused", "server_ext" => "security_token"));

				$client->SetResponseCode($client->appdata["respcode"]);

				// Prevent browsers and proxies from doing bad things.
				$client->SetResponseNoCache();

				ob_start();
?>
<!DOCTYPE html>
<html>
<head><title>403 Forbidden</title></head>
<body>
<h2>403 Forbidden - PAS Security Token Reused</h2>

<p>Your PAS security token is valid (i.e. the 'pas_sec_token' part of the URL).  However, a PAS security token may only be used precisely one time.</p>

<p><span style="color: #A94442; font-size: 1.1em;">This message can appear if another user is on your system and stole your PAS security token before your web browser got a chance to use it.</span></p>

<p><span style="color: #A94442; font-size: 1.1em; font-weight: bold;">It is highly recommended that you reboot your computer immediately to prevent any significant damage to your user account on this system.</span></p>

<p>This message can also appear when attempting to reuse a PAS security token across multiple web browsers.  To use another web browser with this application, change your default web browser and start the application again.  This is a much rarer reason than the one above.</p>

</body>
</html>
<?php
				$content = ob_get_contents();
				ob_end_clean();

				$client->AddResponseContent($content);
				$client->FinalizeResponse();
			}
			else
			{
				// The user has probably just attempted to switch browsers with a plain URL (or an attacker).
				$client->appdata["respcode"] = 403;
				$client->appdata["respmsg"] = "Forbidden<br><br>See error log for details.";

				WriteErrorLog("403 Forbidden - Missing cookie", $client->ipaddr, $client->request, array("success" => false, "error" => "The expected PAS security cookie is missing.  A PAS security cookie is required for all requests.", "errorcode" => "pas_rst_missing", "server_ext" => "security_token"));

				$client->SetResponseCode($client->appdata["respcode"]);

				// Prevent browsers and proxies from doing bad things.
				$client->SetResponseNoCache();

				ob_start();
?>
<html>
<head><title>403 Forbidden</title></head>
<body>
<h2>403 Forbidden - Security Cookie Missing</h2>

<p>An expected web browser cookie is missing or is invalid.</p>

<p>This message can appear for a number of reasons:</p>

<ul>
<li>Switching web browsers.</li>
<li>Switching to Incognito/Private Web Browsing mode.</li>
<li>Disabling web browser cookies.</li>
<li>Blocking cookies using a third-party browser plugin.</li>
<li>Loading the wrong cookies from another application instance due to malware or a bad third-party browser plugin.</li>
</ul>

</body>
</html>
<?php
				$content = ob_get_contents();
				ob_end_clean();

				$client->AddResponseContent($content);
				$client->FinalizeResponse();
			}

			return true;
		}

		public function ServerDone()
		{
		}
	}
?>