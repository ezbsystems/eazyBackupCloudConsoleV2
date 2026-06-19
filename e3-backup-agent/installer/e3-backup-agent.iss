#ifndef AppVersion
  #define AppVersion "1.0.0"
#endif
#ifndef AssetsDir
  #define AssetsDir "..\..\e3-cloudbackup-worker\assets"
#endif

[Setup]
AppName=E3 Backup Agent
AppVersion={#AppVersion}
AppPublisher=EazyBackup
DefaultDirName={autopf}\E3Backup
DefaultGroupName=E3 Backup Agent
DisableProgramGroupPage=yes
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
Compression=lzma2
SolidCompression=yes
PrivilegesRequired=admin
OutputBaseFilename=e3-backup-agent-setup
WizardStyle=modern
; EazyBackup branding. These are embedded into Setup.exe at compile time, so the
; files must be staged into the remote assets dir (see WindowsStage.php).
;  - SetupIconFile: the Setup.exe / title-bar / Add-Remove-Programs icon
;    (replaces Inno's default download-arrow icon).
;  - WizardImageFile: large welcome/finish panel (164x314 logical; 2x source).
;  - WizardSmallImageFile: small top-right header image on inner pages
;    (replaces Inno's default software-box image).
SetupIconFile={#AssetsDir}\tray_logo.ico
WizardImageFile={#AssetsDir}\wizard_large.bmp
WizardSmallImageFile={#AssetsDir}\wizard_small.bmp
CloseApplications=force
CloseApplicationsFilter=e3-backup-agent.exe,e3-backup-tray.exe

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "autorun_tray"; Description: "Run tray helper at login (recommended)"; Flags: checkedonce
Name: "start_tray_now"; Description: "Start tray helper after install"; Flags: checkedonce
Name: "desktopicon"; Description: "Create a desktop shortcut"; Flags: checkedonce

[Dirs]
; Ensure standard users can update config/logs/runs without elevation
Name: "{commonappdata}\E3Backup"; Permissions: users-modify
Name: "{commonappdata}\E3Backup\logs"; Permissions: users-modify
Name: "{commonappdata}\E3Backup\runs"; Permissions: users-modify

[Files]
; NOTE: build these two binaries before compiling the installer:
;  - ..\bin\e3-backup-agent.exe   (service)
;  - ..\bin\e3-backup-tray.exe    (tray)
Source: "..\bin\e3-backup-agent.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\bin\e3-backup-tray.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\THIRD_PARTY_LICENSES.txt"; DestDir: "{app}"; Flags: ignoreversion

; Tray icon assets (installer places PNG next to tray exe; tray will wrap PNG->ICO at runtime)
Source: "{#AssetsDir}\tray_logo-drk-orange120x120.png"; DestDir: "{app}"; DestName: "tray_logo-drk-orange120x120.png"; Flags: ignoreversion
Source: "{#AssetsDir}\tray_logo-drk-orange.svg"; DestDir: "{app}"; DestName: "tray_logo-drk-orange.svg"; Flags: ignoreversion
; Prebuilt .ico used for Start Menu / desktop shortcuts (and preferred by the
; tray's runtime icon loader over the PNG).
Source: "{#AssetsDir}\tray_logo.ico"; DestDir: "{app}"; DestName: "tray_logo.ico"; Flags: ignoreversion

[Icons]
; Start Menu folder (DefaultGroupName) with a launcher for the tray, which is
; the user-facing face of the agent. The background service has no UI of its own.
Name: "{group}\E3 Backup Agent"; Filename: "{app}\e3-backup-tray.exe"; Parameters: "-config ""{commonappdata}\E3Backup\agent.conf"""; IconFilename: "{app}\tray_logo.ico"; Comment: "Open the E3 Backup agent"
Name: "{group}\Uninstall E3 Backup Agent"; Filename: "{uninstallexe}"
; Optional desktop shortcut.
Name: "{autodesktop}\E3 Backup Agent"; Filename: "{app}\e3-backup-tray.exe"; Parameters: "-config ""{commonappdata}\E3Backup\agent.conf"""; IconFilename: "{app}\tray_logo.ico"; Tasks: desktopicon

[Registry]
; Auto-run tray helper in the current user's session (non-elevated).
; HKCU ensures the tray starts with the user's filtered (non-elevated) token,
; which is required so that drive mappings (WNetAddConnection2) land in the
; same logon session as explorer.exe and appear in "This PC".
; HKLM\...\Run would work for all users but the process may inherit an
; elevated token on UAC-enabled systems, causing drive mappings to be
; invisible in the non-elevated Explorer shell.
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "E3BackupTray"; ValueData: """{app}\e3-backup-tray.exe"" -config ""{commonappdata}\E3Backup\agent.conf"""; Tasks: autorun_tray; Flags: uninsdeletevalue

[Run]
; Write initial config to ProgramData\E3Backup\agent.conf
Filename: "{cmd}"; Parameters: "/c ""{code:WriteInitialConfig}"""; Flags: runhidden

; Install the Windows service (idempotent: non-fatal if already installed on upgrade).
Filename: "{app}\e3-backup-agent.exe"; Parameters: "-service install -config ""{commonappdata}\E3Backup\agent.conf"""; Flags: runhidden
; Restart (stop+start) rather than a bare start. On upgrades the service may
; still be registered as running from the SCM's perspective, so a plain start is
; a no-op and the new binary never loads. "restart" stops it (best-effort) and
; reliably starts it again.
Filename: "{app}\e3-backup-agent.exe"; Parameters: "-service restart -config ""{commonappdata}\E3Backup\agent.conf"""; Flags: runhidden

; Phase 2F (beta hardening): explicit Windows Firewall rules. The agent only
; makes outbound calls (S3 + API), but the recovery-agent sibling binary
; listens on loopback :8088 for the attended-recovery flow. Add idempotent
; allow rules so the eventual recovery flow doesn't get blocked silently.
Filename: "{cmd}"; Parameters: "/c netsh advfirewall firewall delete rule name=""E3 Backup Agent (outbound)"" >nul 2>&1"; Flags: runhidden
Filename: "{cmd}"; Parameters: "/c netsh advfirewall firewall add rule name=""E3 Backup Agent (outbound)"" dir=out action=allow program=""{app}\e3-backup-agent.exe"" enable=yes >nul 2>&1"; Flags: runhidden
Filename: "{cmd}"; Parameters: "/c netsh advfirewall firewall delete rule name=""E3 Recovery Agent (loopback 8088)"" >nul 2>&1"; Flags: runhidden
Filename: "{cmd}"; Parameters: "/c netsh advfirewall firewall add rule name=""E3 Recovery Agent (loopback 8088)"" dir=in action=allow protocol=TCP localport=8088 localip=127.0.0.1 enable=yes >nul 2>&1"; Flags: runhidden

; Start tray helper after install (optional)
Filename: "{app}\e3-backup-tray.exe"; Parameters: "-config ""{commonappdata}\E3Backup\agent.conf"""; Tasks: start_tray_now; Flags: nowait postinstall skipifsilent

[UninstallRun]
; Stop tray helper (may lock files in Program Files)
Filename: "{cmd}"; Parameters: "/c taskkill /F /IM e3-backup-tray.exe >nul 2>&1"; Flags: runhidden; RunOnceId: "StopTray"
; Stop/uninstall service on uninstall
Filename: "{app}\e3-backup-agent.exe"; Parameters: "-service stop -config ""{commonappdata}\E3Backup\agent.conf"""; Flags: runhidden; RunOnceId: "StopService"
Filename: "{app}\e3-backup-agent.exe"; Parameters: "-service uninstall -config ""{commonappdata}\E3Backup\agent.conf"""; Flags: runhidden; RunOnceId: "UninstallService"
; Phase 2F: remove the firewall rules we created on install (no-op if missing).
Filename: "{cmd}"; Parameters: "/c netsh advfirewall firewall delete rule name=""E3 Backup Agent (outbound)"" >nul 2>&1"; Flags: runhidden; RunOnceId: "FwDelOutbound"
Filename: "{cmd}"; Parameters: "/c netsh advfirewall firewall delete rule name=""E3 Recovery Agent (loopback 8088)"" >nul 2>&1"; Flags: runhidden; RunOnceId: "FwDelRecovery"

[UninstallDelete]
; Remove ProgramData folder and any leftover app files
Type: filesandordirs; Name: "{commonappdata}\E3Backup"
Type: filesandordirs; Name: "{app}"

[Code]
var
  EnvPage: TWizardPage;
  UseDevCheckbox: TNewCheckBox;

// PrepareToInstall runs before any files are copied. It stops the running
// service and tray so the installer can replace the locked EXE files.
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  RC: Integer;
begin
  Result := '';
  NeedsRestart := False;

  // Kill the tray helper (user-mode process that locks the EXE)
  Exec(ExpandConstant('{cmd}'), '/c taskkill /F /IM e3-backup-tray.exe >nul 2>&1',
       '', SW_HIDE, ewWaitUntilTerminated, RC);

  // Stop the Windows service via sc.exe (works even if the agent binary is being replaced)
  Exec(ExpandConstant('{cmd}'), '/c sc stop e3-backup-agent >nul 2>&1',
       '', SW_HIDE, ewWaitUntilTerminated, RC);

  // Wait for the service process to fully exit and release file handles
  Sleep(3000);

  // If the EXE is still locked, try harder: kill the process directly
  Exec(ExpandConstant('{cmd}'), '/c taskkill /F /IM e3-backup-agent.exe >nul 2>&1',
       '', SW_HIDE, ewWaitUntilTerminated, RC);

  Sleep(1000);
end;

function GetDefaultApiBase: string;
begin
  Result := 'https://accounts.eazybackup.ca/modules/addons/cloudstorage/api';
end;

function GetDevApiBase: string;
begin
  Result := 'https://dev.eazybackup.ca/modules/addons/cloudstorage/api';
end;

function GetSelectedApiBase: string;
begin
  if Assigned(UseDevCheckbox) and UseDevCheckbox.Checked then
    Result := GetDevApiBase
  else
    Result := GetDefaultApiBase;
end;

procedure InitializeWizard;
var
  HelpText: TNewStaticText;
begin
  // NOTE on text scaling (125% / 150%): TNewStaticText supports AutoSize +
  // WordWrap and must use them so wrapped captions are not clipped. However
  // TNewCheckBox does NOT expose a WordWrap property in Inno Setup's Pascal
  // Script (setting it aborts the compile with "Unknown identifier
  // 'WordWrap'"), so its caption must stay short enough to fit on one line.
  // Keep the long explanation in the TNewStaticText help line below. Always
  // anchor right-side so controls stretch with the wizard.
  EnvPage := CreateCustomPage(
    wpSelectTasks,
    'Server Environment',
    'Choose which server the agent should enroll with.'
  );

  UseDevCheckbox := TNewCheckBox.Create(EnvPage);
  UseDevCheckbox.Parent := EnvPage.Surface;
  UseDevCheckbox.Caption := 'Use development server';
  UseDevCheckbox.Checked := False;
  UseDevCheckbox.Left := ScaleX(0);
  UseDevCheckbox.Top := ScaleY(8);
  UseDevCheckbox.Width := EnvPage.SurfaceWidth;
  UseDevCheckbox.Height := ScaleY(24);
  UseDevCheckbox.Anchors := [akLeft, akTop, akRight];

  HelpText := TNewStaticText.Create(EnvPage);
  HelpText.Parent := EnvPage.Surface;
  HelpText.Caption :=
    'Leave this unchecked for all production installs.' + #13#10 +
    'Only check this if EazyBackup support has asked you to enroll against ' +
    'our development server (dev.eazybackup.ca) for testing.';
  HelpText.Left := ScaleX(0);
  HelpText.Top := UseDevCheckbox.Top + UseDevCheckbox.Height + ScaleY(8);
  HelpText.Width := EnvPage.SurfaceWidth;
  HelpText.AutoSize := False;
  HelpText.Height := ScaleY(60);
  HelpText.WordWrap := True;
  HelpText.Anchors := [akLeft, akTop, akRight];
end;

function GetParamValue(const ParamName: string): string;
var
  I: Integer;
  P: string;
begin
  Result := '';
  for I := 1 to ParamCount do begin
    P := ParamStr(I);
    // /TOKEN=abc or /API=https://...
    if CompareText(Copy(P, 1, Length(ParamName)+2), '/' + ParamName + '=') = 0 then begin
      Result := Copy(P, Length(ParamName)+3, MaxInt);
      exit;
    end;
  end;
end;

function QuoteYaml(const S: string): string;
var
  T: string;
begin
  // YAML double-quoted strings process escape sequences, so we must escape backslashes first.
  T := S;
  StringChangeEx(T, '\', '\\', True);  // Escape backslashes first
  StringChangeEx(T, '"', '\"', True);  // Then escape double quotes
  Result := '"' + T + '"';
end;

function NewUUIDv4(const Salt: string): string;
var
  h: string;
begin
  // Inno Setup versions differ in GUID helpers; generate a UUID-like value using MD5.
  // This is sufficient for stable device_id/install_id identifiers.
  h := Lowercase(GetMD5OfString(
    ExpandConstant('{computername}') + '|' +
    GetDateTimeString('yyyymmddhhnnss', '-', ':') + '|' +
    Salt
  ));
  // Force v4 + variant bits in the textual representation.
  // Positions are 1-based in PascalScript strings.
  if Length(h) >= 32 then begin
    h[13] := '4'; // version
    h[17] := '8'; // variant
  end;
  Result :=
    Copy(h, 1, 8) + '-' +
    Copy(h, 9, 4) + '-' +
    Copy(h, 13, 4) + '-' +
    Copy(h, 17, 4) + '-' +
    Copy(h, 21, 12);
end;

function GetYamlValue(const Text, Key: string): string;
var
  P, NL: Integer;
  Line: string;
begin
  Result := '';
  P := Pos(Key + ':', Text);
  if P = 0 then exit;
  // Find end of line
  NL := Pos(#10, Copy(Text, P, MaxInt));
  if NL = 0 then
    Line := Copy(Text, P, MaxInt)
  else
    Line := Copy(Text, P, NL - 1);
  // Strip "key:" prefix
  Line := Copy(Line, Length(Key) + 2, MaxInt);
  Line := Trim(Line);
  // Remove quotes if present
  if (Length(Line) >= 2) and (Line[1] = '"') and (Line[Length(Line)] = '"') then begin
    Line := Copy(Line, 2, Length(Line) - 2);
  end;
  Result := Trim(Line);
end;

function WriteInitialConfig(Param: string): string;
var
  cfgPath, cfgDir: string;
  apiBase, token, deviceId, installId, deviceName: string;
  lines: string;
  existing: AnsiString;
  existingText: string;
  existingAgentUuid, existingAgentToken: string;
begin
  cfgDir := ExpandConstant('{commonappdata}\E3Backup');
  cfgPath := cfgDir + '\agent.conf';

  apiBase := GetParamValue('API');
  if apiBase = '' then apiBase := GetSelectedApiBase;

  token := GetParamValue('TOKEN');

  // If an enrolled config already exists, preserve it.
  // This allows customers to reinstall/upgrade without re-enrolling every time.
  existing := '';
  existingText := '';
  if FileExists(cfgPath) and LoadStringFromFile(cfgPath, existing) then begin
    existingText := existing;
    existingAgentUuid := GetYamlValue(existingText, 'agent_uuid');
    existingAgentToken := GetYamlValue(existingText, 'agent_token');
    if (existingAgentUuid <> '') and (existingAgentToken <> '') then begin
      Result := 'echo preserved existing enrolled agent.conf';
      exit;
    end;
  end;

  // Preserve identity if present; otherwise generate.
  deviceId := GetYamlValue(existingText, 'device_id');
  installId := GetYamlValue(existingText, 'install_id');
  deviceName := GetYamlValue(existingText, 'device_name');
  if deviceId = '' then deviceId := NewUUIDv4('device');
  if installId = '' then installId := NewUUIDv4('install');
  if deviceName = '' then deviceName := ExpandConstant('{computername}');

  ForceDirectories(cfgDir);

  lines := 'api_base_url: ' + QuoteYaml(apiBase) + #13#10;
  if token <> '' then begin
    lines := lines + 'enrollment_token: ' + QuoteYaml(token) + #13#10;
  end;
  lines := lines +
    'device_id: ' + QuoteYaml(deviceId) + #13#10 +
    'install_id: ' + QuoteYaml(installId) + #13#10 +
    'device_name: ' + QuoteYaml(deviceName) + #13#10 +
    'poll_interval_secs: 5' + #13#10 +
    'run_dir: ' + QuoteYaml('C:\ProgramData\E3Backup\runs') + #13#10;

  SaveStringToFile(cfgPath, lines, False);
  Result := 'echo wrote ' + cfgPath;
end;


