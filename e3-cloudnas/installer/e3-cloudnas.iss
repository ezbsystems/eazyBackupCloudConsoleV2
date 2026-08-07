#ifndef AppVersion
  #define AppVersion "1.0.0"
#endif

[Setup]
AppId={{6AE903FC-EF29-46C4-8AC8-85E84AE6D5ED}}
AppName=E3 Cloud NAS
AppVersion={#AppVersion}
AppPublisher=EazyBackup
DefaultDirName={autopf}\E3Backup\CloudNAS
DisableProgramGroupPage=yes
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
Compression=lzma2
SolidCompression=yes
PrivilegesRequired=admin
OutputBaseFilename=e3-cloudnas-setup
WizardStyle=modern
CloseApplications=force
CloseApplicationsFilter=e3-cloudnas.exe

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "..\bin\e3-cloudnas.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\LICENSE"; DestDir: "{app}"; DestName: "LICENSE.txt"; Flags: ignoreversion
Source: "redist\winfsp.msi"; DestDir: "{tmp}"; Flags: deleteafterinstall

[Registry]
; Run in the interactive user's session so WinFsp drive mappings are visible
; to Explorer. Starting it from this elevated installer would use the wrong token.
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "E3CloudNAS"; ValueData: """{app}\e3-cloudnas.exe"""; Flags: uninsdeletevalue

[Run]
; Inno logs the MSI exit code and continues. This makes an already-installed
; WinFsp runtime non-fatal; /norestart leaves reboot control with the operator.
Filename: "{sys}\msiexec.exe"; Parameters: "/i ""{tmp}\winfsp.msi"" /qn /norestart ADDLOCAL=Core"; Flags: runhidden waituntilterminated; StatusMsg: "Installing WinFsp Core..."

[UninstallRun]
Filename: "{cmd}"; Parameters: "/c taskkill /F /IM e3-cloudnas.exe >nul 2>&1"; Flags: runhidden; RunOnceId: "StopCloudNAS"
