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
        $info = curl_getinfo($ch);
        curl_close($ch);

        // If curl_exec failed entirely
        if ($response === false) {
            return [
                'curl_error' => $err,
                'http_code'  => $code,
                'curl_info'  => $info,
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $response];
        }
        // Inject curl metadata for callers
        $decoded['http_code'] = $code;
        if (!empty($err)) {
            $decoded['curl_error'] = $err;
        }
        $decoded['curl_info'] = $info;

        return $decoded;
    }

    private static function lxdUploadFile($containerName, $remotePath, $content, $target = null, $retryCount = 0)
    {
        if ($content === false || $content === null) {
            return ['error' => 'No file content'];
        }
        if (is_string($content) && strlen($content) === 0) {
            return ['error' => 'File content is empty'];
        }
        
        $path = '/1.0/instances/' . rawurlencode($containerName) . '/files?path=' . rawurlencode($remotePath);
        if ($target !== null) {
            $path .= '&target=' . rawurlencode($target);
        }
        
        // Set mode and ownership headers where available; ensure octet-stream for uploads
        $headers = [
            'Content-Type: application/octet-stream',
            'X-LXD-uid: 0',
            'X-LXD-gid: 0',
            'X-LXD-mode: 0644',
            'X-LXD-type: file',
        ];
        
        $result = self::lxdCurl('POST', $path, null, $content, $headers);
        
        // Validate upload success
        $httpCode = $result['http_code'] ?? 0;
        $status = $result['status'] ?? null;
        $curlError = $result['curl_error'] ?? null;
        
        // Check for curl errors
        if ($curlError !== null) {
            if ($retryCount < 1) {
                // Retry once with short delay
                sleep(1);
                return self::lxdUploadFile($containerName, $remotePath, $content, $target, $retryCount + 1);
            }
            return ['error' => 'CURL error during upload: ' . $curlError, 'http_code' => $httpCode];
        }
        
        // Require HTTP 200 and LXD Success status
        if ($httpCode !== 200) {
            if ($retryCount < 1) {
                sleep(1);
                return self::lxdUploadFile($containerName, $remotePath, $content, $target, $retryCount + 1);
            }
            return ['error' => 'Upload failed with HTTP code: ' . $httpCode, 'http_code' => $httpCode, 'response' => $result];
        }
        
        if ($status !== 'Success' && (!isset($result['type']) || $result['type'] !== 'sync')) {
            return ['error' => 'Upload failed: LXD returned non-success status', 'status' => $status, 'response' => $result];
        }
        
        return $result;
    }

    private static function lxdExecCommand($containerName, $command, $logContext = 'exec', $target = null)
    {
        $execPath = '/1.0/instances/' . rawurlencode($containerName) . '/exec';
        if ($target !== null) {
            $execPath .= '?target=' . rawurlencode($target);
        }
        
        $payload = [
            'command' => ['bash', '-lc', $command],
            'environment' => new \stdClass(),
            'wait-for-websocket' => false,
            'interactive' => false,
            'record-output' => true,
        ];
        $start = self::lxdCurl('POST', $execPath, $payload);
        try {
            logModuleCall('eazybackup', $logContext . '.start', [$containerName, $command], $start);
        } catch (\Throwable $e) {}
        if (!is_array($start) || !isset($start['operation'])) {
            return ['error' => 'Failed to start exec', 'response' => $start];
        }
        $op = $start['operation'];
        // Poll status until not Running
        do {
            sleep(1);
            $statusPath = parse_url($op, PHP_URL_PATH);
            if ($target !== null) {
                $statusPath .= (strpos($statusPath, '?') !== false ? '&' : '?') . 'target=' . rawurlencode($target);
            }
            $status = self::lxdCurl('GET', $statusPath);
            try {
                logModuleCall('eazybackup', $logContext . '.status', [$containerName], $status);
            } catch (\Throwable $e) {}
            $running = is_array($status) && isset($status['metadata']['status']) && $status['metadata']['status'] === 'Running';
        } while ($running);
        
        // Extract exit code from metadata
        $exitCode = null;
        if (is_array($status) && isset($status['metadata']['return'])) {
            $exitCode = (int)$status['metadata']['return'];
        }
        
        // Try fetch logs if available using the returned output paths (download raw content)
        $stdout = null;
        $stderr = null;
        if (is_array($status) && isset($status['metadata']['output']) && is_array($status['metadata']['output'])) {
            $out = $status['metadata']['output'];
            if (isset($out[1]) && is_string($out[1])) {
                $stdoutPath = $out[1] . '?download=1';
                if ($target !== null) {
                    $stdoutPath .= '&target=' . rawurlencode($target);
                }
                $stdoutRaw = self::lxdCurl('GET', $stdoutPath);
                $stdout = is_string($stdoutRaw) ? $stdoutRaw : (is_array($stdoutRaw) && isset($stdoutRaw['raw']) ? $stdoutRaw['raw'] : null);
            }
            if (isset($out[2]) && is_string($out[2])) {
                $stderrPath = $out[2] . '?download=1';
                if ($target !== null) {
                    $stderrPath .= '&target=' . rawurlencode($target);
                }
                $stderrRaw = self::lxdCurl('GET', $stderrPath);
                $stderr = is_string($stderrRaw) ? $stderrRaw : (is_array($stderrRaw) && isset($stderrRaw['raw']) ? $stderrRaw['raw'] : null);
            }
        }
        
        try {
            logModuleCall('eazybackup', $logContext . '.logs', [$containerName], [
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'status' => $status['metadata']['status'] ?? null
            ]);
        } catch (\Throwable $e) {}
        
        return [
            'status' => 'ok',
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'raw_status' => $status
        ];
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
                
                // Capture container location/target for clustered LXD
                $containerTarget = null;
                if (isset($result['metadata']['location']) && $result['metadata']['location'] !== 'none') {
                    $containerTarget = $result['metadata']['location'];
                }
                
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
                        $message = "Failed to start container with error: " . ($result['metadata']['err'] ?? 'Unknown error');
                        logModuleCall(
                            "eazybackup",
                            'provisionLXDContainer',
                            [$username, $password, $productId],
                            $message
                        );

                        return ['error' => $result['metadata']['err'] ?? 'Failed to start container'];
                    }
                    
                    // Update target if location changed during start
                    if (isset($result['metadata']['location']) && $result['metadata']['location'] !== 'none') {
                        $containerTarget = $result['metadata']['location'];
                    }

                    // Install software in the container
                    $installResult = self::installSoftwareInContainer($containerName, $username, $password, $productId, $containerTarget);
                    if (isset($installResult['error'])) {
                        $message = "Software installation failed: " . $installResult['error'];
                        logModuleCall(
                            "eazybackup",
                            'installSoftwareInContainer',
                            [$containerName, $username, $password, $productId],
                            $message
                        );
                        return $installResult;
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


    public static function installSoftwareInContainer($containerName, $username, $password, $productId, $target = null)
    {
        try {
            $message = "Starting software installation in container: $containerName";
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer',
                [$containerName, $username, $password, $productId, 'target' => $target],
                $message
            );
            
            // Guard against path drift - validate product ID mapping
            if ($productId == "57") { // OBC MS365
                $serverUrl = "https://csw.obcbackup.com/";
                $installerPath = "/var/www/eazybackup.ca/client_installer/OBC-25.9.8.deb";
                $expectedVersion = "25.9.8";
            } else { // Default to eazyBackup MS365
                $serverUrl = "https://csw.eazybackup.ca/";
                $installerPath = "/var/www/eazybackup.ca/client_installer/eazyBackup-25.9.8.deb";
                $expectedVersion = "25.9.8";
            }

            // Log chosen path and version
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer.path_selection',
                [$containerName, $productId],
                ['installer_path' => $installerPath, 'version' => $expectedVersion, 'server_url' => $serverUrl]
            );

            // Validate installer file exists and is readable
            if (!file_exists($installerPath)) {
                $errno = file_exists($installerPath) ? 0 : (function_exists('error_get_last') ? error_get_last()['type'] ?? 0 : 0);
                $message = "Installer file not found: $installerPath (errno: $errno)";
                logModuleCall(
                    "eazybackup",
                    'installSoftwareInContainer',
                    [$containerName, $username, $password, $productId],
                    $message
                );
                return ['error' => "Installer file not found: $installerPath"];
            }

            // Validate local file reads before upload
            $debBytes = @file_get_contents($installerPath);
            if ($debBytes === false || $debBytes === null) {
                $errno = function_exists('error_get_last') ? (error_get_last()['type'] ?? 0) : 0;
                $errmsg = function_exists('error_get_last') ? (error_get_last()['message'] ?? 'Unknown error') : 'Unknown error';
                $filesize = @filesize($installerPath);
                $message = "Installer file unreadable: $installerPath (size: $filesize, errno: $errno, error: $errmsg)";
                logModuleCall(
                    "eazybackup",
                    'installSoftwareInContainer',
                    [$containerName, $username, $password, $productId],
                    $message
                );
                return ['error' => "Installer file unreadable: $installerPath"];
            }
            
            if (strlen($debBytes) === 0) {
                $message = "Installer file is empty: $installerPath";
                logModuleCall(
                    "eazybackup",
                    'installSoftwareInContainer',
                    [$containerName, $username, $password, $productId],
                    $message
                );
                return ['error' => "Installer file is empty: $installerPath"];
            }
            
            // Optional: checksum verification before upload
            $hostSha256 = hash('sha256', $debBytes);
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer.pre_upload_checksum',
                [$containerName],
                ['installer_path' => $installerPath, 'size' => strlen($debBytes), 'sha256' => $hostSha256]
            );

            // Prepare debconf selections
            $debconfSelections = "backup-tool backup-tool/username string $username\nbackup-tool backup-tool/password password $password\nbackup-tool backup-tool/serverurl string $serverUrl\n";
            $debconfFile = "/tmp/debconf-backup-tool-$containerName";
            $debconfWritten = @file_put_contents($debconfFile, $debconfSelections);
            
            if ($debconfWritten === false) {
                $errno = function_exists('error_get_last') ? (error_get_last()['type'] ?? 0) : 0;
                $errmsg = function_exists('error_get_last') ? (error_get_last()['message'] ?? 'Unknown error') : 'Unknown error';
                $message = "Debconf file unreadable: Failed to write $debconfFile (errno: $errno, error: $errmsg)";
                logModuleCall(
                    "eazybackup",
                    'installSoftwareInContainer',
                    [$containerName, $username, $password, $productId],
                    $message
                );
                return ['error' => "Debconf file unreadable: Failed to write $debconfFile"];
            }
            
            $debconfContent = @file_get_contents($debconfFile);
            if ($debconfContent === false || $debconfContent === null || strlen($debconfContent) === 0) {
                $errno = function_exists('error_get_last') ? (error_get_last()['type'] ?? 0) : 0;
                $message = "Debconf file unreadable: Failed to read $debconfFile (errno: $errno)";
                logModuleCall(
                    "eazybackup",
                    'installSoftwareInContainer',
                    [$containerName, $username, $password, $productId],
                    $message
                );
                return ['error' => "Debconf file unreadable: Failed to read $debconfFile"];
            }
            
            $message = "Debconf selections written to $debconfFile (size: " . strlen($debconfContent) . " bytes)";
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer',
                [$containerName, $username, $password, $productId],
                $message
            );

            // Upload debconf selections into the container with enhanced logging
            $up1 = self::lxdUploadFile($containerName, '/tmp/debconf-backup-tool', $debconfContent, $target);
            try {
                logModuleCall('eazybackup', 'installSoftwareInContainer.upload_debconf', [
                    'container' => $containerName,
                    'remote_path' => '/tmp/debconf-backup-tool',
                    'local_path' => $debconfFile,
                    'byte_length' => strlen($debconfContent),
                    'target' => $target
                ], [
                    'http_code' => $up1['http_code'] ?? null,
                    'status' => $up1['status'] ?? null,
                    'error' => $up1['error'] ?? null,
                    'curl_error' => $up1['curl_error'] ?? null,
                    'response' => $up1
                ]);
            } catch (\Throwable $e) {}
            
            // Enforce upload success criteria
            if (!is_array($up1)) {
                return ['error' => 'Failed to upload debconf: Invalid response'];
            }
            if (!empty($up1['error'])) {
                return ['error' => 'Failed to upload debconf: ' . $up1['error']];
            }
            $httpCode1 = $up1['http_code'] ?? 0;
            $status1 = $up1['status'] ?? null;
            if ($httpCode1 !== 200 || ($status1 !== 'Success' && (!isset($up1['type']) || $up1['type'] !== 'sync'))) {
                return ['error' => 'Debconf upload failed: HTTP ' . $httpCode1 . ', status: ' . ($status1 ?? 'unknown')];
            }

            // Upload installer .deb into the container with enhanced logging
            $up2 = self::lxdUploadFile($containerName, '/tmp/software.deb', $debBytes, $target);
            try {
                logModuleCall('eazybackup', 'installSoftwareInContainer.upload_deb', [
                    'container' => $containerName,
                    'remote_path' => '/tmp/software.deb',
                    'local_path' => $installerPath,
                    'byte_length' => strlen($debBytes),
                    'target' => $target
                ], [
                    'http_code' => $up2['http_code'] ?? null,
                    'status' => $up2['status'] ?? null,
                    'error' => $up2['error'] ?? null,
                    'curl_error' => $up2['curl_error'] ?? null,
                    'response' => $up2
                ]);
            } catch (\Throwable $e) {}
            
            // Enforce upload success criteria
            if (!is_array($up2)) {
                return ['error' => 'Failed to upload installer: Invalid response'];
            }
            if (!empty($up2['error'])) {
                return ['error' => 'Failed to upload installer: ' . $up2['error']];
            }
            $httpCode2 = $up2['http_code'] ?? 0;
            $status2 = $up2['status'] ?? null;
            if ($httpCode2 !== 200 || ($status2 !== 'Success' && (!isset($up2['type']) || $up2['type'] !== 'sync'))) {
                return ['error' => 'Installer upload failed: HTTP ' . $httpCode2 . ', status: ' . ($status2 ?? 'unknown')];
            }
            
            // Stop the flow on failed prerequisites - if uploads failed, don't continue
            // (Already handled above, but explicit check for clarity)

            // Add pre-install presence checks inside the container
            $statCheck = self::lxdExecCommand($containerName, 'stat -c "%n %s" /tmp/debconf-backup-tool /tmp/software.deb 2>&1', 'installSoftwareInContainer.exec.preinstall_stat', $target);
            if (isset($statCheck['error'])) {
                return ['error' => 'Failed to verify uploaded files: ' . $statCheck['error']];
            }
            $statOutput = $statCheck['stdout'] ?? '';
            $statStderr = $statCheck['stderr'] ?? '';
            $statExitCode = $statCheck['exit_code'] ?? null;

            // If no stdout/stderr captured, try to fetch from operation output logs (fallback)
            if (($statOutput === '' && $statStderr === '') && isset($statCheck['raw_status']['metadata']['output']) && is_array($statCheck['raw_status']['metadata']['output'])) {
                $out = $statCheck['raw_status']['metadata']['output'];
                if (isset($out[1]) && is_string($out[1])) {
                    $stdoutPath = $out[1] . '?download=1';
                    if ($target !== null) {
                        $stdoutPath .= '&target=' . rawurlencode($target);
                    }
                    $stdoutRaw = self::lxdCurl('GET', $stdoutPath);
                    if (is_string($stdoutRaw)) {
                        $statOutput = $stdoutRaw;
                    } elseif (is_array($stdoutRaw) && isset($stdoutRaw['raw'])) {
                        $statOutput = $stdoutRaw['raw'];
                    }
                }
                if (isset($out[2]) && is_string($out[2])) {
                    $stderrPath = $out[2] . '?download=1';
                    if ($target !== null) {
                        $stderrPath .= '&target=' . rawurlencode($target);
                    }
                    $stderrRaw = self::lxdCurl('GET', $stderrPath);
                    if (is_string($stderrRaw)) {
                        $statStderr = $stderrRaw;
                    } elseif (is_array($stderrRaw) && isset($stderrRaw['raw'])) {
                        $statStderr = $stderrRaw['raw'];
                    }
                }
            }

            // Accept success if stat exit code is 0 (int or string) or not provided (but no error)
            $statSuccess = ($statExitCode === 0 || $statExitCode === '0' || $statExitCode === null);
            if (!$statSuccess) {
                // Fallback to string checks when exit code wasn't available or non-zero
                if (strpos($statOutput, '/tmp/debconf-backup-tool') !== false && strpos($statOutput, '/tmp/software.deb') !== false) {
                    $statSuccess = true;
                }
                // If stderr doesn't indicate missing files, treat as success
                if (stripos($statStderr, 'no such file') === false && stripos($statStderr, 'cannot stat') === false) {
                    $statSuccess = true;
                }
            }

            if (!$statSuccess) {
                $message = "Pre-install check failed: Files missing in container. stdout: $statOutput, stderr: $statStderr, exit_code: " . (is_null($statExitCode) ? 'null' : $statExitCode);
                logModuleCall('eazybackup', 'installSoftwareInContainer.preinstall_check_failed', [$containerName], [
                    'stdout' => $statOutput,
                    'stderr' => $statStderr,
                    'exit_code' => $statExitCode
                ]);
                return ['error' => 'Pre-install check failed: Required files missing in container'];
            }
            
            // Optional: Verify checksum after upload
            $containerSha256Check = self::lxdExecCommand($containerName, 'sha256sum /tmp/software.deb 2>&1 | cut -d" " -f1', 'installSoftwareInContainer.exec.deb_sha256', $target);
            if (!isset($containerSha256Check['error']) && !empty($containerSha256Check['stdout'])) {
                $containerSha256 = trim($containerSha256Check['stdout']);
                if ($containerSha256 !== $hostSha256) {
                    $message = "Checksum mismatch: host=$hostSha256, container=$containerSha256";
                    logModuleCall('eazybackup', 'installSoftwareInContainer.checksum_mismatch', [$containerName], [
                        'host_sha256' => $hostSha256,
                        'container_sha256' => $containerSha256
                    ]);
                    return ['error' => 'Checksum verification failed: File corrupted during upload'];
                }
                logModuleCall('eazybackup', 'installSoftwareInContainer.checksum_match', [$containerName], [
                    'sha256' => $hostSha256
                ]);
            }

            // Ensure debconf-utils available, then apply selections
            $updateResult = self::lxdExecCommand($containerName, 'DEBIAN_FRONTEND=noninteractive apt-get update -y', 'installSoftwareInContainer.exec.update', $target);
            if (isset($updateResult['error'])) {
                return ['error' => 'Failed to update apt: ' . $updateResult['error']];
            }
            
            $debconfUtilsResult = self::lxdExecCommand($containerName, 'DEBIAN_FRONTEND=noninteractive apt-get install -y debconf-utils ca-certificates', 'installSoftwareInContainer.exec.debconfutils', $target);
            if (isset($debconfUtilsResult['error']) || ($debconfUtilsResult['exit_code'] ?? 0) !== 0) {
                $exitCode = $debconfUtilsResult['exit_code'] ?? 'unknown';
                $stderr = $debconfUtilsResult['stderr'] ?? '';
                logModuleCall('eazybackup', 'installSoftwareInContainer.debconfutils_warning', [$containerName], [
                    'exit_code' => $exitCode,
                    'stderr' => $stderr,
                    'error' => $debconfUtilsResult['error'] ?? null
                ]);
                // Continue anyway as this is not fatal
            }
            
            $debconfResult = self::lxdExecCommand($containerName, 'debconf-set-selections /tmp/debconf-backup-tool', 'installSoftwareInContainer.exec.debconf', $target);
            if (isset($debconfResult['error'])) {
                logModuleCall('eazybackup', 'installSoftwareInContainer.debconf_warning', [$containerName], [
                    'error' => $debconfResult['error']
                ]);
                // Continue anyway
            }
            
            // Install using dpkg then fix deps, then dpkg again to ensure package is configured
            $dpkg1Result = self::lxdExecCommand($containerName, 'dpkg -i /tmp/software.deb', 'installSoftwareInContainer.exec.dpkg1', $target);
            $dpkg1ExitCode = $dpkg1Result['exit_code'] ?? null;
            if ($dpkg1ExitCode !== null && $dpkg1ExitCode !== 0) {
                logModuleCall('eazybackup', 'installSoftwareInContainer.dpkg1_warning', [$containerName], [
                    'exit_code' => $dpkg1ExitCode,
                    'stdout' => $dpkg1Result['stdout'] ?? '',
                    'stderr' => $dpkg1Result['stderr'] ?? ''
                ]);
                // Continue to fix dependencies
            }
            
            $fixDepsResult = self::lxdExecCommand($containerName, 'DEBIAN_FRONTEND=noninteractive apt-get -f install -y', 'installSoftwareInContainer.exec.fixdeps', $target);
            $fixDepsExitCode = $fixDepsResult['exit_code'] ?? null;
            if ($fixDepsExitCode !== null && $fixDepsExitCode !== 0) {
                logModuleCall('eazybackup', 'installSoftwareInContainer.fixdeps_warning', [$containerName], [
                    'exit_code' => $fixDepsExitCode,
                    'stdout' => $fixDepsResult['stdout'] ?? '',
                    'stderr' => $fixDepsResult['stderr'] ?? ''
                ]);
            }
            
            $dpkg2Result = self::lxdExecCommand($containerName, 'dpkg -i /tmp/software.deb', 'installSoftwareInContainer.exec.dpkg2', $target);
            $dpkg2ExitCode = $dpkg2Result['exit_code'] ?? null;
            if ($dpkg2ExitCode !== null && $dpkg2ExitCode !== 0) {
                $message = "dpkg installation failed with exit code: $dpkg2ExitCode";
                logModuleCall('eazybackup', 'installSoftwareInContainer.dpkg2_failed', [$containerName], [
                    'exit_code' => $dpkg2ExitCode,
                    'stdout' => $dpkg2Result['stdout'] ?? '',
                    'stderr' => $dpkg2Result['stderr'] ?? ''
                ]);
                return ['error' => $message];
            }
            
            // Start service
            $serviceResult = self::lxdExecCommand($containerName, 'systemctl daemon-reload && systemctl enable backup-tool && systemctl start backup-tool', 'installSoftwareInContainer.exec.service', $target);
            if (isset($serviceResult['error']) || ($serviceResult['exit_code'] ?? 0) !== 0) {
                logModuleCall('eazybackup', 'installSoftwareInContainer.service_warning', [$containerName], [
                    'exit_code' => $serviceResult['exit_code'] ?? null,
                    'stdout' => $serviceResult['stdout'] ?? '',
                    'stderr' => $serviceResult['stderr'] ?? '',
                    'error' => $serviceResult['error'] ?? null
                ]);
                // Continue anyway
            }
            
            // Verify CLI presence
            $chk = self::lxdExecCommand($containerName, '(command -v backup-tool || command -v /opt/eazyBackup/backup-tool || command -v /opt/OBC/backup-tool) >/dev/null 2>&1 && echo installed || echo missing', 'installSoftwareInContainer.exec.verify', $target);
            $verifyOutput = trim($chk['stdout'] ?? '');
            if ($verifyOutput !== 'installed') {
                $message = "Backup tool verification failed: output='$verifyOutput'";
                logModuleCall('eazybackup', 'installSoftwareInContainer.verify_failed', [$containerName], [
                    'stdout' => $chk['stdout'] ?? '',
                    'stderr' => $chk['stderr'] ?? '',
                    'exit_code' => $chk['exit_code'] ?? null
                ]);
                return ['error' => $message];
            }
            
            // Additional search to log exact path if present
            self::lxdExecCommand($containerName, 'for p in /opt /usr/local/bin /usr/bin; do find "$p" -maxdepth 5 -type f -name backup-tool 2>/dev/null; done', 'installSoftwareInContainer.exec.findbt', $target);

            // Extra diagnostics about the .deb inside the container
            self::lxdExecCommand($containerName, 'ls -l /tmp/software.deb', 'installSoftwareInContainer.exec.deb_ls', $target);
            self::lxdExecCommand($containerName, 'dpkg -I /tmp/software.deb 2>/dev/null | head -n 80', 'installSoftwareInContainer.exec.deb_info', $target);

            // Ensure config directory exists and perform non-interactive login with explicit server/username/password.
            $cfgDirResult = self::lxdExecCommand($containerName, 'mkdir -p /root/.config/backup-tool', 'installSoftwareInContainer.exec.ensure_cfgdir', $target);
            if (isset($cfgDirResult['error'])) {
                logModuleCall('eazybackup', 'installSoftwareInContainer.cfgdir_warning', [$containerName], [
                    'error' => $cfgDirResult['error']
                ]);
                // Continue anyway
            }
            
            $cmdLogin = 'BT=$(command -v /opt/eazyBackup/backup-tool || command -v /opt/OBC/backup-tool || command -v backup-tool || true); '
                      . 'echo "BT=$BT"; '
                      . 'if [ -x "$BT" ]; then '
                      . '  export HOME=/root; '
                      . '  "$BT" logout >/dev/null 2>&1 || true; '
                      . '  ( '
                      . '    "$BT" login ' . escapeshellarg($serverUrl) . ' ' . escapeshellarg($username) . ' ' . escapeshellarg($password) . ' > /tmp/eb-login.out 2>&1 || '
                      . '    "$BT" login add ' . escapeshellarg($serverUrl) . ' ' . escapeshellarg($username) . ' ' . escapeshellarg($password) . ' >> /tmp/eb-login.out 2>&1 || '
                      . '    "$BT" cmd -Action=login -Server=' . escapeshellarg($serverUrl) . ' -Username=' . escapeshellarg($username) . ' -Password=' . escapeshellarg($password) . ' >> /tmp/eb-login.out 2>&1 '
                      . '  ); '
                      . '  RC=$?; echo "RC=$RC" >> /tmp/eb-login.out; '
                      . '  "$BT" whoami >> /tmp/eb-login.out 2>&1 || true; '
                      . '  echo "--- eb-login.out ---"; cat /tmp/eb-login.out || true; '
                      . '  exit $RC; '
                      . 'else echo BT_NOT_FOUND; fi';
            $loginResult = self::lxdExecCommand($containerName, $cmdLogin, 'installSoftwareInContainer.exec.login', $target);
            $loginExitCode = $loginResult['exit_code'] ?? null;
            if ($loginExitCode !== null && $loginExitCode !== 0) {
                $loginStderr = $loginResult['stderr'] ?? '';
                $loginStdout = $loginResult['stdout'] ?? '';
                logModuleCall('eazybackup', 'installSoftwareInContainer.login_failed', [$containerName], [
                    'exit_code' => $loginExitCode,
                    'stdout' => $loginStdout,
                    'stderr' => $loginStderr
                ]);
                // Log but don't fail - service restart may still work
            }
            
            // Restart service so delegate-server loads fresh credentials
            $restartResult = self::lxdExecCommand($containerName, 'systemctl restart backup-tool', 'installSoftwareInContainer.exec.service_restart', $target);
            if (isset($restartResult['error']) || ($restartResult['exit_code'] ?? 0) !== 0) {
                logModuleCall('eazybackup', 'installSoftwareInContainer.service_restart_warning', [$containerName], [
                    'exit_code' => $restartResult['exit_code'] ?? null,
                    'stdout' => $restartResult['stdout'] ?? '',
                    'stderr' => $restartResult['stderr'] ?? '',
                    'error' => $restartResult['error'] ?? null
                ]);
                // Continue anyway
            }

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

    public static function loginPromptInContainer($containerName, $username, $password, $productId, $target = null)
    {
        try {
            // Resolve server URL
            if ($productId == "57") { // OBC MS365
                $serverUrl = "https://csw.obcbackup.com/";
            } else { // eazyBackup MS365
                $serverUrl = "https://csw.eazybackup.ca/";
            }

            // Non-interactive login inside container and restart service (with fallbacks + log capture)
            $cmd = 'BT=$(command -v /opt/eazyBackup/backup-tool || command -v /opt/OBC/backup-tool || command -v backup-tool || true); '
                 . 'echo "BT=$BT"; '
                 . 'if [ -x "$BT" ]; then '
                 . '  export HOME=/root; mkdir -p /root/.config/backup-tool || true; '
                 . '  "$BT" logout >/dev/null 2>&1 || true; '
                 . '  ( '
                 . '    "$BT" login ' . escapeshellarg($serverUrl) . ' ' . escapeshellarg($username) . ' ' . escapeshellarg($password) . ' > /tmp/eb-login.out 2>&1 || '
                 . '    "$BT" login add ' . escapeshellarg($serverUrl) . ' ' . escapeshellarg($username) . ' ' . escapeshellarg($password) . ' >> /tmp/eb-login.out 2>&1 || '
                 . '    "$BT" cmd -Action=login -Server=' . escapeshellarg($serverUrl) . ' -Username=' . escapeshellarg($username) . ' -Password=' . escapeshellarg($password) . ' >> /tmp/eb-login.out 2>&1 '
                 . '  ); '
                 . '  RC=$?; echo "RC=$RC" >> /tmp/eb-login.out; '
                 . '  "$BT" whoami >> /tmp/eb-login.out 2>&1 || true; '
                 . '  echo "--- eb-login.out ---"; cat /tmp/eb-login.out || true; '
                 . '  exit $RC; '
                 . 'else echo BT_NOT_FOUND; fi';
            $res = self::lxdExecCommand($containerName, $cmd, 'loginPromptInContainer.exec', $target);
            $restartRes = self::lxdExecCommand($containerName, 'systemctl restart backup-tool', 'loginPromptInContainer.exec.service_restart', $target);
            
            $exitCode = $res['exit_code'] ?? null;
            if ($exitCode !== null && $exitCode !== 0) {
                logModuleCall("eazybackup", 'loginPromptInContainer.login_warning', [$containerName], [
                    'exit_code' => $exitCode,
                    'stdout' => $res['stdout'] ?? '',
                    'stderr' => $res['stderr'] ?? ''
                ]);
            }

            return ['status' => 'success', 'message' => 'Login completed', 'output' => $res, 'restart_output' => $restartRes];
        } catch (\Exception $e) {
            $message = "Exception during login: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString();
            logModuleCall("eazybackup", 'loginPromptInContainer', [$containerName, $username, $password, $productId], $message);
            return ['error' => $e->getMessage()];
        }
    }

}