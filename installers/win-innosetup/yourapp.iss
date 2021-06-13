; Basic Inno Setup script.
; (C) 2021 CubicleSoft.  All Rights Reserved.

; Adjust these defines for your app.
#define BusinessName "Your Business or Name"
#define BusinessURL "http://www.yourwebsite.com/"
#define AppName "Your App"
#define AppMutex "Your Business or Name_Your App"
#define AppFilename "Your-App"
#define AppVer "1.0"
#define AppURL "http://www.yourwebsite.com/product/"
#define AppCopyright "(C) 2021 Your Business or Name"
#define SupportURL "http://www.yourwebsite.com/contact/"
#define AppBase "yourapp"

; Check http://windows.php.net/ for your PHP version to determine which of the following Visual C++ Runtime lines to uncomment.

; Visual C++ 2012 (VC12) Runtimes.
; #define VCDetect "msvcr110.dll"
; #define VCx86URL "https://download.microsoft.com/download/1/6/B/16B06F60-3B20-4FF2-B699-5E9B7962F9AE/VSU_4/vcredist_x86.exe"
; #define VCx64URL "https://download.microsoft.com/download/1/6/B/16B06F60-3B20-4FF2-B699-5E9B7962F9AE/VSU_4/vcredist_x64.exe"

; Visual C++ 2015, 2017, and 2019 (VC14, VC15, VC16) Runtimes.
#define VCDetect "vcruntime140.dll"
#define VCx86URL "https://aka.ms/vs/16/release/VC_redist.x86.exe"
#define VCx64URL "https://aka.ms/vs/16/release/VC_redist.x64.exe"

; Uncomment the next line to allow the application to be installed in a "Portable Apps" fashion (beta).
; #define PortableAppMode

; There shouldn't be any need to modify the rest of this file.
; Note that not including any PHP binaries may result in a lot of unhappy users.
#ifexist "php-win-32\php-win-elevated.exe"
#define PHP_32
#endif
#ifexist "php-win-64\php-win-elevated.exe"
#define PHP_64
#endif

[Setup]
AppName={#AppName}
AppVersion={#AppVer}
AppPublisher={#BusinessName}
AppPublisherURL={#BusinessURL}
AppUpdatesURL={#AppURL}
AppSupportURL={#SupportURL}
AppCopyright={#AppCopyright}
AppMutex={#AppMutex}
SourceDir=..\..
#ifdef PHP_64
ArchitecturesInstallIn64BitMode=x64
  #ifndef PHP_32
ArchitecturesAllowed=x64
  #endif
#endif
CreateUninstallRegKey=not IsMSI
DefaultDirName={pf}\{#BusinessName}\{#AppName}
DefaultGroupName={#AppName}
LicenseFile={#AppBase}-license.txt
; Minimum version supported by PHP is Windows XP.
MinVersion=5.1
OutputBaseFilename={#AppFilename}-{#AppVer}
OutputDir=installers\win-innosetup\Output
PrivilegesRequired=admin
#ifexist "setup.ico"
SetupIconFile=setup.ico
UninstallDisplayIcon={uninstallexe}
#else
UninstallDisplayIcon={app}\{#AppBase}.ico
#endif
#ifdef PortableAppMode
Uninstallable=not IsTaskSelected('portablemode')
#endif

[Code]
// Determine whether or not the installer is being called from the WiX wrapper.
function IsMSI(): Boolean;
var
  x: Integer;
begin
  Result := False;

  for x := 1 to ParamCount do
  begin
    if CompareText(Copy(ParamStr(x), 1, 5), '/MSI=') = 0 then
    begin
      Result := True;
    end;
  end;
end;

// Sets a static registry key as per the input.  Lets the MSI wrapper do its thing later.
procedure PrepareMSIUninstall();
var
  x: Integer;
  subkey: String;
begin
  for x := 1 to ParamCount do
  begin
    if CompareText(Copy(ParamStr(x), 1, 5), '/MSI=') = 0 then
    begin
      subkey := 'SOFTWARE\Inno Setup MSIs\' + Copy(ParamStr(x), 6, Length(ParamStr(x)) - 5);
      RegDeleteKeyIncludingSubkeys(HKLM, subkey);
      RegWriteStringValue(HKLM, subkey, '', ExpandConstant('{uninstallexe}'));
    end;
  end;
end;

function InitializeSetup() : Boolean;
var
  MsgResult : Integer;
  ErrCode: Integer;
begin
  // Check for the correct VC++ Redistributables.
  if ((NOT Is64BitInstallMode) AND (NOT FileExists(ExpandConstant('{syswow64}') + '\{#VCDetect}'))) then begin
    MsgResult := SuppressibleMsgBox('The {#AppName} {#AppVer} setup has detected that the following critical component is missing:'#10'Microsoft Visual C++ Redistributables (32-bit)'#10#10'{#AppName} will not function properly without this component.'#10'Download the required redistributables from Microsoft now?', mbError, MB_YESNOCANCEL, IDNO);
    if (MsgResult = IDCANCEL) then exit;

    if (MsgResult = IDYES) then begin
      ShellExecAsOriginalUser('open', '{#VCx86URL}', '', '', SW_SHOW, ewNoWait, ErrCode);
    end;
  end;

  if (Is64BitInstallMode AND (NOT FileExists(ExpandConstant('{sys}') + '\{#VCDetect}'))) then begin
    MsgResult := SuppressibleMsgBox('The {#AppName} {#AppVer} setup has detected that the following critical component is missing:'#10'Microsoft Visual C++ Redistributables (64-bit)'#10#10'{#AppName} will not function properly without this component.'#10'Download the required redistributables from Microsoft now?', mbError, MB_YESNOCANCEL, IDNO);
    if (MsgResult = IDCANCEL) then exit;

    if (MsgResult = IDYES) then begin
      ShellExecAsOriginalUser('open', '{#VCx64URL}', '', '', SW_SHOW, ewNoWait, ErrCode);
    end;
  end;

  Result := True;
end;

function HasRedistributables() : Boolean;
begin
  Result := True;

  if ((NOT Is64BitInstallMode) AND (NOT FileExists(ExpandConstant('{syswow64}') + '\{#VCDetect}'))) then begin
    Result := False;
  end;

  if (Is64BitInstallMode AND (NOT FileExists(ExpandConstant('{sys}') + '\{#VCDetect}'))) then begin
    Result := False;
  end;
end;

[Tasks]
Name: desktopicon; Description: "Create a &desktop icon"
#ifdef PortableAppMode
Name: portablemode; Description: "&Portable mode"; Flags: unchecked
#endif

[InstallDelete]
; Be sure to read the documentation on why this is part of the installation/upgrade process.
Type: filesandordirs; Name: "{app}\extensions"
Type: filesandordirs; Name: "{app}\www"

[Dirs]
Name: "{app}\extensions"
Name: "{app}\www"

[Files]
Source: "{#AppBase}.phpapp"; DestDir: "{app}"
Source: "{#AppBase}.ico"; DestDir: "{app}"
Source: "server.php"; DestDir: "{app}"
Source: "support\*"; Excludes: "mac\,nix\"; DestDir: "{app}\support"; Flags: createallsubdirs recursesubdirs
Source: "extensions\*"; Excludes: "README.md"; DestDir: "{app}\extensions"; Flags: createallsubdirs recursesubdirs skipifsourcedoesntexist
Source: "www\*"; Excludes: "README.md"; DestDir: "{app}\www"; Flags: createallsubdirs recursesubdirs
#ifdef PHP_32
Source: "installers\win-innosetup\php-win-32\*"; Excludes: "README.md,*.bak"; DestDir: "{app}\php-32"; Check: (not Is64BitInstallMode) or IsTaskSelected('portablemode'); Flags: createallsubdirs recursesubdirs
#endif
#ifdef PHP_64
Source: "installers\win-innosetup\php-win-64\*"; Excludes: "README.md,*.bak"; DestDir: "{app}\php-64"; Check: Is64BitInstallMode or IsTaskSelected('portablemode'); Flags: createallsubdirs recursesubdirs
#endif
#ifdef PortableAppMode
Source: "installers\win-innosetup\start.bat"; DestDir: "{app}"; Check: IsTaskSelected('portablemode')
#endif

[Icons]
#ifdef PHP_32
Name: "{commondesktop}\{#AppName}"; Filename: "{app}\php-32\php-win.exe"; Parameters: """{app}\{#AppBase}.phpapp"""; WorkingDir: "{app}"; IconFilename: "{app}\{#AppBase}.ico"; Check: not Is64BitInstallMode; Tasks: desktopicon
Name: "{group}\{#AppName}"; Filename: "{app}\php-32\php-win.exe"; Parameters: """{app}\{#AppBase}.phpapp"""; WorkingDir: "{app}"; IconFilename: "{app}\{#AppBase}.ico"; Check: (not Is64BitInstallMode) and not IsTaskSelected('portablemode')
#endif
#ifdef PHP_64
Name: "{commondesktop}\{#AppName}"; Filename: "{app}\php-64\php-win.exe"; Parameters: """{app}\{#AppBase}.phpapp"""; WorkingDir: "{app}"; IconFilename: "{app}\{#AppBase}.ico"; Check: Is64BitInstallMode; Tasks: desktopicon
Name: "{group}\{#AppName}"; Filename: "{app}\php-64\php-win.exe"; Parameters: """{app}\{#AppBase}.phpapp"""; WorkingDir: "{app}"; IconFilename: "{app}\{#AppBase}.ico"; Check: Is64BitInstallMode and not IsTaskSelected('portablemode')
#endif
Name: "{group}\Uninstall {#AppName}"; Filename: "{uninstallexe}"; Check: (not IsTaskSelected('portablemode')) and not IsMSI

[Registry]
Root: HKLM; Subkey: "SOFTWARE\Inno Setup MSIs"; Check: IsMSI; AfterInstall: PrepareMSIUninstall

[Run]
#ifdef PHP_32
Filename: "{app}\php-32\php-win.exe"; Parameters: """{app}\support\post-install.php"""
Filename: "{app}\php-32\php-win.exe"; Parameters: """{app}\{#AppBase}.phpapp"""; Description: "Launch application"; Check: (not Is64BitInstallMode) and HasRedistributables; Flags: postinstall nowait skipifsilent
#endif
#ifdef PHP_64
Filename: "{app}\php-64\php-win.exe"; Parameters: """{app}\support\post-install.php"""
Filename: "{app}\php-64\php-win.exe"; Parameters: """{app}\{#AppBase}.phpapp"""; Description: "Launch application"; Check: Is64BitInstallMode and HasRedistributables; Flags: postinstall nowait skipifsilent
#endif

[UninstallRun]
#ifdef PHP_32
Filename: "{app}\php-32\php-win.exe"; Parameters: """{app}\support\pre-uninstall.php"""
#endif
#ifdef PHP_64
Filename: "{app}\php-64\php-win.exe"; Parameters: """{app}\support\pre-uninstall.php"""
#endif
