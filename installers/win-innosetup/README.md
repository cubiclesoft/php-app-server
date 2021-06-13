Windows EXE Instructions
========================

This directory contains an Inno Setup script (.iss) and tools for preparing EXE-based installer packages for Windows.

Note that Windows is required for most of these instructions.  Fortunately, Microsoft provides free, temporary [virtual machines of the Windows OS](https://developer.microsoft.com/en-us/windows/downloads/virtual-machines) for a variety of virtual machine software products, including [VirtualBox](https://www.virtualbox.org/wiki/Downloads), which means you can develop on whatever host OS you prefer and still build software for Windows.

First, for the generated installer to work properly on target systems, put 32-bit (x86) and/or 64-bit (x64) PHP binaries for Windows into the appropriate `php-win-XX` directory.  Also, create or adjust `php.ini` for the application's needs (e.g. uncomment the line `extension_dir = "ext"` and then whatever extensions are used by the application).  In general, don't use the "Non Thread Safe" (NTS) versions available on [windows.php.net](https://windows.php.net/download/).  While not necessarily required, trimming unnecessary DLLs can dramatically reduce the final package size.

Next, run `prepare-php.bat`.  This step prepares the PHP binaries from the previous step to be deployed onto Windows systems.  Alternatively, the `support/prepare.php` script can be run via PHP on your host OS to accomplish the same objective.  The preparation script only needs to be run one time when upgrading PHP binaries or if the application icon is changed.

By default, the preparation script automatically converts the PNG icon (e.g. yourapp.png) into a ICO file (e.g. yourapp.ico) if it doesn't exist yet.  Custom, hand-optimized/tweaked ICO files are generally of higher quality than automation can produce because 16x16 is fairly tiny.  If you wish to manually create a custom .ico file at various sizes, the free [IcoFX Portable](https://portableapps.com/apps/graphics_pictures/icofx_portable) application produces excellent results.  The application icon is integrated into the various PHP executables for easier identification in Task Manager.

Next, download and install the latest [Inno Setup](http://www.jrsoftware.org/isinfo.php) software.  Be sure to install the ISPP extensions when asked.

Next, rename the `yourapp.iss` file to match your application name.  This helps prevent accidental overwrites when upgrading the software in the future.

Double-click the renamed .iss file to open it in the Inno Setup editor.  Adjust the `#define` lines.  The Inno Setup editor itself is a little awkward to use but has lots of features specific to Inno Setup, including the ability to debug the generated installer itself by setting various breakpoints.

One of the `#define` lines is "AppMutex".  This is a special string that is used to determine if the web server is running and prevents the installer from proceeding until the application is no longer running.  The string should be "Business name_App name" from the root `.phpapp` file.  Commas must be escaped with a backslash (`\`) and there must be an underscore (`_`) between the business name and app name.  Getting PHP App Server to exit so that PHP can be upgraded on Windows is a tad tricky.  See the main documentation for more details.

Next, run the compiler (Build -> Compiler).  Errors emitted by the compiler will generally be missing files that are required by the script.  Create each missing file until the compilation succeeds.  A license file (e.g. EULA) and an app icon in Windows .ico format are required (the .ico file should have already been generated earlier in the preparation step).

Finally, don't forget to test the installation to verify that it works as expected.  Specifically, be sure to test it in an environment that does not have PHP installed.
