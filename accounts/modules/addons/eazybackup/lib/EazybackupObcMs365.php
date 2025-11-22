<?php

namespace WHMCS\Module\Addon\Eazybackup;

class EazybackupObcMs365 {

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

        } catch (Exception $e) {
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
            // Log the start of the installation process
            $message = "Starting software installation in container: $containerName";
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer',
                [$containerName, $username, $password, $productId],
                $message
            );
            // Determine the server URL and installer path based on the product ID
            if ($productId == "57") { // OBC MS365
                $serverUrl = "https://csw.obcbackup.com/";
                $installerPath = "/var/www/eazybackup.ca/client_installer/OBC-25.3.6.deb";
            } else { // Default to eazyBackup MS365
                $serverUrl = "https://csw.eazybackup.ca/";
                $installerPath = "/var/www/eazybackup.ca/client_installer/eazyBackup-25.3.6.deb";
            }

            // Pre-seed debconf answers for backup-tool
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

            // Check if the installer exists on the remote web server
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

            // Define the LXD host and SSH configurations
            $remoteLxdHost = 'ms365-containers.com';
            $knownHostsFile = "/var/www/.ssh/known_hosts";
            $sshKeyPath = "/var/www/.ssh/id_rsa";

            // Function to execute a command and log the output
            $executeCommand = function($command) use ($containerName, $username, $password, $productId) {
                $message = "Executing command: $command";
                logModuleCall(
                    "eazybackup",
                    'installSoftwareInContainer',
                    [$containerName, $username, $password, $productId],
                    $message
                );
                $output = shell_exec("$command 2>&1");
                $message = "Command output: " . $output;
                logModuleCall(
                    "eazybackup",
                    'installSoftwareInContainer',
                    [$containerName, $username, $password, $productId],
                    $message
                );
                return $output;
            };

            // Add the LXD server's host key to the known_hosts file
            $knownHostsFile = "/var/www/.ssh/known_hosts";
            $sshKeyScanCmd = "ssh-keyscan -H $remoteLxdHost >> $knownHostsFile";
            $message = "Adding LXD server's host key to known_hosts";
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer',
                [$containerName, $username, $password, $productId],
                $message
            );
            $executeCommand($sshKeyScanCmd);
            $message = "LXD server's host key added to known_hosts";
            logModuleCall(
                "eazybackup",
                'installSoftwareInContainer',
                [$containerName, $username, $password, $productId],
                $message
            );

            // Path to the SSH key
            $sshKeyPath = "/var/www/.ssh/id_rsa";

            // Copy the debconf selections and the software package to the LXD host
            $commands = [
                "scp -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile $debconfFile root@$remoteLxdHost:/tmp/debconf-backup-tool-$containerName",
                "scp -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile $installerPath root@$remoteLxdHost:/tmp/software.deb", // Dynamic installer name
                // Copy the files from the LXD host to the container
                "ssh -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile root@$remoteLxdHost 'lxc file push /tmp/debconf-backup-tool-$containerName $containerName/tmp/debconf-backup-tool'",
                "ssh -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile root@$remoteLxdHost 'lxc file push /tmp/software.deb $containerName/tmp/software.deb'", // Dynamic installer name
                // Set debconf selections
                "ssh -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile root@$remoteLxdHost 'lxc exec $containerName -- debconf-set-selections /tmp/debconf-backup-tool'",
                // Install the software
                "ssh -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile root@$remoteLxdHost 'lxc exec $containerName -- env DEBIAN_FRONTEND=noninteractive apt-get install -y -o Dpkg::Options::=\"--force-confdef\" -o Dpkg::Options::=\"--force-confold\" /tmp/software.deb'",
                // Start the backup-tool service
                "ssh -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile root@$remoteLxdHost 'lxc exec $containerName -- systemctl start backup-tool'",
                // Verify the backup-tool service status
                "ssh -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile root@$remoteLxdHost 'lxc exec $containerName -- systemctl status backup-tool'"
            ];

            foreach ($commands as $command) {
                $output = $executeCommand($command);
                if (strpos($output, 'error') !== false) {
                    $message = "Error during command execution: " . $output;
                    logModuleCall(
                        "eazybackup",
                        'installSoftwareInContainer',
                        [$containerName, $username, $password, $productId],
                        $message
                    );
                    return ['error' => 'Error during software installation'];
                }
            }

            // Clean up the temporary debconf file
            unlink($debconfFile);

            return ['status' => 'success', 'message' => 'Software installed successfully'];

        } catch (Exception $e) {
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
            // Determine the correct backup-tool command based on the product ID.
            if ($productId == "57") { // OBC MS365
                $commandBase = "/opt/OBC/backup-tool login prompt";
            } else {
                $commandBase = "/opt/eazyBackup/backup-tool login prompt";
            }

            /*
             * Build the command that pipes the responses:
             *   - The first "\n" sends an empty response (Enter) for the username.
             *   - Then "$password\n" sends the password.
             *   - Finally, another "\n" sends an empty response for the server URL.
             *
             * This is done with printf to produce exactly these three lines.
             */
            $loginPromptCommand = "printf \"\\n%s\\n\\n\" \"$password\" | lxc exec $containerName -- $commandBase";

            // Define the LXD host and SSH configurations.
            $remoteLxdHost  = 'ms365-containers.com';
            $knownHostsFile = "/var/www/.ssh/known_hosts";
            $sshKeyPath     = "/var/www/.ssh/id_rsa";

            // Function to execute a command and log the output.
            $executeCommand = function($command) use ($containerName, $username, $password, $productId) {
                $message = "Executing command: $command";
                logModuleCall("eazybackup", 'loginPromptInContainer', [$containerName, $username, $password, $productId], $message);
                $output = shell_exec("$command 2>&1");
                logModuleCall("eazybackup", 'loginPromptInContainer', [$containerName, $username, $password, $productId], "Command output: " . $output);
                return $output;
            };

            // Ensure the LXD server's host key is added to known_hosts.
            $sshKeyScanCmd = "ssh-keyscan -H $remoteLxdHost >> $knownHostsFile 2>/dev/null";
            $executeCommand($sshKeyScanCmd);

            // Execute the command on the remote host via SSH.
            $command = "ssh -i $sshKeyPath -o UserKnownHostsFile=$knownHostsFile root@$remoteLxdHost '$loginPromptCommand'";
            $output = $executeCommand($command);
            if (strpos(strtolower($output), 'error') !== false) {
                logModuleCall("eazybackup", 'loginPromptInContainer', [$containerName, $username, $password, $productId], "Error during command execution: " . $output);
                return ['error' => 'Error during software installation'];
            }

            return ['status' => 'success', 'message' => 'Software installed successfully'];
        } catch (Exception $e) {
            $message = "Exception during software installation: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString();
            logModuleCall("eazybackup", 'loginPromptInContainer', [$containerName, $username, $password, $productId], $message);
            return ['error' => $e->getMessage()];
        }
    }

}