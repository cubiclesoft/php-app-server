Windows MSI Instructions
========================

This directory contains a WiX script (.wxs), a GUID generator, and a couple of batch files for preparing MSI-based installer packages for Windows.

Note that Windows is required for these instructions.  Fortunately, Microsoft provides free, temporary [virtual machines of the Windows OS](https://developer.microsoft.com/en-us/windows/downloads/virtual-machines) for a variety of virtual machine software products, including VirtualBox so that you can run whatever OS you prefer.

The WiX script depends on an Inno Setup installer executable to exist in the default `..\win-innosetup\Output` directory.  If you haven't generated the Inno Setup installer yet, go do that first.  See the notes section below.

First, download and install the latest [WiX toolset](http://wixtoolset.org/) software on a Windows computer.

Also generate a GUID using the following command line:

`uuidgen.exe -c`

Next, rename the `yourapp.wxs` file to your application name.  This helps prevent accidental overwrites when upgrading the software in the future.

Edit the `yourapp.wxs` file in your text editor.  Adjust the `#define` lines and use the uppercase GUID that was generated a moment ago for the value for `UpgradeGUID`.  `UpgradeGUID` should only ever be set up one time as it is how Windows Installer determines if a product is installed or not and the string must be all uppercase.

Once the defines have been adjusted, run the `build.bat` or `build_64.bat` batch file.  `build_64.bat` should be used if the Inno Setup executable is 64-bit only.  If all goes well, a deployable MSI file will be generated.  Note that the `WIX` environment variable must point at the WiX toolset directory or the batch file will be unable to locate WiX on the system.

Notes
-----

Basically, this WiX script wraps the Inno Setup EXE with a set of MSI Custom Actions designed to handle installation, upgrading, downgrading, and removal of the software.  The resulting MSI files are a little odd but are generally compliant with most common enterprise GPO and SCCM deployment paths (e.g. `msiexec` silent remote installation support).

The [Inno Setup installer script](../win-innosetup/yourapp.iss) includes a custom /MSI command line option for better integration with the WiX script found here.  The techniques found there can be reused in other Inno Setup scripts that primarily dump files onto a system and create a few shortcuts.
