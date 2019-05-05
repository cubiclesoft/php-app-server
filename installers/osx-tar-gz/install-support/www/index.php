<?php
	// Main installer for Mac OSX.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	require_once "support/str_basics.php";
	require_once "support/flex_forms.php";
	require_once "support/random.php";
	require_once "support/dir_helper.php";

	function DisplayInitError($title, $msg)
	{
		global $ff;

		header("Content-type: text/html; charset=UTF-8");

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?=htmlspecialchars($title)?> | Installer Initialization Error</title>
<link rel="stylesheet" href="support/install.css" type="text/css" media="all" />
</head>
<body>
<div id="headerwrap"><div id="header">Installer Initialization Error</div></div>
<div id="contentwrap"><div id="content">
<h1><?=htmlspecialchars($title)?></h1>
<p><?=htmlspecialchars($msg)?></p>
</div></div>
<div id="footerwrap"><div id="footer">
&copy <?=date("Y")?>, All Rights Reserved.
</div></div>
</body>
</html>
<?php
	}

	Str::ProcessAllInput();

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	// Determine the OS.
	$os = php_uname("s");
	$windows = (strtoupper(substr($os, 0, 3)) == "WIN");
	$mac = (strtoupper(substr($os, 0, 6)) == "DARWIN");

	if (!$mac)  DisplayInitError("Invalid OS", "This installer only runs on Mac OSX.");

	// Find a PHP binary.
	require_once "support/process_helper.php";

	$phpbin = ProcessHelper::FindExecutable("php", "/usr/bin");
	if ($phpbin === false)  DisplayInitError("PHP Missing", "This installer requires PHP to be installed (http://www.php.net/).");

	// Verify that this is a package.
	if (!is_dir($rootpath . "/../../app/"))  DisplayInitError("Missing Application", "This application does not appear to be packaged properly.  Missing 'app' directory.");
	$basepath = str_replace("\\", "/", rtrim(realpath($rootpath . "/../../app/"), "/"));
	if (!file_exists($basepath . "/Contents/MacOS/server.php"))  DisplayInitError("Missing App Server", "This application does not appear to be packaged properly.  Missing 'server.php'.");

	$appname = false;
	$dir = opendir($basepath . "/Contents/MacOS");
	while (($file = readdir($dir)) !== false)
	{
		if (substr($file, -7) === ".phpapp")  $appname = substr($file, 0, -7);
	}
	closedir($dir);
	if ($appname === false)  DisplayInitError("Missing App Startup", "This application does not appear to be packaged properly.  Missing the required .phpapp file.");

	$packageinfo = json_decode(file_get_contents($basepath . "/Contents/MacOS/package.json"), true);
	if (!is_array($packageinfo))  DisplayInitError("Missing/Invalid Configuration", "The 'package.json' file is missing or not valid JSON.");

	$vendorappname = ($packageinfo["vendor"] != "" ? trim(preg_replace('/\s+/', "-", preg_replace('/[^a-zA-Z]/', " ", $packageinfo["vendor"])), "-") : "phpapp");
	if ($vendorappname === "")  $vendorappname = "phpapp";
	$vendorappname .= "-" . $appname;

	$skey = $vendorappname . "_install";
	session_start();
	if (!isset($_SESSION[$skey]))  $_SESSION[$skey] = array();
	if (!isset($_SESSION[$skey]["secret"]))
	{
		$rng = new CSPRNG();
		$_SESSION[$skey]["secret"] = $rng->GetBytes(64);
	}

	$ff = new FlexForms();
	$ff->SetSecretKey($_SESSION[$skey]["secret"]);
	$ff->CheckSecurityToken("action");

	function OutputHeader($title)
	{
		global $ff, $packageinfo;

		header("Content-type: text/html; charset=UTF-8");

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?=htmlspecialchars($title)?> | <?=htmlspecialchars($packageinfo["app_name"] . " " . $packageinfo["app_ver"])?> Installer</title>
<link rel="stylesheet" href="support/install.css" type="text/css" media="all" />
<?php
		$ff->OutputJQuery();

		// Connect with a WebSocket to the exit-app extension and set the delay to three seconds.
		// All connections have to drop for three seconds before the server automatically exits.
?>
<script type="text/javascript">
function InitExitApp()
{
	var ws = new WebSocket((window.location.protocol === 'https:' ? 'wss://' : 'ws://') + window.location.host + '/exit-app/');

	ws.addEventListener('open', function(e) {
		var msg = {
			authtoken: '<?=hash_hmac("sha256", "/exit-app/", $_SERVER["PAS_SECRET"])?>',
			delay: 3
		};

		ws.send(JSON.stringify(msg));
	});

	ws.addEventListener('close', function(e) {
		setTimeout(InitExitApp, 500);
	});
}

InitExitApp();
</script>
</head>
<body>
<div id="headerwrap"><div id="header"><?=htmlspecialchars($packageinfo["app_name"] . " " . $packageinfo["app_ver"])?> Installer</div></div>
<div id="contentwrap"><div id="content">
<h1><?=htmlspecialchars($title)?></h1>
<?php
	}

	function OutputFooter()
	{
		global $packageinfo;

?>
</div></div>
<div id="footerwrap"><div id="footer">
<a href="<?=htmlspecialchars($packageinfo["business_url"])?>" style="color: #222222; text-decoration: none;" target="_blank"><?=str_replace("(C)", "&copy", htmlspecialchars($packageinfo["app_copyright"]))?></a> | <a href="<?=htmlspecialchars($packageinfo["app_url"])?>" target="_blank">Product website</a> | <a href="<?=htmlspecialchars($packageinfo["support_url"])?>" target="_blank">Get support</a>
</div></div>
</body>
</html>
<?php
	}

	$errors = array();
	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "done")
	{
		OutputHeader("Installation Finished");

		$ff->OutputMessage("success", "The installation completed successfully.");

?>
<p><?=htmlspecialchars($packageinfo["app_name"] . " " . $packageinfo["app_ver"])?> was successfully installed.</p>

<p>Close this tab/window to exit the installer.</p>
<?php

		OutputFooter();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "options")
	{
		// Determine if the user is an admin.
		$testdir = "phpapp_" . microtime(true);
		$admin = @mkdir("/Applications/" . $testdir);
		@rmdir("/Applications/" . $testdir);

		$scopes = array();
		if ($admin)  $scopes["all_users"] = "All users";
		if (getenv("HOME") !== false)  $scopes["curr_user"] = "Only for you";

		if (isset($_REQUEST["submit"]))
		{
			if (!isset($scopes[$_REQUEST["scope"]]))  $errors["scope"] = "Please select an option.";

			if (count($errors))  $errors["msg"] = "Please correct the errors below and try again.";
			else
			{
				// Split the installation based on whether or not the app is being installed for all users.
				if ($_REQUEST["scope"] === "all_users")
				{
					$installpath = "/Applications/" . $packageinfo["app_name"] . ".app";
				}
				else
				{
					if (getenv("HOME") === false)  DisplayInitError("Missing/Corrupt Environment", "Unable to install due to missing environment variable.  Expected 'HOME'.");

					$homepath = getenv("HOME");
					$homepath = rtrim($homepath, "/");

					$installpath = $homepath . "/Applications";
					@mkdir($installpath, 0700);
					@chmod($installpath, 0700);

					$installpath .= "/" . $packageinfo["app_name"] . ".app";
				}

				DirHelper::Delete($installpath);
				@mkdir($installpath, ($admin ? 0755 : 0700));
				@chmod($installpath, ($admin ? 0755 : 0700));

				DirHelper::Copy($basepath, $installpath);
				DirHelper::SetPermissions($installpath, false, false, ($admin ? 0755 : 0700), false, false, ($admin ? 0644 : 0600));

				// Modify the .phpapp file.
				$filename = $installpath . "/Contents/MacOS/" . $appname . ".phpapp";
				$data = file_get_contents($filename);
				$data = "#!" . $phpbin . "\n" . $data;
				file_put_contents($filename, $data);
				chmod($filename, ($admin ? 0755 : 0700));

				// Remove quarantine so that the user isn't annoyed at having to approve another app.
				system("xattr -r -d com.apple.quarantine " . escapeshellarg($installpath));

				// Optional dock icon.
				if (isset($_REQUEST["dock_icon"]) && $_REQUEST["dock_icon"] === "Yes")
				{
					system("defaults write com.apple.dock persistent-apps -array-add \"<dict><key>tile-data</key><dict><key>file-data</key><dict><key>_CFURLString</key><string>" . htmlspecialchars($installpath) . "</string><key>_CFURLStringType</key><integer>0</integer></dict></dict></dict>\"");
					system("killall Dock");
				}

				// Post-install script.
				if (file_exists($installpath . "/support/post-install.php"))
				{
					system("php " . escapeshellarg($installpath . "/support/post-install.php"));
				}

				// When the dock icon is not used, the relevant Applications folder is opened instead.
				if (!isset($_REQUEST["dock_icon"]) || $_REQUEST["dock_icon"] !== "Yes")  system("open " . escapeshellarg(dirname($installpath)));

				header("Location: " . $ff->GetFullRequestURLBase() . "?action=done&sec_t=" . $ff->CreateSecurityToken("done"));

				exit();
			}
		}

		OutputHeader("Select Options");

		if (count($errors))  $ff->OutputMessage("error", $errors["msg"]);

		$contentopts = array(
			"fields" => array(
				array(
					"title" => "Install for",
					"type" => "select",
					"mode" => "radio",
					"name" => "scope",
					"options" => $scopes,
					"default" => ($admin ? "all_users" : "curr_user")
				),
				array(
					"title" => "Add icon to Dock",
					"type" => "select",
					"mode" => "radio",
					"name" => "dock_icon",
					"options" => array("Yes" => "Yes", "No" => "No"),
					"default" => ($packageinfo["user_dock_icon"] ? "Yes" : "No")
				)
			),
			"submit" => "Install",
			"submitname" => "submit"
		);

		$ff->Generate($contentopts, $errors);

		OutputFooter();
	}
	else
	{
		if (!file_exists($basepath . "/Contents/MacOS/" . $appname . "-license.txt") || (isset($_REQUEST["submit"]) && $_REQUEST["submit"] === "I Agree"))
		{
			header("Location: " . $ff->GetFullRequestURLBase() . "?action=options&sec_t=" . $ff->CreateSecurityToken("options"));

			exit();
		}

		OutputHeader("License Agreement");

?>
<p>Please read the following End User License Agreement.  Proceeding to the next step constitutes accepting the agreement.</p>
<?php

		$data = htmlspecialchars(file_get_contents($basepath . "/Contents/MacOS/" . $appname . "-license.txt"));
		$data = str_replace("\n\n", "</p><p>", $data);
		$data = str_replace("\n", "<br>\n", $data);
		$data = str_replace("</p><p>", "</p>\n<p>", $data);

		$contentopts = array(
			"fields" => array(
				array(
					"type" => "custom",
					"value" => "<p>" . $data . "</p>"
				)
			),
			"submit" => "I Agree",
			"submitname" => "submit"
		);

		$ff->Generate($contentopts, $errors);

		OutputFooter();
	}
?>