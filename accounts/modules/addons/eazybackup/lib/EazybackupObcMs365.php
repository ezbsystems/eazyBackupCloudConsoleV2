<?php

namespace WHMCS\Module\Addon\Eazybackup;

class EazybackupObcMs365 {

    private static function lxdBaseUrl()
    {
        return 'https://ms365-containers.com:8443';
    }

    private static function lxdCurl($method, $path, $jsonBody = null, $rawBody = null, array $extraHeaders = [])
    {
        $url = rtrim(self::lxdBaseUrl(), '/') . $path;
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
        if (!empty($extraHeaders)) {
            $headers = array_merge($headers, $extraHeaders);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSLCERT, '/var/www/ssl/client.crt');
        curl_setopt($ch, CURLOPT_SSLKEY, '/var/www/ssl/client.key');
        curl_setopt($ch, CURLOPT_CAINFO, '/var/www/ssl/lxd-server.crt');
        if ($jsonBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($jsonBody) ? $jsonBody : json_encode($jsonBody));
        } elseif ($rawBody !== null) {
            // For file uploads to /files endpoint we need to send raw bytes and override content-type
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_diff($headers, ['Content-Type: application/json']));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        }
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            return ['curl_error' => $err, 'http_code' => $code];
        }
        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : ['raw' => $response, 'http_code' => $code];
    }

    private static function lxdUploadFile($containerName, $remotePath, $content)
    {
        if ($content === false || $content === null) {
            return ['error' => 'No file content'];
        }
        $path = '/1.0/instances/' . rawurlencode($containerName) . '/files?path=' . $remotePath;
        // Set mode and ownership headers where available; ensure octet-stream for uploads
        $headers = [
            'Content-Type: application/octet-stream',
            'X-LXD-uid: 0',
            'X-LXD-gid: 0',
            'X-LXD-mode: 0644',
            'X-LXD-type: file',
        ];
        return self::lxdCurl('POST', $path, null, $content, $headers);
    }

    private static function lxdExecCommand($containerName, $command, $logContext = 'exec')
    {
        $payload = [
            'command' => ['bash', '-lc', $command],
            'environment' => new \stdClass(),
            'wait-for-websocket' => false,
            'interactive' => false,
            'record-output' => true,
        ];
        $start = self::lxdCurl('POST', '/1.0/instances/' . rawurlencode($containerName) . '/exec', $payload);
        try {
            logModuleCall('eazybackup', $logContext . '.start', [$containerName, $command], $start);
        } catch (\Throwable $e) {}
        if (!is_array($start) || !isset($start['operation'])) {
            return ['error' => 'Failed to start exec'];
        }
        $op = $start['operation'];
        // Poll status until not Running
        do {
            sleep(1);
            $status = self::lxdCurl('GET', parse_url($op, PHP_URL_PATH));
            try {
                logModuleCall('eazybackup', $logContext . '.status', [$containerName], $status);
            } catch (\Throwable $e) {}
            $running = is_array($status) && isset($status['metadata']['status']) && $status['metadata']['status'] === 'Running';
        } while ($running);
        // Try fetch logs if available using the returned output paths (download raw content)
        $stdout = null;
        $stderr = null;
        if (is_array($status) && isset($status['metadata']['output']) && is_array($status['metadata']['output'])) {
            $out = $status['metadata']['output'];
            if (isset($out[1]) && is_string($out[1])) {
                $stdout = self::lxdCurl('GET', $out[1] . '?download=1');
            }
            if (isset($out[2]) && is_string($out[2])) {
                $stderr = self::lxdCurl('GET', $out[2] . '?download=1');
            }
        }
        try {
            logModuleCall('eazybackup', $logContext . '.logs', [$containerName], ['stdout' => $stdout, 'stderr' => $stderr]);
        } catch (\Throwable $e) {}
        return ['status' => 'ok', 'stdout' => $stdout, 'stderr' => $stderr, 'raw_status' => $status];
    }

    public static function provisionLXDContainer($username, $password, $productId)
    {
        try {
            $url = 'https://ms365-containers.com:8443/1.0/containers';
            $data = json_encode([
                "name" => preg_replace("/[^a-zA-Z0-9]/", "", $username), // Sanitize username
                "architecture" => "x86_64",
                "profiles" => ["default"],
                "ephemeral" => false,
                "source" => [
                    "type" => "image",
                    "alias" => "ubuntu-20.04-lts" // Use the local alias
                ]
            ]);

            $message = "Starting container creation for user: $username";
            logModuleCall(
                "eazybackup",
                'provisionLXDContainer',
                [$username, $password, $productId],
                $message
            );

            // Create the container
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSLCERT, '/var/www/ssl/client.crt');
            curl_setopt($ch, CURLOPT_SSLKEY, '/var/www/ssl/client.key');
            curl_setopt($ch, CURLOPT_CAINFO, '/var/www/ssl/lxd-server.crt'); // Updated path to the new server certificate

            $response = curl_exec($ch);
            if (!$response) {
                $message = "CURL error during container creation: " . curl_error($ch);
                logModuleCall(
                    "eazybackup",
                    'provisionLXDContainer',
                    [$username, $password, $productId],
                    $message
                );
                return ['error' => curl_error($ch)];
            }

            $result = json_decode($response, true);
            curl_close($ch);

            // Log container creation result
            $message = "Container creation result: " . json_encode($result);
            logModuleCall(
                "eazybackup",
                'provisionLXDContainer',
                [$username, $password, $productId],
                $message
            );

            if (isset($result['operation'])) {
                $operationUrl = 'https://ms365-containers.com:8443' . $result['operation'];

                // Polling the operation status
                do {
                    sleep(1); // Wait for 1 second before polling again

                    $ch = curl_init($operationUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_SSLCERT, '/var/www/ssl/client.crt');
                    curl_setopt($ch, CURLOPT_SSLKEY, '/var/www/ssl/client.key');
                    curl_setopt($ch, CURLOPT_CAINFO, '/var/www/ssl/lxd-server.crt'); // Updated path to the new server certificate

                    $response = curl_exec($ch);
                    if (!$response) {
                        $message = "CURL error during operation polling: " . curl_error($ch);
                        logModuleCall(
                            "eazybackup",
                            'provisionLXDContainer',
                            [$username, $password, $productId],
                            $message
                        );

                        return ['error' => curl_error($ch)];
                    }

                    $result = json_decode($response, true);
                    curl_close($ch);

                    // Log operation status
                    $message = "Operation status: " . json_encode($result);
                    logModuleCall(
                        "eazybackup",
                        'provisionLXDContainer',
                        [$username, $password, $productId],
                        $message
                    );

                } while ($result['metadata']['status'] == 'Running');

                if ($result['metadata']['status'] != 'Success') {
                    $message = "Operation failed with error: " . ($result['metadata']['err'] ?? 'Unknown error');
                    logModuleCall(
                        "eazybackup",
                        'provisionLXDContainer',
                        [$username, $password, $productId],
                        $message
                    );
                    return ['error' => $result['metadata']['err'] ?? 'Unknown error'];
                }

                // Start the container after creation
                $containerName = basename($result['metadata']['resources']['instances'][0]);
                $startUrl = 'https://ms365-containers.com:8443/1.0/instances/' . $containerName . '/state';

                $startData = json_encode([
                    "action" => "start",
                    "timeout" => 30,
                    "force" => true
                ]);

                $ch = curl_init($startUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $startData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_SSLCERT, '/var/www/ssl/client.crt');
                curl_setopt($ch, CURLOPT_SSLKEY, '/var/www/ssl/client.key');
                curl_setopt($ch, CURLOPT_CAINFO, '/var/www/ssl/lxd-server.crt'); // Updated path to the new server certificate

                $response = curl_exec($ch);
                if (!$response) {
                    $message = "CURL error during container start: " . curl_error($ch);
                    logModuleCall(
                        "eazybackup",
                        'provisionLXDContainer',
                        [$username, $password, $productId],
                        $message
                    );

                    return ['error' => curl_error($ch)];
                }

                $result = json_decode($response, true);
                curl_close($ch);

                // Log container start result
                $message = "Container start result: " . json_encode($result);
                logModuleCall(
                    "eazybackup",
                    'provisionLXDContainer',
                    [$username, $password, $productId],
                    $message
                );
                if (isset($result['operation'])) {
                    $operationUrl = 'https://ms365-containers.com:8443' . $result['operation'];

                    // Polling the start operation status
                    do {
                        sleep(1); // Wait for 1 second before polling again

                        $ch = curl_init($operationUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_SSLCERT, '/var/www/ssl/client.crt');
                        curl_setopt($ch, CURLOPT_SSLKEY, '/var/www/ssl/client.key');
                        curl_setopt($ch, CURLOPT_CAINFO, '/var/www/ssl/lxd-server.crt'); // Updated path to the new server certificate

                        $response = curl_exec($ch);
                        if (!$response) {
                            $message = "CURL error during start operation polling: " . curl_error($ch);
                            logModuleCall(
                                "eazybackup",
                                'provisionLXDContainer',
                                [$username, $password, $productId],
                                $message
                            );

                            return ['error' => curl_error($ch)];
                        }

                        $result = json_decode($response, true);
                        curl_close($ch);

                        // Log start operation status
                        $message = "Start operation status: " . json_encode($result);
                        logModuleCall(
                            "eazybackup",
                            'provisionLXDContainer',
                            [$username, $password, $productId],
                            $message
                        );

                    } while ($result['metadata']['status'] == 'Running');

                    if ($result['metadata']['status'] != 'Success') {
                        $message = "Failed to start container with error: " . ($result['metadata']['err'] ?? 'Unknown
                        error');
                        logModuleCall(
                            "eazybackup",
                            'provisionLXDContainer',
                            [$username, $password, $productId],
                            $message
                        );

                        return ['error' => $result['metadata']['err'] ?? 'Failed to start container'];
                    }

                    // Install software in the container
                    $installResult = self::installSoftwareInContainer($containerName, $username, $password, $productId);
                    if (isset($installResult['error'])) {
                        $message = "Software installation failed: " . $installResult['error'];
                        logModuleCall(
                            "eazybackup",
                            'installSoftwareInContainer',
                            [$containerName, $username, $password, $productId],
                            $message
                        );
                        echo json_encode($installResult);
                        exit; // Ensure we stop further execution
                    }

                } else {
                    $message = "No operation ID returned from container start.";
                    logModuleCall(
                        "eazybackup",
                        'provisionLXDContainer',
                        [$username, $password, $productId],
                        $message
                    );

                    return ['error' => 'No operation ID returned'];
                }
            } else {
                $message = "No operation ID returned from container creation.";
                logModuleCall(
                    "eazybackup",
                    'provisionLXDContainer',
                    [$username, $password, $productId],
                    $message
                );

                return ['error' => 'No operation ID returned'];
            }

            $message = "Container provisioning completed successfully for user: $username";
            logModuleCall(
                "eazybackup",
                'provisionLXDContainer',
                [$username, $password, $productId],
                $message
            );

            return ['status' => 'success', 'message' => 'Container provisioning completed successfully'];

        } catch (\Exception $e) {
            $message = "Exception during provisioning: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString();
            logModuleCall(
                "eazybackup",
                'provisionLXDContainer',
                [$username, $password, $productId],
                $message
            );

            return ['error' => $e->getMessage()];
        }
    }


    public static function installSoftwareInContainer($containerName, $username, $password, $productId)
    {
        try {
            $message = "Starting software installation in container: $containerName";
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer',
                [$containerName, $username, $password, $productId],
                $message
            );
            if ($productId == "57") { // OBC MS365
                $serverUrl = "https://csw.obcbackup.com/";
                $installerPath = "/var/www/eazybackup.ca/client_installer/OBC-25.3.6.deb";
            } else { // Default to eazyBackup MS365
                $serverUrl = "https://csw.eazybackup.ca/";
                $installerPath = "/var/www/eazybackup.ca/client_installer/eazyBackup-25.3.6.deb";
            }

            $debconfSelections = "backup-tool backup-tool/username string $username\nbackup-tool backup-tool/password password $password\nbackup-tool backup-tool/serverurl string $serverUrl\n";
            $debconfFile = "/tmp/debconf-backup-tool-$containerName";
            file_put_contents($debconfFile, $debconfSelections);
            $message = "Debconf selections written to $debconfFile";
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer',
                [$containerName, $username, $password, $productId],
                $message
            );

            if (!file_exists($installerPath)) {
                $message = "Installer file not found: $installerPath";
                logModuleCall(
                    "eazybackup",
                    'installSoftwareInContainer',
                    [$containerName, $username, $password, $productId],
                    $message
                );
                return ['error' => "Installer file not found: $installerPath"];
            }

            // Upload debconf selections into the container
            $up1Content = @file_get_contents($debconfFile);
            $up1 = self::lxdUploadFile($containerName, '/tmp/debconf-backup-tool', $up1Content);
            try { logModuleCall('eazybackup', 'installSoftwareInContainer.upload_debconf', [$containerName], $up1); } catch (\Throwable $e) {}
            if (!is_array($up1)) { return ['error' => 'Failed to upload debconf']; }

            // Upload installer .deb into the container
            $debBytes = @file_get_contents($installerPath);
            $up2 = self::lxdUploadFile($containerName, '/tmp/software.deb', $debBytes);
            try { logModuleCall('eazybackup', 'installSoftwareInContainer.upload_deb', [$containerName, 'size' => is_string($debBytes) ? strlen($debBytes) : 0], $up2); } catch (\Throwable $e) {}
            if (!is_array($up2)) { return ['error' => 'Failed to upload installer']; }

            // Ensure debconf-utils available, then apply selections
            self::lxdExecCommand($containerName, 'DEBIAN_FRONTEND=noninteractive apt-get update -y', 'installSoftwareInContainer.exec.update');
            self::lxdExecCommand($containerName, 'DEBIAN_FRONTEND=noninteractive apt-get install -y debconf-utils || true', 'installSoftwareInContainer.exec.debconfutils');
            self::lxdExecCommand($containerName, 'debconf-set-selections /tmp/debconf-backup-tool || true', 'installSoftwareInContainer.exec.debconf');
            // Verify files inside container prior to install
            self::lxdExecCommand($containerName, 'stat -c "%n %s" /tmp/debconf-backup-tool /tmp/software.deb || true', 'installSoftwareInContainer.exec.preinstall_stat');
            // Install using dpkg then fix deps, then dpkg again to ensure package is configured
            self::lxdExecCommand($containerName, 'dpkg -i /tmp/software.deb || true', 'installSoftwareInContainer.exec.dpkg1');
            self::lxdExecCommand($containerName, 'DEBIAN_FRONTEND=noninteractive apt-get -f install -y || true', 'installSoftwareInContainer.exec.fixdeps');
            self::lxdExecCommand($containerName, 'dpkg -i /tmp/software.deb || true', 'installSoftwareInContainer.exec.dpkg2');
            // Start service
            self::lxdExecCommand($containerName, 'systemctl daemon-reload || true; systemctl enable backup-tool || true; systemctl start backup-tool || true', 'installSoftwareInContainer.exec.service');
            // Verify CLI presence
            $chk = self::lxdExecCommand($containerName, '(command -v backup-tool || command -v /opt/eazyBackup/backup-tool || command -v /opt/OBC/backup-tool) >/dev/null 2>&1 && echo installed || echo missing', 'installSoftwareInContainer.exec.verify');
            // Additional search to log exact path if present
            self::lxdExecCommand($containerName, 'for p in /opt /usr/local/bin /usr/bin; do find "$p" -maxdepth 5 -type f -name backup-tool 2>/dev/null; done', 'installSoftwareInContainer.exec.findbt');

            // Attempt device login/registration using non-interactive piping inside container
            // Extra diagnostics about the .deb inside the container
            self::lxdExecCommand($containerName, 'ls -l /tmp/software.deb || true', 'installSoftwareInContainer.exec.deb_ls');
            self::lxdExecCommand($containerName, 'sha256sum /tmp/software.deb || true', 'installSoftwareInContainer.exec.deb_sha256');
            self::lxdExecCommand($containerName, 'dpkg -I /tmp/software.deb 2>/dev/null | head -n 80 || true', 'installSoftwareInContainer.exec.deb_info');

            $cmdLogin = 'BT=$(command -v backup-tool || command -v /opt/eazyBackup/backup-tool || command -v /opt/OBC/backup-tool || true); ' .
                        'echo "BT=$BT"; if [ -x "$BT" ]; then printf "\n' . addslashes($password) . '\n\n" | "$BT" login prompt; else echo BT_NOT_FOUND; fi';
            self::lxdExecCommand($containerName, $cmdLogin, 'installSoftwareInContainer.exec.login');

            unlink($debconfFile);

            return ['status' => 'success', 'message' => 'Software installed successfully'];

        } catch (\Exception $e) {
            $message = "Exception during software installation: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString();
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer',
                [$containerName, $username, $password, $productId],
                $message
            );
            return ['error' => $e->getMessage()];
        }
    }

    public static function loginPromptInContainer($containerName, $username, $password, $productId)
    {
        try {
            if ($productId == "57") { // OBC MS365
                $commandBase = "/opt/OBC/backup-tool login prompt";
            } else {
                $commandBase = "/opt/eazyBackup/backup-tool login prompt";
            }

            // Execute inside container (no SSH, no shell_exec): pipe password to "login prompt"
            $cmd = 'printf "\n' . addslashes($password) . '\n\n" | ' . $commandBase;
            $res = self::lxdExecCommand($containerName, $cmd, 'loginPromptInContainer.exec');
            return ['status' => 'success', 'message' => 'Software installed successfully', 'output' => $res];
        } catch (\Exception $e) {
            $message = "Exception during software installation: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString();
            logModuleCall("eazybackup", 'loginPromptInContainer', [$containerName, $username, $password, $productId], $message);
            return ['error' => $e->getMessage()];
        }
    }

}