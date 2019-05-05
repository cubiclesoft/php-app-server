<?php
	// Long running process SDK.  Requires an API-compatible backend.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class RunProcessSDK
	{
		protected $web, $fp, $url, $authuser, $authtoken;

		public function __construct()
		{
			if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";

			$this->web = new WebBrowser();
			$this->fp = false;
			$this->url = false;
			$this->authuser = false;
			$this->authtoken = false;
		}

		public function SetAccessInfo($url, $authuser, $authtoken)
		{
			$this->web = new WebBrowser();
			$this->fp = false;
			$this->url = $url;
			$this->authuser = $authuser;
			$this->authtoken = $authtoken;
		}

		public function Test()
		{
			return $this->RunAPI("GET", "?action=test");
		}

		public function GetChannelList($tag = false)
		{
			return $this->RunAPI("GET", "?action=list" . ($tag !== false ? "&tag=" . urlencode($tag) : ""));
		}

		public function ClearChannel($channel)
		{
			$options = array(
				"action" => "clear",
				"channel" => (int)$channel
			);

			return $this->RunAPI("POST", "", $options);
		}

		public function StartProcess($tag, $cmd, $options)
		{
			$options["action"] = "start";
			$options["cmd"] = (string)$cmd;
			$options["tag"] = (string)$tag;

			return $this->RunAPI("POST", "", $options);
		}

		public function SendStdin($channel, $data, $historylines = false)
		{
			$options = array(
				"action" => "send_stdin",
				"channel" => (int)$channel,
				"data" => base64_encode($data)
			);

			if ($historylines !== false)  $options["history"] = (int)$historylines;

			return $this->RunAPI("POST", "", $options);
		}

		public function CloseStdin($channel)
		{
			$options = array(
				"action" => "close_stdin",
				"channel" => (int)$channel
			);

			return $this->RunAPI("POST", "", $options);
		}

		public function SendStdcmd($channel, $data)
		{
			$options = array(
				"action" => "send_stdcmd",
				"channel" => (int)$channel,
				"data" => base64_encode($data)
			);

			return $this->RunAPI("POST", "", $options);
		}

		public function CloseStdcmd($channel)
		{
			$options = array(
				"action" => "close_stdcmd",
				"channel" => (int)$channel
			);

			return $this->RunAPI("POST", "", $options);
		}

		public function TerminateProcess($channel)
		{
			$options = array(
				"action" => "terminate",
				"channel" => (int)$channel
			);

			return $this->RunAPI("POST", "", $options);
		}

		public function RemoveChannel($channel)
		{
			$options = array(
				"action" => "remove",
				"channel" => (int)$channel
			);

			return $this->RunAPI("POST", "", $options);
		}

		protected function RunAPI($method, $apipath, $options = array(), $expected = 200, $encodejson = true, $decodebody = true)
		{
			if ($this->url === false)  return array("success" => false, "error" => self::RP_Translate("Run Process URL not set.  Call SetAccessInfo()."), "errorcode" => "missing_url");
			if ($this->authuser === false)  return array("success" => false, "error" => self::RP_Translate("Run Process authorization user not set.  Call SetAccessInfo()."), "errorcode" => "missing_authuser");
			if ($this->authtoken === false)  return array("success" => false, "error" => self::RP_Translate("Run Process authentication token not set.  Call SetAccessInfo()."), "errorcode" => "missing_authtoken");

			$options2 = array(
				"method" => $method,
				"headers" => array(
					"Connection" => "keep-alive",
					"Authorization" => "Basic " . base64_encode(rawurlencode($this->authuser) . ":" . rawurlencode($this->authtoken))
				)
			);

			if ($this->fp !== false)  $options2["fp"] = $this->fp;

			if ($encodejson && $method !== "GET")
			{
				$options2["headers"]["Content-Type"] = "application/json";
				$options2["body"] = json_encode($options, JSON_UNESCAPED_SLASHES);
			}
			else
			{
				$options2 = array_merge($options2, $options);
			}

			$result = $this->web->Process($this->url . $apipath, $options2);

			if (!$result["success"] && $this->fp !== false)
			{
				// If the server terminated the connection, then re-establish the connection and rerun the request.
				@fclose($this->fp);
				$this->fp = false;

				return $this->RunAPI($method, $apipath, $options, $expected, $encodejson, $decodebody);
			}

			if (!$result["success"])  return $result;

			if (isset($result["fp"]) && is_resource($result["fp"]))  $this->fp = $result["fp"];
			else  $this->fp = false;

			if ($result["response"]["code"] != $expected)  return array("success" => false, "error" => self::RP_Translate("Expected a %d response from the web server.  Received '%s'.", $expected, $result["response"]["line"]), "errorcode" => "unexpected_response", "info" => $result);

			if ($decodebody)
			{
				$data = @json_decode($result["body"], true);
				if (is_array($data) && isset($data["success"]))  return $data;
			}

			return $result;
		}

		public static function OutputCSS()
		{
			$rootpath = str_replace("\\", "/", dirname(__FILE__));
			$urlpath = substr($rootpath, strlen($_SERVER["DOCUMENT_ROOT"]));

?>
<link rel="stylesheet" href="<?=$urlpath?>/xterm.css">
<link rel="stylesheet" href="<?=$urlpath?>/run_process.css">
<?php
		}

		public static function OutputJS()
		{
			$rootpath = str_replace("\\", "/", dirname(__FILE__));
			$urlpath = substr($rootpath, strlen($_SERVER["DOCUMENT_ROOT"]));

?>
<script type="text/javascript" src="<?=$urlpath?>/xterm.js"></script>
<script type="text/javascript" src="<?=$urlpath?>/addons/fit.js"></script>
<script type="text/javascript" src="<?=$urlpath?>/run_process.js"></script>
<?php
		}

		// FlexForms integration.
		public static function FF_Init(&$state, &$options)
		{
			if (!isset($state["modules_run_process_sdk"]))  $state["modules_run_process_sdk"] = false;
		}

		public static function FF_FieldType(&$state, $num, &$field, $id)
		{
			if ($field["type"] === "run_process" && isset($field["url"]) && is_string($field["url"]) && isset($field["authuser"]) && isset($field["authtoken"]) && is_string($field["authtoken"]))
			{
				$id .= "_run_process";

?>
<div class="formitemdata">
	<div class="runprocessitemwrap"<?php if (isset($field["width"]))  echo " style=\"" . ($state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . "\""; ?>>
		<div id="<?php echo htmlspecialchars($id); ?>"></div>
	</div>
</div>
<?php
				if ($state["modules_run_process_sdk"] === false)
				{
					$rootpath = str_replace("\\", "/", dirname(__FILE__));
					$urlpath = substr($rootpath, strlen($_SERVER["DOCUMENT_ROOT"]));

					$state["css"]["modules-run-process-xterm"] = array("mode" => "link", "dependency" => false, "src" => $urlpath . "/xterm.css");
					$state["css"]["modules-run-process-sdk"] = array("mode" => "link", "dependency" => "modules-run-process-xterm", "src" => $urlpath . "/run_process.css");
					$state["js"]["modules-run-process-xterm"] = array("mode" => "src", "dependency" => false, "src" => $urlpath . "/xterm.js", "detect" => "window.Terminal");
					$state["js"]["modules-run-process-xterm-fit"] = array("mode" => "src", "dependency" => "modules-run-process-xterm", "src" => $urlpath . "/addons/fit.js", "detect" => "window.fit");
					$state["js"]["modules-run-process-sdk"] = array("mode" => "src", "dependency" => "modules-run-process-xterm-fit", "src" => $urlpath . "/run_process.js", "detect" => "window.RunProcessSDK");

					$state["modules_run_process_sdk"] = true;
				}

				$options = array(
					"__flexforms" => true
				);

				// Allow the terminal manager instance to be fully customized beyond basic support.
				// Uses dot notation for array key references.
				if (isset($field["options"]))
				{
					foreach ($field["options"] as $key => $val)
					{
						$parts = explode(".", $key);

						FlexForms::SetNestedPathValue($options, $parts, $val);
					}
				}

				// Queue up the necessary Javascript for later output.
				ob_start();
?>
(function() {
	var runproc = new RunProcessSDK('<?=FlexForms::JSSafe($field["url"])?>', <?php echo json_encode($field["authuser"], JSON_UNESCAPED_SLASHES); ?>, '<?=FlexForms::JSSafe($field["authtoken"])?>');
<?php
				if (isset($field["debug"]) && $field["debug"])
				{
?>
	runproc.debug = true;
<?php
				}
?>
	var options = <?php echo json_encode($options, JSON_UNESCAPED_SLASHES); ?>;
<?php
				if (isset($field["callbacks"]))
				{
					foreach ($field["callbacks"] as $key => $val)
					{
						$parts = explode(".", $key);

?>
	options<?php foreach ($parts as $part)  echo "['" . $part . "']"; ?> = <?php echo $val; ?>;
<?php
					}
				}
?>

	var elem = document.getElementById('<?php echo FlexForms::JSSafe($id); ?>');

	var tm = new TerminalManager(runproc, elem, options);
})();
<?php
				$state["js"]["modules-run-process-sdk|" . $id] = array("mode" => "inline", "dependency" => "modules-run-process-sdk", "src" => ob_get_contents());
				ob_end_clean();
			}
		}

		protected static function RP_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}

	// Register form handlers.
	if (is_callable("FlexForms::RegisterFormHandler"))
	{
		FlexForms::RegisterFormHandler("init", "RunProcessSDK::FF_Init");
		FlexForms::RegisterFormHandler("field_type", "RunProcessSDK::FF_FieldType");
	}
?>