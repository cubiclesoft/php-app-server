<?php
	// FlexForms is the flexible, security-centric class for generating web forms.  Extracted from Barebones CMS with concepts from Admin Pack and SSO server and represents the culminaton of 7 years of precision development.
	// (C) 2017 CubicleSoft.  All Rights Reserved.

	class FlexForms
	{
		protected $state, $secretkey, $extrainfo, $autononce, $version;
		protected static $requesthostcache, $formhandlers;

		public function __construct()
		{
			$this->state = array(
				"formnum" => 0,
				"formidbase" => "ff_form_",
				"responsive" => true,
				"formtables" => true,
				"formwidths" => true,
				"autofocused" => false,
				"jqueryuiused" => false,
				"jqueryuitheme" => "smoothness",
				"supporturl" => "support",
				"customfieldtypes" => array(),
				"ajax" => false,
				"action" => self::GetRequestURLBase(),
				"js" => array(),
				"jsoutput" => array(),
				"css" => array(),
				"cssoutput" => array()
			);

			$this->secretkey = false;
			$this->extrainfo = "";
			$this->autononce = false;
			$this->version = "";
		}

		public function GetState()
		{
			return $this->state;
		}

		public function SetState($newstate)
		{
			$this->state = array_merge($this->state, $newstate);
		}

		public function SetAjax($enable)
		{
			if ($enable)
			{
				$this->state["ajax"] = true;
				$this->state["action"] = self::GetFullRequestURLBase();
			}
			else
			{
				$this->state["ajax"] = false;
				$this->state["action"] = self::GetRequestURLBase();
			}
		}

		public function AddJS($name, $info)
		{
			$this->state["js"][$name] = $info;
		}

		public function SetJSOutput($name)
		{
			$this->state["jsoutput"][$name] = true;
		}

		public function AddCSS($name, $info)
		{
			$this->state["css"][$name] = $info;
		}

		public function SetCSSOutput($name)
		{
			$this->state["cssoutput"][$name] = true;
		}

		public function SetVersion($newversion)
		{
			$this->version = urlencode((string)$newversion);
		}

		public function SetSecretKey($secretkey)
		{
			$this->secretkey = (string)$secretkey;
		}

		public function SetTokenExtraInfo($extrainfo)
		{
			$this->extrainfo = (string)$extrainfo;
		}

		public function CreateSecurityToken($action, $extra = "")
		{
			if ($this->secretkey === false)
			{
				echo self::FFTranslate("Secret key not set for form.");
				exit();
			}

			$str = $action . ":" . $this->extrainfo;
			if (is_string($extra) && $extra !== "")
			{
				$extra = explode(",", $extra);
				foreach ($extra as $key)
				{
					$key = trim($key);
					if ($key !== "" && isset($_REQUEST[$key]))  $str .= ":" . (string)$_REQUEST[$key];
				}
			}
			else if (is_array($extra))
			{
				foreach ($extra as $val)  $str .= ":" . $val;
			}

			return hash_hmac("sha1", $str, $this->secretkey);
		}

		public static function IsSecExtraOpt($opt)
		{
			return (isset($_REQUEST["sec_extra"]) && strpos("," . $_REQUEST["sec_extra"] . ",", "," . $opt . ",") !== false);
		}

		public function CheckSecurityToken($action)
		{
			if (isset($_REQUEST[$action]) && (!isset($_REQUEST["sec_t"]) || $_REQUEST["sec_t"] != $this->CreateSecurityToken($_REQUEST[$action], (isset($_REQUEST["sec_extra"]) ? $_REQUEST["sec_extra"] : ""))))
			{
				echo self::FFTranslate("Invalid security token.  Cross-site scripting (XSRF attack) attempt averted.");
				exit();
			}
			else if (isset($_REQUEST[$action]))
			{
				$this->autononce = array("action" => $action, "value" => $_REQUEST[$action]);
			}
		}

		public function OutputFormCSS($delaycss = false)
		{
			if (!isset($this->state["cssoutput"]["formcss"]))
			{
				if ($delaycss)  $this->state["css"]["formcss"] = array("mode" => "link", "dependency" => false, "src" => $this->state["supporturl"] . "/flex_forms.css");
				else
				{
?>
	<link rel="stylesheet" href="<?php echo htmlspecialchars($this->state["supporturl"] . "/flex_forms.css" . ($this->version !== "" ? (strpos($info["src"], "?") === false ? "?" : "&") . $this->version : "")); ?>" type="text/css" media="all" />
<?php

					$this->state["cssoutput"]["formcss"] = true;
				}
			}
		}

		public function OutputMessage($type, $message)
		{
			$type = strtolower((string)$type);
			if ($type === "warn")  $type = "warning";

			$this->OutputFormCSS();

?>
	<div class="ff_formmessagewrap">
		<div class="ff_formmessagewrapinner">
			<div class="message message<?php echo htmlspecialchars($type); ?>">
				<?php echo self::FFTranslate((string)$message); ?>
			</div>
		</div>
	</div>
<?php
		}

		public function GetEncodedSignedMessage($type, $message, $prefix = "")
		{
			$message = self::FFTranslate($message);

			return urlencode($prefix . "msgtype") . "=" . urlencode($type) . "&" . urlencode($prefix . "msg") . "=" . urlencode($message) . "&" . urlencode($prefix . "msg_t") . "=" . $this->CreateSecurityToken("forms__message", array($type, $message));
		}

		public function OutputSignedMessage($prefix = "")
		{
			if (isset($_REQUEST[$prefix . "msgtype"]) && isset($_REQUEST[$prefix . "msg"]) && isset($_REQUEST[$prefix . "msg_t"]) && $_REQUEST[$prefix . "msg_t"] === $this->CreateSecurityToken("forms__message", array($_REQUEST[$prefix . "msgtype"], $_REQUEST[$prefix . "msg"])))
			{
				$this->OutputMessage($_REQUEST[$prefix . "msgtype"], htmlspecialchars($_REQUEST[$prefix . "msg"]));
			}
		}

		public function OutputJQuery($delayjs = false)
		{
			if (!isset($this->state["jsoutput"]["jquery"]))
			{
				if ($delayjs)  $this->state["js"]["jquery"] = array("mode" => "src", "dependency" => false, "src" => $this->state["supporturl"] . "/jquery-3.1.1.min.js", "detect" => "jQuery");
				else
				{
?>
	<script type="text/javascript" src="<?php echo htmlspecialchars($this->state["supporturl"] . "/jquery-3.1.1.min.js"); ?>"></script>
<?php

					$this->state["jsoutput"]["jquery"] = true;
				}
			}
		}

		public function OutputJQueryUI($delayjs = false)
		{
			$this->OutputJQuery($delayjs);

			if (!isset($this->state["jsoutput"]["jqueryui"]))
			{
				if ($delayjs)
				{
					$this->state["css"]["jqueryui"] = array("mode" => "link", "dependency" => false, "src" => $this->state["supporturl"] . "/jquery_ui_themes/" . $this->state["jqueryuitheme"] . "/jquery-ui-1.12.1.css");
					$this->state["js"]["jqueryui"] = array("mode" => "src", "dependency" => "jquery", "src" => $this->state["supporturl"] . "/jquery-ui-1.12.1.min.js", "detect" => "jQuery.ui");
				}
				else
				{
?>
	<link rel="stylesheet" href="<?php echo htmlspecialchars($this->state["supporturl"] . "/jquery_ui_themes/" . $this->state["jqueryuitheme"] . "/jquery-ui-1.12.1.css"); ?>" type="text/css" media="all" />
	<script type="text/javascript" src="<?php echo htmlspecialchars($this->state["supporturl"] . "/jquery-ui-1.12.1.min.js"); ?>"></script>
<?php

					$this->state["cssoutput"]["jqueryui"] = true;
					$this->state["jsoutput"]["jqueryui"] = true;
				}
			}
		}

		// Copy included for class self-containment.
		// Makes an input filename safe for use.
		// Allows a very limited number of characters through.
		public static function FilenameSafe($filename)
		{
			return preg_replace('/\s+/', "-", trim(trim(preg_replace('/[^A-Za-z0-9_.\-]/', " ", $filename), ".")));
		}

		public static function NormalizeFiles($key)
		{
			$result = array();
			if (isset($_FILES) && is_array($_FILES) && isset($_FILES[$key]) && is_array($_FILES[$key]))
			{
				$currfiles = $_FILES[$key];

				if (isset($currfiles["name"]) && isset($currfiles["type"]) && isset($currfiles["tmp_name"]) && isset($currfiles["error"]) && isset($currfiles["size"]))
				{
					if (is_string($currfiles["name"]))
					{
						$currfiles["name"] = array($currfiles["name"]);
						$currfiles["type"] = array($currfiles["type"]);
						$currfiles["tmp_name"] = array($currfiles["tmp_name"]);
						$currfiles["error"] = array($currfiles["error"]);
						$currfiles["size"] = array($currfiles["size"]);
					}

					$y = count($currfiles["name"]);
					for ($x = 0; $x < $y; $x++)
					{
						if ($currfiles["error"][$x] != 0)
						{
							switch ($currfiles["error"][$x])
							{
								case 1:  $msg = "The uploaded file exceeds the 'upload_max_filesize' directive in 'php.ini'.";  $code = "upload_err_ini_size";  break;
								case 2:  $msg = "The uploaded file exceeds the 'MAX_FILE_SIZE' directive that was specified in the submitted form.";  $code = "upload_err_form_size";  break;
								case 3:  $msg = "The uploaded file was only partially uploaded.";  $code = "upload_err_partial";  break;
								case 4:  $msg = "No file was uploaded.";  $code = "upload_err_no_file";  break;
								case 6:  $msg = "The configured temporary folder on the server is missing.";  $code = "upload_err_no_tmp_dir";  break;
								case 7:  $msg = "Unable to write the temporary file to disk.  The server is out of disk space, incorrectly configured, or experiencing hardware issues.";  $code = "upload_err_cant_write";  break;
								case 8:  $msg = "A PHP extension stopped the upload.";  $code = "upload_err_extension";  break;
								default:  $msg = "An unknown error occurred.";  $code = "upload_err_unknown";  break;
							}

							$entry = array(
								"success" => false,
								"error" => self::FFTranslate($msg),
								"errorcode" => $code
							);
						}
						else if (!is_uploaded_file($currfiles["tmp_name"][$x]))
						{
							$entry = array(
								"success" => false,
								"error" => self::FFTranslate("The specified input filename was not uploaded to this server."),
								"errorcode" => "invalid_input_filename"
							);
						}
						else
						{
							$currfiles["name"][$x] = self::FilenameSafe($currfiles["name"][$x]);
							$pos = strrpos($currfiles["name"][$x], ".");
							$fileext = ($pos !== false ? (string)substr($currfiles["name"][$x], $pos + 1) : "");

							$entry = array(
								"success" => true,
								"file" => $currfiles["tmp_name"][$x],
								"name" => $currfiles["name"][$x],
								"ext" => $fileext,
								"type" => $currfiles["type"][$x],
								"size" => $currfiles["size"][$x]
							);
						}

						$result[] = $entry;
					}
				}
			}

			return $result;
		}

		public static function GetValue($key, $default)
		{
			return (isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default);
		}

		public static function GetSelectValues($data)
		{
			$result = array();
			foreach ($data as $val)  $result[$val] = true;

			return $result;
		}

		public static function ProcessInfoDefaults($info, $defaults)
		{
			foreach ($defaults as $key => $val)
			{
				if (!isset($info[$key]))  $info[$key] = $val;
			}

			return $info;
		}

		public static function SetNestedPathValue(&$data, $pathparts, $val)
		{
			$curr = &$data;
			foreach ($pathparts as $key)
			{
				if (!isset($curr[$key]))  $curr[$key] = array();

				$curr = &$curr[$key];
			}

			$curr = $val;
		}

		public static function GetIDDiff($origids, $newids)
		{
			$result = array("remove" => array(), "add" => array());
			foreach ($origids as $id => $val)
			{
				if (!isset($newids[$id]))  $result["remove"][$id] = $val;
			}

			foreach ($newids as $id => $val)
			{
				if (!isset($origids[$id]))  $result["add"][$id] = $val;
			}

			return $result;
		}

		public function GetHashedFieldName($name)
		{
			if ($this->secretkey === false)
			{
				echo self::FFTranslate("Secret key not set.");
				exit();
			}

			return "f_" . hash_hmac("md5", $name, $this->secretkey);
		}

		public function GetHashedFieldValues($nameswithdefaults)
		{
			if ($this->secretkey === false)
			{
				echo self::FFTranslate("Secret key not set.");
				exit();
			}

			$result = array();
			foreach ($nameswithdefaults as $name => $default)
			{
				$name2 = "f_" . hash_hmac("md5", $name, $this->secretkey);

				$result[$name] = (isset($_REQUEST[$name2]) ? $_REQUEST[$name2] : $default);
			}

			return $result;
		}

		public function Generate($options, $errors = array(), $lastform = true)
		{
			$this->InitFormVars($options);

			$this->OutputFormCSS();

?>
	<div class="ff_formwrap">
	<div class="ff_formwrapinner">
<?php
			if (isset($options["submit"]) || (isset($options["useform"]) && $options["useform"]))
			{
				$this->state["formnum"]++;
				$this->state["formid"] = $this->state["formidbase"] . $this->state["formnum"];
?>
		<form class="ff_form" id="<?php echo $this->state["formid"]; ?>"<?php if (isset($options["formmode"]) && $options["formmode"] === "get")  { ?> method="get"<?php } else { ?> method="post" enctype="multipart/form-data"<?php } ?> action="<?php echo htmlspecialchars($this->state["action"]); ?>">
<?php

				$extra = array();
				if (!isset($options["nonce"]) && $this->autononce !== false)
				{
					$options["nonce"] = $this->autononce["action"];

					if (!isset($options["hidden"]))  $options["hidden"] = array();
					if (!isset($options["hidden"][$options["nonce"]]))  $options["hidden"][$options["nonce"]] = $this->autononce["value"];
				}
				if (isset($options["hidden"]))
				{
					foreach ($options["hidden"] as $name => $value)
					{
						$this->state["hidden"][(string)$name] = (string)$value;

?>
		<input type="hidden" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($value); ?>" />
<?php
						if (isset($options["nonce"]) && $options["nonce"] != $name)  $extra[$name] = $value;
					}

					if (isset($options["nonce"]))
					{
						$this->state["hidden"]["sec_extra"] = implode(",", array_keys($extra));
						$this->state["hidden"]["sec_t"] = $this->CreateSecurityToken($options["hidden"][$options["nonce"]], $extra);

?>
		<input type="hidden" name="sec_extra" value="<?php echo htmlspecialchars($this->state["hidden"]["sec_extra"]); ?>" />
		<input type="hidden" name="sec_t" value="<?php echo htmlspecialchars($this->state["hidden"]["sec_t"]); ?>" />
<?php
					}
				}
				unset($extra);
			}

			if (isset($options["fields"]))
			{
?>
		<div class="formfields<?php if (count($options["fields"]) == 1 && !isset($options["fields"][0]["title"]) && !isset($options["fields"][0]["htmltitle"]))  echo " alt"; ?><?php if ($this->state["responsive"])  echo " formfieldsresponsive"; ?>">
<?php
				foreach ($options["fields"] as $num => $field)
				{
					$id = "f" . $this->state["formnum"] . "_" . $num;
					if (!is_string($field) && isset($field["name"]))
					{
						if (isset($errors[$field["name"]]))  $field["error"] = $errors[$field["name"]];

						if (isset($options["hashnames"]) && $options["hashnames"])
						{
							$field["origname"] = $field["name"];
							$field["name"] = $this->GetHashedFieldName($field["name"]);
						}

						$id .= "_" . $field["name"];
					}

					$this->ProcessField($num, $field, $id);
				}

				$this->CleanupFields();
?>
		</div>
<?php
			}

			if (isset($options["submit"]))  $this->ProcessSubmit($options);

			if (isset($options["submit"]) || (isset($options["useform"]) && $options["useform"]))
			{
?>
		</form>
<?php
			}
?>
	</div>
	</div>
<?php

			if ($lastform)  $this->Finalize();
		}

		public static function FFTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}

		public static function JSSafe($data)
		{
			return str_replace(array("'", "\r", "\n"), array("\\'", "\\r", "\\n"), $data);
		}

		public static function IsSSLRequest()
		{
			return ((isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on" || $_SERVER["HTTPS"] == "1")) || (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] == "443") || (isset($_SERVER["REQUEST_URI"]) && str_replace("\\", "/", strtolower(substr($_SERVER["REQUEST_URI"], 0, 8))) == "https://"));
		}

		// Returns 'http[s]://www.something.com[:port]' based on the current page request.
		public static function GetRequestHost($protocol = "")
		{
			$protocol = strtolower($protocol);
			$ssl = ($protocol == "https" || ($protocol == "" && self::IsSSLRequest()));
			if ($protocol == "")  $type = "def";
			else if ($ssl)  $type = "https";
			else  $type = "http";

			if (!isset(self::$requesthostcache))  self::$requesthostcache = array();
			if (isset(self::$requesthostcache[$type]))  return self::$requesthostcache[$type];

			$url = "http" . ($ssl ? "s" : "") . "://";

			$str = (isset($_SERVER["REQUEST_URI"]) ? str_replace("\\", "/", $_SERVER["REQUEST_URI"]) : "/");
			$pos = strpos($str, "?");
			if ($pos !== false)  $str = substr($str, 0, $pos);
			$str2 = strtolower($str);
			if (substr($str2, 0, 7) == "http://")
			{
				$pos = strpos($str, "/", 7);
				if ($pos === false)  $str = "";
				else  $str = substr($str, 7, $pos);
			}
			else if (substr($str2, 0, 8) == "https://")
			{
				$pos = strpos($str, "/", 8);
				if ($pos === false)  $str = "";
				else  $str = substr($str, 8, $pos);
			}
			else  $str = "";

			if ($str != "")  $host = $str;
			else if (isset($_SERVER["HTTP_HOST"]))  $host = $_SERVER["HTTP_HOST"];
			else  $host = $_SERVER["SERVER_NAME"] . ":" . (int)$_SERVER["SERVER_PORT"];

			$pos = strpos($host, ":");
			if ($pos === false)  $port = 0;
			else
			{
				$port = (int)substr($host, $pos + 1);
				$host = substr($host, 0, $pos);
			}
			if ($port < 1 || $port > 65535)  $port = ($ssl ? 443 : 80);
			$url .= preg_replace('/[^a-z0-9.\-]/', "", strtolower($host));
			if ($protocol == "" && ((!$ssl && $port != 80) || ($ssl && $port != 443)))  $url .= ":" . $port;
			else if ($protocol == "http" && !$ssl && $port != 80)  $url .= ":" . $port;
			else if ($protocol == "https" && $ssl && $port != 443)  $url .= ":" . $port;

			self::$requesthostcache[$type] = $url;

			return $url;
		}

		public static function GetRequestURLBase()
		{
			$str = (isset($_SERVER["REQUEST_URI"]) ? str_replace("\\", "/", $_SERVER["REQUEST_URI"]) : "/");
			$pos = strpos($str, "?");
			if ($pos !== false)  $str = substr($str, 0, $pos);
			if (strncasecmp($str, "http://", 7) == 0 || strncasecmp($str, "https://", 8) == 0)
			{
				$pos = strpos($str, "/", 8);
				if ($pos === false)  $str = "/";
				else  $str = substr($str, $pos);
			}

			return $str;
		}

		public static function GetFullRequestURLBase($protocol = "")
		{
			return self::GetRequestHost($protocol) . self::GetRequestURLBase();
		}

		public static function RegisterFormHandler($mode, $callback)
		{
			if (!isset(self::$formhandlers) || !is_array(self::$formhandlers))  self::$formhandlers = array("init" => array(), "field_string" => array(), "field_type" => array(), "table_row" => array(), "cleanup" => array(), "finalize" => array());

			if (isset(self::$formhandlers[$mode]))  self::$formhandlers[$mode][] = $callback;
		}

		protected function InitFormVars(&$options)
		{
			if (!isset(self::$formhandlers) || !is_array(self::$formhandlers))  self::$formhandlers = array("init" => array(), "field_string" => array(), "field_type" => array(), "table_row" => array(), "cleanup" => array(), "finalize" => array());

			$this->state["hidden"] = array();
			$this->state["insiderow"] = false;
			$this->state["firstitem"] = false;

			// Let form handlers modify the options and state arrays.
			foreach (self::$formhandlers["init"] as $callback)
			{
				if (is_callable($callback))  call_user_func_array($callback, array(&$this->state, &$options));
			}
		}

		protected function AlterField($num, &$field, $id)
		{
			// Let form handlers process custom, modified, and other field types.
			foreach (self::$formhandlers["field_type"] as $callback)
			{
				if (is_callable($callback))  call_user_func_array($callback, array(&$this->state, $num, &$field, $id));
			}
		}

		protected function ProcessField($num, &$field, $id)
		{
			if (is_string($field))
			{
				if ($field == "split" && !$this->state["insiderow"])  echo "<hr />";
				else if ($field == "startrow")
				{
					if ($this->state["insiderow"])
					{
						if ($this->state["responsive"] && $this->state["insiderowwidth"])  echo "<td></td>";

						echo "</tr><tr>";
					}
					else if ($this->state["formtables"])
					{
						$this->state["insiderow"] = true;
						$this->state["insiderowwidth"] = false;
?>
			<div class="fieldtablewrap<?php if ($this->state["firstitem"])  echo " firstitem"; ?>"><table class="rowwrap"><tbody><tr>
<?php
						$this->state["firstitem"] = false;
					}
				}
				else if ($field == "endrow" && $this->state["formtables"] && $this->state["insiderow"])
				{
					if ($this->state["responsive"] && $this->state["insiderowwidth"])  echo "<td></td>";

?>
			</tr></tbody></table></div>
<?php
					$this->state["insiderow"] = false;
				}
				else if (substr($field, 0, 5) == "html:")
				{
					echo substr($field, 5);
				}

				// Let form handlers process strings.
				foreach (self::$formhandlers["field_string"] as $callback)
				{
					if (is_callable($callback))  call_user_func_array($callback, array(&$this->state, $num, &$field, $id));
				}
			}
			else if (!isset($field["type"]) || (isset($field["use"]) && !$field["use"]))
			{
				// Do nothing if type is not specified.
			}
			else if (isset($this->state["customfieldtypes"][$field["type"]]))
			{
				// Output custom fields.
				$this->AlterField($num, $field, $id);
			}
			else
			{
				if ($this->state["insiderow"])
				{
					if (!$this->state["responsive"] || !isset($field["width"]))  echo "<td>";
					else
					{
						echo "<td style=\"width: " . htmlspecialchars($field["width"]) . ";\">";

						$this->state["insiderowwidth"] = true;
					}
				}

?>
			<div class="formitem<?php echo ((isset($field["split"]) && $field["split"] === false) || $this->state["firstitem"] ? " firstitem" : ""); ?>">
<?php
				$this->state["firstitem"] = false;
				if (isset($field["title"]))
				{
					if (is_string($field["title"]))
					{
?>
			<div class="formitemtitle"><?php echo htmlspecialchars(self::FFTranslate($field["title"])); ?></div>
<?php
					}
				}
				else if (isset($field["htmltitle"]))
				{
?>
			<div class="formitemtitle"><?php echo self::FFTranslate($field["htmltitle"]); ?></div>
<?php
				}
				else if ($field["type"] == "checkbox" && $this->state["insiderow"])
				{
?>
			<div class="formitemtitle">&nbsp;</div>
<?php
				}

				if (isset($field["width"]) && !$this->state["formwidths"])  unset($field["width"]);

				if (isset($field["name"]) && isset($field["default"]))
				{
					if ($field["type"] == "select")
					{
						if (!isset($field["select"]))
						{
							$field["select"] = self::GetValue($field["name"], $field["default"]);
							if (is_array($field["select"]))  $field["select"] = self::GetSelectValues($field["select"]);
						}
					}
					else if ($field["type"] == "checkbox")
					{
						if (!isset($field["check"]) && isset($field["value"]))  $field["check"] = (isset($_REQUEST[$field["name"]]) && $_REQUEST[$field["name"]] === $field["value"] ? true : ($_SERVER["REQUEST_METHOD"] === "GET" ? $field["default"] : false));
					}
					else
					{
						if (!isset($field["value"]))  $field["value"] = self::GetValue($field["name"], $field["default"]);
					}
				}

				if (isset($field["focus"]) && $field["focus"] && $this->state["autofocused"] === false)  $this->state["autofocused"] = $id;
				if ($field["type"] == "select" && isset($field["mode"]) && $field["mode"] == "formhandler")  unset($field["mode"]);

				$this->AlterField($num, $field, $id);

				switch ($field["type"])
				{
					case "static":
					{
?>
			<div class="formitemdata">
				<div class="staticwrap"<?php if (isset($field["width"]))  echo " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . ";\""; ?>><?php echo htmlspecialchars($field["value"]); ?></div>
			</div>
<?php
						break;
					}
					case "text":
					{
?>
			<div class="formitemdata">
				<div class="textitemwrap"<?php if (isset($field["width"]))  echo " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . ";\""; ?>><input class="text" type="text" id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" value="<?php echo htmlspecialchars($field["value"]); ?>"<?php if ($this->state["autofocused"] === $id)  echo " autofocus"; ?> /></div>
			</div>
<?php
						break;
					}
					case "password":
					{
?>
			<div class="formitemdata">
				<div class="textitemwrap"<?php if (isset($field["width"]))  echo " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . ";\""; ?>><input class="text" type="password" id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" value="<?php echo htmlspecialchars($field["value"]); ?>"<?php if ($this->state["autofocused"] === $id)  echo " autofocus"; ?> /></div>
			</div>
<?php
						break;
					}
					case "checkbox":
					{
?>
			<div class="formitemdata">
				<div class="checkboxitemwrap"<?php if (isset($field["width"]))  echo " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . ";\""; ?>>
					<input class="checkbox" type="checkbox" id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" value="<?php echo htmlspecialchars($field["value"]); ?>"<?php if (isset($field["check"]) && $field["check"])  echo " checked"; ?><?php if ($this->state["autofocused"] === $id)  echo " autofocus"; ?> />
					<label for="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars(self::FFTranslate($field["display"])); ?></label>
				</div>
			</div>
<?php
						break;
					}
					case "select":
					{
						if (!isset($field["multiple"]) || $field["multiple"] !== true)  $mode = (isset($field["mode"]) && $field["mode"] == "radio" ? "radio" : "select");
						else if (!isset($field["mode"]) || ($field["mode"] != "formhandler" && $field["mode"] != "select"))  $mode = "checkbox";
						else  $mode = $field["mode"];

						if (isset($field["width"]))  $stylewidth = " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . ";\"";
						else  $stylewidth = "";

						if (isset($field["height"]) && isset($field["multiple"]) && $field["multiple"] === true)  $styleheight = " style=\"height: " . htmlspecialchars($field["height"]) . ";\"";
						else  $styleheight = "";

						if (!isset($field["select"]))  $field["select"] = array();
						else if (is_string($field["select"]))  $field["select"] = array($field["select"] => true);

?>
			<div class="formitemdata">
<?php

						$idbase = htmlspecialchars($id);
						if ($mode == "checkbox" || $mode == "radio")
						{
							$idnum = 0;
							foreach ($field["options"] as $name => $value)
							{
								if (is_array($value))
								{
									foreach ($value as $name2 => $value2)
									{
										$id2 = $idbase . ($idnum ? "_" . $idnum : "");
?>
				<div class="<?=$mode?>itemwrap"<?php echo $stylewidth; ?>>
					<input class="<?=$mode?>" type="<?=$mode?>" id="<?php echo $id2; ?>" name="<?php echo htmlspecialchars($field["name"]); ?><?php if ($mode == "checkbox")  echo "[]"; ?>" value="<?php echo htmlspecialchars($name2); ?>"<?php if (isset($field["select"][$name2]))  echo " checked"; ?><?php if ($this->state["autofocused"] === $id)  { echo " autofocus";  $this->state["autofocused"] = true; } ?> />
					<label for="<?php echo $id2; ?>"><?php echo htmlspecialchars(self::FFTranslate($name)); ?> - <?php echo ($value2 == "" ? "&nbsp;" : htmlspecialchars(self::FFTranslate($value2))); ?></label>
				</div>
<?php
										$idnum++;
									}
								}
								else
								{
									$id2 = $idbase . ($idnum ? "_" . $idnum : "");
?>
				<div class="<?=$mode?>itemwrap"<?php echo $stylewidth; ?>>
					<input class="<?=$mode?>" type="<?=$mode?>" id="<?php echo $id2; ?>" name="<?php echo htmlspecialchars($field["name"]); ?><?php if ($mode == "checkbox")  echo "[]"; ?>" value="<?php echo htmlspecialchars($name); ?>"<?php if (isset($field["select"][$name]))  echo " checked"; ?><?php if ($this->state["autofocused"] === $id)  { echo " autofocus";  $this->state["autofocused"] = true; } ?> />
					<label for="<?php echo $id2; ?>"><?php echo ($value == "" ? "&nbsp;" : htmlspecialchars(self::FFTranslate($value))); ?></label>
				</div>
<?php
									$idnum++;
								}
							}
						}
						else
						{
?>
				<div class="selectitemwrap"<?php echo $stylewidth; ?>>
					<select class="<?php echo (isset($field["multiple"]) && $field["multiple"] === true ? "multi" : "single"); ?>" id="<?php echo $idbase; ?>" name="<?php echo htmlspecialchars($field["name"]) . (isset($field["multiple"]) && $field["multiple"] === true ? "[]" : ""); ?>"<?php if (isset($field["multiple"]) && $field["multiple"] === true)  echo " multiple"; ?><?php if ($this->state["autofocused"] === $id)  echo " autofocus"; ?><?php echo $styleheight; ?>>
<?php
							foreach ($field["options"] as $name => $value)
							{
								if (is_array($value))
								{
?>
						<optgroup label="<?php echo htmlspecialchars(self::FFTranslate($name)); ?>">
<?php
									foreach ($value as $name2 => $value2)
									{
?>
							<option value="<?php echo htmlspecialchars($name2); ?>"<?php if (isset($field["select"][$name2]))  echo " selected"; ?>><?php echo ($value2 == "" ? "&nbsp;" : htmlspecialchars(self::FFTranslate($value2))); ?></option>
<?php
									}
?>
						</optgroup>
<?php
								}
								else
								{
?>
						<option value="<?php echo htmlspecialchars($name); ?>"<?php if (isset($field["select"][$name]))  echo " selected"; ?>><?php echo ($value == "" ? "&nbsp;" : htmlspecialchars(self::FFTranslate($value))); ?></option>
<?php
								}
							}
?>
					</select>
				</div>
<?php
						}
?>
			</div>
<?php

						break;
					}
					case "textarea":
					{
						if (isset($field["width"]))  $stylewidth = " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . ";\"";
						else  $stylewidth = "";

						if (isset($field["height"]))  $styleheight = " style=\"height: " . htmlspecialchars($field["height"]) . ";\"";
						else  $styleheight = "";

?>
			<div class="formitemdata">
				<div class="textareawrap"<?php echo $stylewidth; ?>><textarea class="text"<?php echo $styleheight; ?> id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" rows="5" cols="50"<?php if ($this->state["autofocused"] === $id)  echo " autofocus"; ?>><?php echo htmlspecialchars($field["value"]); ?></textarea></div>
			</div>
<?php
						break;
					}
					case "table":
					{
						$idbase = $id . "_table";

?>
			<div class="formitemdata">
<?php
						if ($this->state["formtables"])
						{
?>
				<table id="<?php echo htmlspecialchars($idbase); ?>" class="formitemtable<?php if (isset($field["class"]))  echo " " . htmlspecialchars($field["class"]); ?>"<?php if (isset($field["width"]))  echo " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . "\""; ?>>
					<thead>
<?php
							// Let form handlers process the columns.
							$trattrs = array("class" => "head");
							$colattrs = array();
							if (!isset($field["cols"]))  $field["cols"] = array();
							foreach ($field["cols"] as $col)  $colattrs[] = array();
							foreach (self::$formhandlers["table_row"] as $callback)
							{
								if (is_callable($callback))  call_user_func_array($callback, array(&$this->state, $num, &$field, $idbase, "head", -1, &$trattrs, &$colattrs, &$field["cols"]));
							}
?>
					<tr<?php foreach ($trattrs as $key => $val)  echo " " . $key . "=\"" . htmlspecialchars($val) . "\""; ?>>
<?php
							foreach ($field["cols"] as $num2 => $col)
							{
?>
						<th<?php foreach ($colattrs[$num2] as $key => $val)  echo " " . $key . "=\"" . htmlspecialchars($val) . "\""; ?>><?php echo (isset($field["htmlcols"]) && $field["htmlcols"] ? self::FFTranslate($col) : htmlspecialchars(self::FFTranslate($col))); ?></th>
<?php
							}
?>
					</tr>
					</thead>
					<tbody>
<?php
							$colattrs = array();
							foreach ($field["cols"] as $col)  $colattrs[] = (isset($field["nowrap"]) && ((is_string($field["nowrap"]) && $col === $field["nowrap"]) || (is_array($field["nowrap"]) && in_array($col, $field["nowrap"]))) ? array("class" => "nowrap") : array());

							$rownum = 0;
							$altrow = false;
							if (isset($field["callback"]) && is_callable($field["callback"]))  $field["rows"] = call_user_func_array($field["callback"], array($field));
							while (count($field["rows"]))
							{
								foreach ($field["rows"] as $row)
								{
									// Let form handlers process the current row.
									$trattrs = array("class" => "row" . ($altrow ? " altrow" : ""));
									$colattrs2 = $colattrs;
									foreach (self::$formhandlers["table_row"] as $callback)
									{
										if (is_callable($callback))  call_user_func_array($callback, array(&$this->state, $num, &$field, $idbase, "body", $rownum, &$trattrs, &$colattrs2, &$row));
									}

									if (count($row) < count($colattrs2))  $colattrs2[count($row) - 1]["colspan"] = (count($colattrs2) - count($row) + 1);
?>
					<tr<?php foreach ($trattrs as $key => $val)  echo " " . $key . "=\"" . htmlspecialchars($val) . "\""; ?>>
<?php
									$num2 = 0;
									foreach ($row as $col)
									{
?>
						<td<?php if (isset($colattrs2[$num2]))  { foreach ($colattrs2[$num2] as $key => $val)  echo " " . $key . "=\"" . htmlspecialchars($val) . "\""; } ?>><?php echo $col; ?></td>
<?php
										$num2++;
									}
?>
					</tr>
<?php
									$rownum++;
									$altrow = !$altrow;
								}

								if (isset($field["callback"]) && is_callable($field["callback"]))  $field["rows"] = call_user_func_array($field["callback"], array($field));
								else  $field["rows"] = array();
							}
?>
					</tbody>
				</table>
<?php
						}
						else
						{
?>
				<div class="nontablewrap" id="<?php echo htmlspecialchars($idbase); ?>"<?php if (isset($field["width"]))  echo " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . "\""; ?>>
<?php
							// Let form handlers process the columns.
							$trattrs = array();
							$headcolattrs = array();
							if (!isset($field["cols"]))  $field["cols"] = array();
							foreach ($field["cols"] as $num2 => $col)
							{
								$headcolattrs[] = array("class" => "nontable_th" . ($num2 ? "" : " firstcol"));
							}
							foreach (self::$formhandlers["table_row"] as $callback)
							{
								if (is_callable($callback))  call_user_func_array($callback, array(&$this->state, $num, &$field, $idbase, "head", -1, &$trattrs, &$headcolattrs, &$field["cols"]));
							}

							$colattrs = array();
							foreach ($field["cols"] as $col)  $colattrs[] = array("class" => "nontable_td");

							$rownum = 0;
							$altrow = false;
							if (isset($field["callback"]) && is_callable($field["callback"]))  $field["rows"] = call_user_func_array($field["callback"], array($field));
							while (count($field["rows"]))
							{
								foreach ($field["rows"] as $row)
								{
									// Let form handlers process the current row.
									$trattrs = array("class" => "nontable_row" . ($altrow ? " altrow" : "") . ($rownum ? "" : " firstrow"));
									$colattrs2 = $colattrs;
									foreach (self::$formhandlers["table_row"] as $callback)
									{
										if (is_callable($callback))  call_user_func_array($callback, array(&$this->state, $num, &$field, $idbase, "body", $rownum, &$trattrs, &$colattrs2, &$row));
									}

?>
					<div<?php foreach ($trattrs as $key => $val)  echo " " . $key . "=\"" . htmlspecialchars($val) . "\""; ?>>
<?php
									$num2 = 0;
									foreach ($row as $col)
									{
?>
						<div<?php foreach ($headcolattrs as $key => $val)  echo " " . $key . "=\"" . htmlspecialchars($val) . "\""; ?>><?php echo (isset($field["htmlcols"]) && $field["htmlcols"] ? self::FFTranslate(isset($field["cols"][$num2]) ? $field["cols"][$num2] : "") : htmlspecialchars(self::FFTranslate(isset($field["cols"][$num2]) ? $field["cols"][$num2] : ""))); ?></div>
						<div<?php if (isset($colattrs2[$num2]))  { foreach ($colattrs2[$num2] as $key => $val)  echo " " . $key . "=\"" . htmlspecialchars($val) . "\""; } ?>><?php echo $col; ?></div>
<?php
										$num2++;
									}
?>
					</div>
<?php
									$altrow = !$altrow;
								}

								if (isset($field["callback"]) && is_callable($field["callback"]))  $field["rows"] = call_user_func_array($field["callback"], array($field));
								else  $field["rows"] = array();
							}
?>
				</div>
<?php
						}
?>
			</div>
<?php

						break;
					}
					case "file":
					{
?>
			<div class="formitemdata">
				<div class="textitemwrap"<?php if (isset($field["width"]))  echo " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . "\""; ?>><input class="text" type="file" id="<?php echo htmlspecialchars($id); ?>" name="<?php echo htmlspecialchars($field["name"]) . (isset($field["multiple"]) && $field["multiple"] === true ? "[]" : ""); ?>"<?php if (isset($field["multiple"]) && $field["multiple"] === true)  echo " multiple";?><?php if (isset($field["accept"]) && is_string($field["accept"]))  echo " accept=\"" . htmlspecialchars($field["accept"]) . "\"";?><?php if ($this->state["autofocused"] === $id)  echo " autofocus"; ?> /></div>
			</div>
<?php
						break;
					}
					case "custom":
					{
?>
			<div class="formitemdata">
				<div id="<?php echo htmlspecialchars($id); ?>" class="customitemwrap"<?php if (isset($field["width"]))  echo " style=\"" . ($this->state["responsive"] ? "max-" : "") . "width: " . htmlspecialchars($field["width"]) . "\""; ?>>
<?php
						echo $field["value"];
?>
				</div>
			</div>
<?php
						break;
					}
				}

				if (isset($field["desc"]) && $field["desc"] != "")
				{
?>
			<div class="formitemdesc"><?php echo htmlspecialchars(self::FFTranslate($field["desc"])); ?></div>
<?php
				}
				else if (isset($field["htmldesc"]) && $field["htmldesc"] != "")
				{
?>
			<div class="formitemdesc"><?php echo $field["htmldesc"]; ?></div>
<?php
				}

				if (isset($field["error"]) && $field["error"] != "")
				{
?>
			<div class="formitemresult">
				<div class="formitemerror"><?php echo htmlspecialchars(self::FFTranslate($field["error"])); ?></div>
			</div>
<?php
				}
?>
			</div>
<?php
				if ($this->state["insiderow"])  echo "</td>";
			}
		}

		protected function CleanupFields()
		{
			if ($this->state["insiderow"])
			{
				if ($this->state["responsive"] && $this->state["insiderowwidth"])  echo "<td></td>";

?>
			</tr></tbody></table></div>
<?php
			}

			// Let form handlers process other field types.
			foreach (self::$formhandlers["cleanup"] as $callback)
			{
				if (is_callable($callback))  call_user_func_array($callback, array(&$this->state));
			}
		}

		protected function ProcessSubmit(&$options)
		{
			if (is_string($options["submit"]))
			{
				$name = (isset($options["submitname"]) ? $options["submitname"] : "");
				$options["submit"] = array($name => $options["submit"]);
			}
?>
		<div class="formsubmit">
<?php
			foreach ($options["submit"] as $name => $val)
			{
				if (is_int($name) && isset($options["submitname"]))  $name = $options["submitname"];
?>
			<input class="submit" type="submit"<?php if ($name !== "")  echo " name=\"" . htmlspecialchars(isset($options["hashnames"]) && $options["hashnames"] ? $this->GetHashedFieldName($name) : $name) . "\""; ?> value="<?php echo htmlspecialchars(self::FFTranslate($val)); ?>" />
<?php
			}
?>
		</div>
<?php
		}

		public function OutputFlexFormsJS($scripttag = true)
		{
			if ($scripttag)  echo "<script type=\"text/javascript\">\n";

?>
window.FlexForms = window.FlexForms || {
	version: '<?php echo self::JSSafe($this->version); ?>',

	modules: {},

	Extend: function(target, src) {
		for (var x in src)  { target[x] = src[x]; }
	},

	cssoutput: {},

	LoadCSS: function(name, url, cssmedia) {
		var $this = this;

		if ($this.cssoutput[name] !== undefined)  return;

		if ($this.version !== '')  url += (url.indexOf('?') > -1 ? '&' : '?') + $this.version;

		if (document.createStyleSheet)
		{
			var sheet = document.createStyleSheet(url);
			sheet.media = (cssmedia != undefined ? cssmedia : 'all');
		}
		else
		{
			var tag = document.createElement('link');
			tag.rel = 'stylesheet';
			tag.type = 'text/css';
			tag.href = url;
			tag.media = (cssmedia != undefined ? cssmedia : 'all');

			document.getElementsByTagName('head')[0].appendChild(tag);
		}

		$this.cssoutput[name] = true;
	},

	jsqueue: {},

	LoadJSQueueItem: function(name) {
		var $this = this;

		var done = false;
		var s = document.createElement('script');

		$this.jsqueue[name].loading = true;
		$this.jsqueue[name].retriesleft = $this.jsqueue[name].retriesleft || 3;

		s.onload = function() {
			if (!done)  { done = true;  delete $this.jsqueue[name];  $this.ProcessJSQueue.call(window.FlexForms); }
		};

		s.onreadystatechange = function() {
			if (!done && s.readyState === 'complete')  { done = true;  delete $this.jsqueue[name];  $this.ProcessJSQueue.call(window.FlexForms); }
		};

		s.onerror = function() {
			if (!done)
			{
				done = true;

				$this.jsqueue[name].retriesleft--;
				if ($this.jsqueue[name].retriesleft > 0)
				{
					$this.jsqueue[name].loading = false;

					setTimeout(function() { $this.ProcessJSQueue.call(window.FlexForms) }, 250);
				}
			}
		};

		s.src = $this.jsqueue[name].src + ($this.version === '' ? '' : ($this.jsqueue[name].src.indexOf('?') > -1 ? '&' : '?') + $this.version);

		document.body.appendChild(s);
	},

	ready: false,

	GetObjectFromPath: function(path) {
		var obj = window;
		path = path.split('.');
		for (var x = 0; x < path.length; x++)
		{
			if (obj[path[x]] === undefined)  return;

			obj = obj[path[x]];
		}

		return obj;
	},

	ProcessJSQueue: function() {
		var $this = this;

		$this.ready = true;

		for (var name in $this.jsqueue) {
			if ($this.jsqueue.hasOwnProperty(name))
			{
				if (($this.jsqueue[name].loading === undefined || $this.jsqueue[name].loading === false) && ($this.jsqueue[name].dependency === false || $this.jsqueue[$this.jsqueue[name].dependency] === undefined))
				{
					if ($this.jsqueue[name].detect !== undefined && $this.GetObjectFromPath($this.jsqueue[name].detect) !== undefined)  delete $this.jsqueue[name];
					else if ($this.jsqueue[name].mode === "src")  $this.LoadJSQueueItem(name);
					else if ($this.jsqueue[name].mode === "inline")
					{
						$this.jsqueue[name].src();

						delete $this.jsqueue[name];
					}
				}
			}
		}
	},

	initialized: false,

	Init: function() {
		var $this = this;

		if ($this.ready)  $this.ProcessJSQueue.call(window.FlexForms);
		else if (!$this.initialized)
		{
			if (document.addEventListener)
			{
				function regevent(event) {
					document.removeEventListener("DOMContentLoaded", regevent, false);

					$this.ProcessJSQueue.call(window.FlexForms);
				}

				document.addEventListener("DOMContentLoaded", regevent);
			}
			else
			{
				setTimeout(function() { $this.ProcessJSQueue.call(window.FlexForms) }, 250);
			}

			$this.initialized = true;
		}
	}
};
<?php
			if ($scripttag)  echo "</script>\n";
		}

		protected function Finalize()
		{
			// Output FlexForms Javascript.  External dependencies are not allowed here.
			$this->OutputFlexFormsJS(!$this->state["ajax"]);

			// Queue up jQuery and jQuery UI.  Even though these are added last, they end up being output first.
			$this->OutputJQuery(true);

			if ($this->state["jqueryuiused"])  $this->OutputJQueryUI(true);

			// Output CSS.
			$output = $this->state["cssoutput"];
			foreach ($output as $name => $val)  unset($this->state["css"][$name]);
			if ($this->state["ajax"])  echo "<script type=\"text/javascript\">\n";
			do
			{
				$found = false;

				foreach ($this->state["css"] as $name => $info)
				{
					if ($info["mode"] === "link" && ($info["dependency"] === false || !isset($this->state["css"][$info["dependency"]])))
					{
						if ($this->state["ajax"])  echo "FlexForms.LoadCSS('" . self::JSSafe($info["src"]) . "'" . (isset($info["media"]) ? ", '" . self::JSSafe($info["media"]) . "'" : "") . ");\n";
						else
						{
							echo "<link rel=\"stylesheet\" href=\"" . htmlspecialchars($info["src"] . ($this->version !== "" ? (strpos($info["src"], "?") === false ? "?" : "&") . $this->version : "")) . "\" type=\"text/css\" media=\"" . (isset($info["media"]) ? $info["media"] : "all") . "\" />\n";

							$this->state["cssoutput"][$name] = true;
						}

						unset($this->state["css"][$name]);
						$output[$name] = true;

						$found = true;
					}
				}
			} while ($found);
			if (!$this->state["ajax"])  echo "<script type=\"text/javascript\">\n";
			echo "FlexForms.Extend(FlexForms.cssoutput, " . json_encode($this->state["cssoutput"], JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT) . ");\n";
			echo "</script>\n";
			foreach ($this->state["css"] as $name => $info)
			{
				if ($info["mode"] === "inline")  echo $info["src"];
			}
			$this->state["css"] = array();
			$this->state["cssoutput"] = $output;

			// Output Javascript.
			$output = $this->state["jsoutput"];
			foreach ($output as $name => $val)  unset($this->state["js"][$name]);
			do
			{
				$found = false;

				foreach ($this->state["js"] as $name => $info)
				{
					if ($info["dependency"] === false || !isset($this->state["js"][$info["dependency"]]))
					{
						if ($info["mode"] === "src")
						{
							$info["loading"] = false;

							if ($this->state["ajax"])  echo "FlexForms.jsqueue['" . self::JSSafe($name) . "'] = " . json_encode($info, JSON_UNESCAPED_SLASHES) . ";\n";
							else  echo "<script type=\"text/javascript\" src=\"" . htmlspecialchars($info["src"] . ($this->version !== "" ? (strpos($info["src"], "?") === false ? "?" : "&") . $this->version : "")) . "\"></script>\n";
						}
						else if ($info["mode"] === "inline")
						{
							if (!$this->state["ajax"])  echo "<script type=\"text/javascript\">\n" . $info["src"] . "</script>\n";
							else
							{
								$src = $info["src"];
								unset($info["src"]);
								echo "FlexForms.jsqueue['" . self::JSSafe($name) . "'] = " . json_encode($info, JSON_UNESCAPED_SLASHES) . ";\n";
								echo "FlexForms.jsqueue['" . self::JSSafe($name) . "'].src = function() {\n" . $src . "};\n";
							}
						}

						unset($this->state["js"][$name]);
						$output[$name] = true;

						$found = true;
					}
				}
			} while ($found);
			$this->state["js"] = array();
			$this->state["jsoutput"] = $output;

			// Let form handlers finalize other field types.
			foreach (self::$formhandlers["finalize"] as $callback)
			{
				if (is_callable($callback))  call_user_func_array($callback, array(&$this->state));
			}

			// Initialize FlexForms (only needed for any AJAX bits).
?>
<script type="text/javascript">
FlexForms.Init.call(FlexForms);
</script>
<?php
		}
	}
?>