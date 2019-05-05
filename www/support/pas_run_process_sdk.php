<?php
	// PHP App Server long-running process SDK.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	// Load dependencies.
	if (!class_exists("RunProcessSDK", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/run-process/run_process_sdk.php";

	class PAS_RunProcessSDK extends RunProcessSDK
	{
		public function __construct()
		{
			if (!class_exists("WebBrowser", false))  require_once $_SERVER["PAS_ROOT"] . "/support/web_browser.php";

			parent::__construct();

			// Initialize the access information.
			$url = self::GetURL("http");
			$authtoken = self::GetAuthToken();

			$this->SetAccessInfo($url, "", $authtoken);
		}

		public static function GetURL($protocol = "ws")
		{
			return $protocol . (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? "s://" : "://") . $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . "/run-process/";
		}

		public static function GetAuthToken()
		{
			return hash_hmac("sha256", "/run-process/", $_SERVER["PAS_SECRET"]);
		}

		// FlexForms integration.
		public static function PAS_FF_FieldType(&$state, $num, &$field, $id)
		{
			if ($field["type"] === "pas_run_process")
			{
				$field["type"] = "run_process";

				$field["url"] = self::GetURL();
				$field["authuser"] = false;
				$field["authtoken"] = self::GetAuthToken();

				parent::FF_FieldType($state, $num, $field, $id);
			}
		}
	}

	// Register form handlers.
	if (is_callable("FlexForms::RegisterFormHandler"))
	{
		FlexForms::RegisterFormHandler("field_type", "PAS_RunProcessSDK::PAS_FF_FieldType");
	}
?>