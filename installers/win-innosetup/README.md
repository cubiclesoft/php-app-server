Windows EXE Instructions
========================

This directory contains an Inno Setup script (.iss) and tools for preparing EXE-based installer packages for Windows.

Note that Windows is required for these instructions.  Fortunately, Microsoft provides free, temporary [virtual machines of the Windows OS](https://developer.microsoft.com/en-us/windows/downloads/virtual-machines) for a variety of virtual machine software products, including VirtualBox so that you can run whatever OS you prefer.

First, for the generated installer to work properly on target systems, put 32-bit (x86) and/or 64-bit (x64) PHP binaries into the appropriate `php-win-XX` directory.  Also, create or adjust `php.ini` for the application's needs (e.g. uncomment the line `extension_dir = "ext"` and then whatever extensions are used by the application).  In general, don't use the "Non Thread Safe" versions available on [windows.php.net](https://windows.php.net/download/).  While not necessarily required, trimming unnecessary DLLs can dramatically reduce the final package size.

Next, run `prepare-php.bat` on a Windows computer.  This step prepares the PHP binaries from the previous step to be deployed onto Windows systems.

Next, download and install the latest [Inno Setup](http://www.jrsoftware.org/isinfo.php) software.  Be sure to install the ISPP extensions when asked.

Next, rename the `yourapp.iss` file to match your application name.  This helps prevent accidental overwrites when upgrading the software in the future.

Double-click the renamed .iss file to open it in the Inno Setup editor.  Adjust the `#define` lines.  The Inno Setup editor itself is a little awkward to use but has lots of features specific to Inno Setup, including the ability to debug the generated installer itself by setting various breakpoints.

Finally, run the compiler (Build -> Compiler).  Errors emitted by the compiler will generally be missing files that are required by the script.  Create each missing file until the compilation succeeds.  A license file (e.g. EULA) and an app icon in Windows .ico format are required.

Don't forget to test the installation to verify that it works as expected.
