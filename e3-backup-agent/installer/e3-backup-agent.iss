[Setup]
AppName=E3 Backup Agent
AppVersion=1.0.0
AppPublisher=EazyBackup
DefaultDirName={autopf}\E3Backup
DefaultGroupName=E3 Backup Agent
DisableProgramGroupPage=yes
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
Compression=lzma2
SolidCompression=yes
PrivilegesRequired=admin
OutputBaseFilename=e3-backup-agent-setup
WizardStyle=modern

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "autorun_tray"; Description: "Run tray helper at login (recommended)"; Flags: checkedonce
Name: "start_tray_now"; Description: "Start tray helper after install"; Flags: checkedonce

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

; Tray icon assets (installer places PNG next to tray exe; tray will wrap PNG->ICO at runtime)
Source: "..\..\e3-cloudbackup-worker\assets\tray_logo-drk-orange120x120.png"; DestDir: "{app}"; DestName: "tray_logo-drk-orange120x120.png"; Flags: ignoreversion
Source: "..\..\e3-cloudbackup-worker\assets\tray_logo-drk-orange.svg"; DestDir: "{app}"; DestName: "tray_logo-drk-orange.svg"; Flags: ignoreversion

[Registry]
; Auto-run tray helper for all users (important for MSP/RMM installs that run elevated/SYSTEM)
Root: HKLM; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "E3BackupTray"; ValueData: """{app}\e3-backup-tray.exe"" -config ""{commonappdata}\E3Backup\agent.conf"""; Tasks: autorun_tray; Flags: uninsdeletevalue

[Run]
; Write initial config to ProgramData\E3Backup\agent.conf
Filename: "{cmd}"; Parameters: "/c ""{code:WriteInitialConfig}"""; Flags: runhidden

; Install/Start the Windows service
Filename: "{app}\e3-backup-agent.exe"; Parameters: "-service install -config ""{commonappdata}\E3Backup\agent.conf"""; Flags: runhidden
Filename: "{app}\e3-backup-agent.exe"; Parameters: "-service start -config ""{commonappdata}\E3Backup\agent.conf"""; Flags: runhidden

; Start tray helper after install (optional)
Filename: "{app}\e3-backup-tray.exe"; Parameters: "-config ""{commonappdata}\E3Backup\agent.conf"""; Tasks: start_tray_now; Flags: nowait postinstall skipifsilent

[UninstallRun]
; Stop tray helper (may lock files in Program Files)
Filename: "{cmd}"; Parameters: "/c taskkill /F /IM e3-backup-tray.exe >nul 2>&1"; Flags: runhidden; RunOnceId: "StopTray"
; Stop/uninstall service on uninstall
Filename: "{app}\e3-backup-agent.exe"; Parameters: "-service stop -config ""{commonappdata}\E3Backup\agent.conf"""; Flags: runhidden; RunOnceId: "StopService"
Filename: "{app}\e3-backup-agent.exe"; Parameters: "-service uninstall -config ""{commonappdata}\E3Backup\agent.conf"""; Flags: runhidden; RunOnceId: "UninstallService"

[UninstallDelete]
; Remove ProgramData folder and any leftover app files
Type: filesandordirs; Name: "{commonappdata}\E3Backup"
Type: filesandordirs; Name: "{app}"

[Code]
var
  EnvPage: TWizardPage;
  UseDevCheckbox: TNewCheckBox;

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
begin
  EnvPage := CreateCustomPage(
    wpSelectTasks,
    'Server Environment',
    'Choose which server the agent should enroll with.'
  );

  UseDevCheckbox := TNewCheckBox.Create(EnvPage);
  UseDevCheckbox.Parent := EnvPage.Surface;
  UseDevCheckbox.Caption := 'Use development server (dev.eazybackup.ca)';
  UseDevCheckbox.Checked := False;
  UseDevCheckbox.Left := ScaleX(0);
  UseDevCheckbox.Top := ScaleY(8);
  UseDevCheckbox.Width := EnvPage.SurfaceWidth;
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
  P, E, NL: Integer;
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
  existingAgentId, existingAgentToken: string;
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
    existingAgentId := GetYamlValue(existingText, 'agent_id');
    existingAgentToken := GetYamlValue(existingText, 'agent_token');
    if (existingAgentId <> '') and (existingAgentToken <> '') then begin
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


