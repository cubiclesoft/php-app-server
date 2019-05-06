PHP App Server
==============

Create lightweight, installable applications written in HTML, CSS, Javascript, and PHP for the Windows, Mac, and Linux desktop operating systems.

![Screenshot of a generated installer on Windows](https://user-images.githubusercontent.com/1432111/46253522-88f58200-c432-11e8-97c0-8337b2d181af.png)

What can be created with PHP App Server are real, installable software applications that take a fraction of the time to create when compared to traditional desktop application development.  Go from idea/concept to full deployment in 1/10th the time and support every major desktop OS with just one code base.

[![PHP App Server Overview and Tutorial video](https://user-images.githubusercontent.com/1432111/46376043-d3048080-c649-11e8-80e3-5729bf3b5e7e.png)](https://www.youtube.com/watch?v=pEykaINmvo0 "PHP App Server Overview and Tutorial")

PHP App Server is a fully-featured and extensible web server written in PHP with custom features specially designed to be used in a traditional desktop OS environment.  When the user runs the software via their Start menu, application launcher, etc., the software starts the server and then launches the user's preferred web browser to access the application.  PHP powers the backend while the web browser handles all the nitty-gritty details of displaying the user interface.  The ready-made installer scripts simplify the process of creating final release packages for delivery to your user's computer systems.

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/)

Features
--------

* Fully extensible web server written in PHP.  (This is not the built-in web server found in PHP itself.)
* Relies on the user's preferred web browser instead of a traditional GUI.  Write applications with HTML, CSS, Javascript, and PHP.
* Long running process and high-performance localhost API support.
* WebSocket support.
* Virtual directory support.
* Dual document roots.
* Access and error logging.
* Zero configuration.
* Pre-made boilerplate installer scripts for Windows (EXE and MSI), Mac (.app bundle in a .tar.gz), and Linux (.tar.gz) with custom EULA and clean update support.
* Tiny installer sizes.  From 85KB (Linux, .tar.gz) up to 10MB (Windows, .exe).  When paying per GB of transfer, every byte counts.
* Branded to look like your own software application.  Your app's custom icon is placed in the Start menu, application launcher, desktop/dock, etc.
* Fully isolated, zero conflict software.
* Compatible with [CubicleSoft software](https://github.com/cubiclesoft).
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Getting Started
---------------

Download or clone the latest software release.  When cloning, be sure to use a fork and create a branch for your app before beginning development.  Doing so avoids accidentally overwriting your software whenever you fetch upstream updates for PHP App Server itself.

For the rest of this guide, a recent version of PHP is assumed to be installed.  There are many ways to make that happen.

From a command-line, run the following to get a list of command-line options:

```
php server.php -?
```

Start a web server on port 9002 by running:

```
php server.php -port=9002
```

The directory structure of PHP App Server is as follows:

* www - Where standard web server files go, including PHP files.  This is actually treated as a view of two separate directories.
* support - Contains files required by `server.php` to operate.
* extensions - Where long running processes, high performance APIs, and WebSocket responders can be registered.
* installers - Pre-made installer scripts for various OSes.

Create an `index.php` file in the 'www' directory:

```php
<?php
	phpinfo();
```

Connect to the running server with your web browser at:

```
http://127.0.0.1:9002/
```

The output of `phpinfo()` is displayed in the browser and the result of the request is written to the command-line.

Change the URL to:

```
http://127.0.0.1:9002/api/v1/account/info
```

The same `index.php` file runs.

Rename or copy the `index.php` file to `api.php` and reload the page.  Now `api.php` is being called.  The virtual directory feature of PHP App Server is something that you might find useful as you develop your application.

Dual Document Roots
-------------------

Installed software applications cannot write to 'www'.  Applications are usually installed by a privileged user on the system but the person running the software will generally not have sufficient permissions to write to the 'www' directory.  This is an important consideration to keep in mind while developing a software application using PHP App Server.  Fortunately, there is a solution to this problem already built into the server:  Dual document roots.

When PHP code is executing from the application's 'www' directory, it has access to five `$_SERVER` variables that are passed in by and are unique to the PHP App Server environment:

* $_SERVER["DOCUMENT_ROOT_USER"] - A document root that can be written to and referenced by URLs.  Resides in the user's HOME directory on a per-OS basis.  Note that any '.php' files stored here are ignored by PHP App Server for security reasons.
* $_SERVER["PAS_USER_FILES"] - The parent directory of `DOCUMENT_ROOT_USER`.  Can also be written to but cannot be referenced by URLs.  Useful for storing private data for the application (e.g. a SQLite database).
* $_SERVER["PAS_PROG_FILES"] - The directory containing the access and error log files for PHP App Server.  Useful for providing a page in the application itself to view the error log file and other debugging information, which could be useful for debugging issues with the installed application on a user's system.
* $_SERVER["PAS_ROOT"] - The root directory containing the application server (i.e. where `server.php` resides).  Useful for accessing files in the `support` subdirectory.
* $_SERVER["PAS_SECRET"] - An internal, per-app instance session secret.  Useful for generating application XSRF tokens.

When a request is made to the web server, PHP App Server looks first for files in the application's 'www' directory.  If it doesn't find a file there, it then checks for the file in the path specified by `DOCUMENT_ROOT_USER`.

Writing Secure Software
-----------------------

Writing a localhost server application that relies on a web browser can result in serious system security violations ranging from loss of data control to damaging the user's file system.  As long as the application is written correctly, the web browser's policies will generally protect the user from malicious websites and users that attempt to access PHP App Server controlled content.

However, here are a few important, select security related items that all PHP App Server based software applications must actively defend against (in order of importance):

* [Sensitive data exposure](https://www.owasp.org/index.php/Top_10-2017_A3-Sensitive_Data_Exposure) - Use `$_SERVER["PAS_USER_FILES"]` or a user-defined location to store sensitive user data instead of `$_SERVER["DOCUMENT_ROOT_USER"]`.  Always ask the user what to do if they might consider something to be sensitive (e.g. asking could be as simple as displaying a checkbox to the user).  Privacy-centric individuals will generally speak their mind.
* [Cross-site request forgery attacks](https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)) - `$_SERVER["PAS_SECRET"]` combined with [CubicleSoft Admin Pack](https://github.com/cubiclesoft/admin-pack/) or other application frameworks help to handle this issue.  Server extensions also generally require an authentication token based on `$_SERVER["PAS_SECRET"]`.
* [Session fixation attacks](https://www.owasp.org/index.php/Session_fixation) - The [security token extension](extensions/1_security_token.php) that is included with PHP App Server automatically deals with this issue.
* [SQL injection attacks](https://www.owasp.org/index.php/SQL_Injection) - Relevant when using a database.  To avoid this, just don't run raw queries and use a good Database Access Layer (DAL) class like [CSDB](https://github.com/cubiclesoft/csdb/).

There are many other security considerations that are in the [OWASP Top 10 list](https://www.owasp.org/index.php/Category:OWASP_Top_Ten_Project) and the [OWASP attacks list](https://www.owasp.org/index.php/Category:Attack) to also keep in mind, but those are the big ones.

Long-Running Processes
----------------------

PHP App Server includes a powerful server extension and two SDKs to make starting, managing, and monitoring long-running processes easy and secure from both PHP and Javascript.  Started processes run as the user that PHP App Server is running as but aren't limited by timeouts or memory limits like regular CGI/FastCGI requests are.  Running processes can be actively monitored and even interacted with from the web browser via the included Javascript SDK.

Long-running scripts should ideally be stored in a 'scripts' subdirectory off the main PHP App Server 'support' directory.  That way they are away from the main web root but the application can still find them via `$_SERVER["PAS_ROOT"]`.

Here's an example of starting a PHP script called 'test.php' using the PHP SDK:

```php
<?php
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	// Load the PHP App Server common functions.
	require_once $_SERVER["PAS_ROOT"] . "/support/process_helper.php";
	require_once $_SERVER["PAS_ROOT"] . "/support/pas_functions.php";

	$cmd = escapeshellarg(PAS_GetPHPBinary());
	$cmd .= " " . escapeshellarg(realpath($_SERVER["PAS_ROOT"] . "/support/scripts/test.php"));

	$options = array();

	// Start the process.
	require_once $rootpath . "/support/pas_run_process_sdk.php";

	$rp = new PAS_RunProcessSDK();

	$result = $rp->StartProcess("demo", $cmd, $options);
	if (!$result["success"])  echo "An error occurred while starting a long-running process.";

	echo "Done.";
?>
```

Each process is given a tag, which allows multiple running processes to be grouped by tag.  In the example above, the tag is called "demo".  The Javacsript SDK can later be used to show only processes that use a specific tag:

```php
<?php
	header("Content-Type: text/html; charset=UTF8");
?>
<!DOCTYPE html>
<html>
<body>
<?php
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/support/pas_run_process_sdk.php";

	PAS_RunProcessSDK::OutputCSS();
	PAS_RunProcessSDK::OutputJS();
?>
<div id="terminal-manager"></div>

<script type="text/javascript">
// NOTE:  Always put Javascript RunProcesSDK and TerminalManager class instances in a Javascript closure like this one to limit the XSRF attack surface.
(function() {
	// Establish a new connection with a compatible WebSocket server.
	var runproc = new RunProcessSDK('<?=PAS_RunProcessSDK::GetURL()?>', false, '<?=PAS_RunProcessSDK::GetAuthToken()?>');

	// Debugging mode dumps incoming and outgoing packets to the web browser's debug console.
	runproc.debug = true;

	// Establish a new terminal manager instance.
	var elem = document.getElementById('terminal-manager');

	// Automatically attach to all channels with the 'demo' tag.
	var options = {
		tag: 'demo'
	};

	var tm = new TerminalManager(runproc, elem, options);
})();
</script>
</body>
</html>
```

The PHP SDK simplifies emitting the necessary CSS and Javscript dependencies into the HTML.  The above code demonstrates setting up a WebSocket connection to the PHP App Server extension and connecting it to a TerminalManager instance to monitor for processes with the "demo" tag.  TerminalManager is an included Javascript class that automatically creates and manages one or more ExecTerminals (also included) based on the input criteria.  In this case, TerminalManager will automatically attach to any process created with a "demo" tag.  An ExecTerminal looks like this:

![A screenshot of an ExecTerminal](https://user-images.githubusercontent.com/1432111/57195442-674c0400-6f07-11e9-9bcd-62269ca0de3c.png)

Each ExecTerminal wraps up a [XTerm.js Terminal](https://xtermjs.org/) instance with additional features:

* Status icons for process running/terminated, had output on stderr, and disconnected from WebSocket.
* Title that can be dynamically changed from the script via an ANSI escape sequence.
* Various buttons to attach and detach (not shown above), enter/exit fullscreen mode, forcefully terminate the process, and remove the ExecTerminal.
* Multiple input modes:  'interactive', 'interactive_echo' (rarely used), 'readline' (most common, keeps history), 'readline_secure', and 'none'.
* Intuitive terminal resizing.

And more.

Note that TerminalManager and ExecTerminal are not required for managing long-running processes but they do handle quite a few common scenarios.  The example code above only scratches the surface of what can be done.

Here is the full list of TerminalManager options:

* fullscreen - A boolean indicating whether or not an attached ExecTerminal starts in fullscreen mode (Default is false).
* autoattach - A boolean indicating whether or not to automatically attach to channels and create ExecTerminals (Default is true).
* manualdetach - A boolean indicating whether or not to display the manual attach/detach button on the title bar (Default is false).
* terminatebutton - A boolean indicating whether or not to display the button that allows the user to forcefully terminate the process (Default is true).
* autoremove - A boolean indicating whether or not to automatically remove terminated ExecTerminals (Default is false).  Can also be a string of 'keep_if_stderr', which removes the ExecTerminal if there was nothing output on stderr or keeps it if there was output on stderr.
* removebutton - A boolean indicating whether or not to display the button that allows the user to remove the ExecTerminal (Default is true).
* initviewportheight - A double containing the multiplier of the viewport height that ExecTerminals initialize at (Default is 0.5, which is 50% viewport height).
* historylines - An integer containing the number of lines of history to keep in 'readline' input mode (Default is 200).  That user input box is fairly powerful.
* terminaltheme - A Javascript object containing a XTerm.js Terminal-compatible theme (Default is { foreground: '#BBBBBB', cursor: '#00D600', cursorAccent: '#F0F0F0' }).
* oncreate - An optional callback function that is called whenever an ExecTerminal is created (Default is null).  The callback function must accept one parameter - callback(msg).
* onmessage - An optional callback function that is called whenever a message is received from the WebSocket for the TerminalManager instance (Default is null).  The callback function must accept one parameter - callback(msg).
* channel - A boolean of false or an integer containing a specific channel to attach to (Default is false).  Useful for monitoring exactly one running process.
* tag - A boolean of false or a string containing a tag name to watch for (Default is false).  Useful for monitoring many running processes at one time.
* langmap - An object containing translation strings (Default is an empty object).

The included [XTerm PHP class](https://github.com/cubiclesoft/php-app-server/blob/master/support/xterm.php) offers seamless and simplified control over the output from a long-running script to the XTerm-compatible ExecTerminal in the browser.  No need to remember ANSI escape codes.  Here's an example script:

```php
<?php
	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../xterm.php";

	for ($x = 0; $x < 5; $x++)
	{
		echo "Test:  " . ($x + 1) . "\n";

		sleep(1);
	}

	echo "That's boring.  Let's...";
	sleep(1);

	XTerm::SetItalic();
	echo "spice it up!\n";
	XTerm::SetItalic(false);
	sleep(1);

	$palette = XTerm::GetBlackOptimizedColorPalette();

	for ($x = 5; $x < 10; $x++)
	{
		$num = mt_rand(17, 231);
		XTerm::SetForegroundColor($palette[$num]);
		echo "Test:  " . ($x + 1) . " (Color " . $palette[$num] . ")\n";

		sleep(1);
	}
	XTerm::SetForegroundColor(false);

	XTerm::SetTitle("Changing the title...");
	usleep(250000);
	XTerm::SetTitle("Changing the title...like");
	usleep(250000);
	XTerm::SetTitle("Changing the title...like a");
	usleep(250000);
	XTerm::SetTitle("Changing the title...like a BOSS!");
	usleep(500000);

	echo "\n";
	echo "Enter some text:  ";
	$line = rtrim(fgets(STDIN));

	XTerm::SetBold();
	echo "Here's what you wrote:  " . $line . "\n\n";
	XTerm::SetBold(false);

	echo "[Switching to 'readline_secure' mode]\n\n";
	XTerm::SetCustomInputMode('readline_secure');

	echo "Enter some more text:  ";
	XTerm::SetColors(0, 0);
	$line = rtrim(fgets(STDIN));
	XTerm::SetColors(false, false);
	XTerm::SetCustomInputMode('readline');

	XTerm::SetBold();
	echo "Here's what you wrote:  " . $line . "\n\n";
	XTerm::SetBold(false);

	echo "Done.\n";
?>
```

Finally, if you use CubicleSoft [Admin Pack](https://github.com/cubiclesoft/admin-pack/) or [FlexForms](https://github.com/cubiclesoft/php-flexforms/) to build your application, the PHP SDK includes native FlexForms integration (i.e. no need to write Javascript/HTML):

```php
<?php
	// Admin Pack and FlexForms integration.
	require_once "support/pas_run_process_sdk.php";

	$contentopts = array(
		"desc" => "Showing all long-running processes with the 'demo' tag.",
		"fields" => array(
			array(
				"type" => "pas_run_process",
//				"debug" => true,
				"options" => array(
					"tag" => "demo"
				)
			)
		)
	);

	BB_GeneratePage("Process Demo", $menuopts, $contentopts);
?>
```

Creating Extensions
-------------------

Writing an extension requires a little bit of knowledge about how PHP App Server works:  Extensions are loaded early on during startup so they can get involved in the startup sequence if they need to (mostly just for security-related extensions).  Once the web server has started, every web request walks through the list of extensions and asks, "Can you handle this request?"  If an extension responds in the affirmative (i.e. returns true), then the rest of the request is passed off to the extension to handle.

Since extensions are run directly inline with the core server, they get a significant performance boost and can do things such as respond over WebSocket or start long-running processes that would normally be killed off after 30 seconds by the normal PHP path.

However, those benefits come with two major drawbacks.  The first is that if an extension raises an uncaught exception or otherwise crashes, it takes the whole web server with it.  The second is that making code changes to an extension requires restarting the web server to test the changes, which can be a bit of a hassle.  In general, the normal 'www' path is sufficient for most needs and extensions are for occasional segments of specialized logic.

The included [security token extension](extensions/1_security_token.php) is an excellent starting point for building an extension that can properly handle requests.  The security token extension is fairly short, well-commented, and works.

The server assumes that the filename is a part of the class name.  Whatever the PHP file is named, the class name within has to follow suit, otherwise PHP App Server will fail to load the extension.  Extension names should start with a number, which indicates the expected order in which to call the extension.

The variables available to normal PHP scripts are also available to extensions via the global `$baseenv` variable (e.g. `$baseenv["DOCUMENT_ROOT_USER"]` and `$baseenv["PAS_USER_FILES"]`).  Please do not alter the `$baseenv` values as that will negatively affect the rest of the application.

Always use the `ProcessHelper::StartProcess()` static function when starting external, long-running processes inside an extension.  The [ProcessHelper](https://github.com/cubiclesoft/php-misc/blob/master/docs/process_helper.md) class is designed to start non-blocking processes in the background across all platforms.  Note that the preferred way to start long-running processes is to use the long-running processes extension.

Pre-Installer Tasks
-------------------

Before running the various scripts that generate installer packages, various files need to be created, renamed, and/or modified.  Every file that starts with "yourapp" needs to be renamed to your application name, preferably restricted to all lowercase a-z and hyphens.  This is done so that updates to the software don't accidentally overwrite your work and so that any nosy users poking around the directory structure see the application's actual name instead of "yourapp".

* yourapp.png - A 512x512 pixel PNG image containing your application icon.  It should be fairly easy to tell what the icon represents when shrunk to 24x24 pixels.  The default icon works for testing but should be replaced with your own icon before deploying.
* yourapp.ico - A Windows .ico file containing your application icon at as many resolutions and sizes as possible.  The default icon works for testing but should be replaced with your own icon before deploying.
* yourapp.phpapp - This file needs to be modified.  More on this file in a moment.
* yourapp-license.txt - Replace the text within with an actual End User License Agreement (EULA) written and approved by a real lawyer.

The 'yourapp.phpapp' file is a PHP file that performs the actual application startup sequence of starting the web server (server.php) and then launching the user's web browser.  There is an `$options` array in the file that should be modified for your application's needs:

* business - A string containing your business or your name (Default is "CubicleSoft", which is probably not really what is desired).  Shown under some OSes when displaying a program listing - notably Linux.
* appname - A boolean of false or a string containing your application's name (Default is false, which attempts to automatically determine the app's name based on the directory it is installed in).  Shown under some OSes when displaying a program listing - notably Linux.
* home - An optional string containing the directory to use as the "home" directory.  Could be useful for implementing a "portable" version of the application.
* host - A string containing the IP address to bind to (Default is "127.0.0.1").  In general, don't change this.
* port - An integer containing the port number to bind to (Default is 0, which selects a random port number).  In general, don't change this.
* quitdelay - An integer specifying the number of minutes after the last client disconnects to quit running the server (Default is 6).  In general, don't change this.

The last three options are intended for highly specialized scenarios.  Changing 'host' to something like "127.0.1.1" might be okay but don't use "0.0.0.0" or "::0", which binds the server publicly to the network interface.  Binding to a specific 'port' number might seem like a good idea until users start complaining about error messages when they try to restart the application.

The 'quitdelay' option is interesting.  The server portion of PHP App Server will stick around until 'quitdelay' minutes after the last client disconnects.  The application should send a "heartbeat" request every five minutes to guarantee that the web server won't terminate itself before the user is finished using the application.

Installer Packaging
-------------------

Each platform packaging tool has its own instructions:

* [Windows, EXE](installers/win-innosetup/README.md) - Inno Setup based installer script with specialized support for the MSI build process.
* [Windows, MSI](installers/win-wix/README.md) - WiX Toolset based installer script.  Build the EXE with Inno Setup first.
* [Mac OSX, .tar.gz](installers/osx-tar-gz/README.md) - Rearranges the application into an .app format and then wraps the entire application in a custom installer .app and finally puts the whole mess into a .tar.gz file.  Think Very Different.  This approach also doesn't require owning a Mac, which is kind of cool because not everyone can afford the expensive hardware.
* [Linux, .tar.gz](installers/nix-tar-gz/README.md) - Produces the smallest output file out of all of the application packagers.  The installer relies on the system package manager to install PHP and other dependencies on Debian, RedHat, and Arch-based systems - that is, there is fairly broad distro coverage.  The installer itself requires a Freedesktop.org-compliant window manager that supports `xdg-utils` (Gnome, KDE, XFCE, etc. are all fine).

There are some known packaging issues:

* Code signing support is missing - I don't like code signing and neither should anyone else until we can all use DNSSEC DANE TLSA Certificate usage 3 with Authenticode/Gatekeeper/etc.  (Hint to Microsoft/Apple:  Publisher = domain name).  Feel free to open a pull request if you implement really good support for optional code signing in the various packagers.  I'm not particularly interested in code signing given how pointless, fairly expensive, and obnoxious it tends to be.  Note that on Mac OSX, a user has to hold Ctrl while clicking, then select Open from the menu, and finally open the app when Gatekeeper complains about the lack of a digital signature.
* The Mac OSX installer has a continually bouncing icon while installing the software.  Since the installer relies on the user's web browser, clicking the icon does nothing.

The installers and the server software have some interesting tales behind them.  Maybe I'll share those stories one day.  For now, enjoy building your next application in PHP App Server!
