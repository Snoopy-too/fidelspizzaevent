<?php
/**
 * GitHub Webhook Deployment Script for Bluehost
 *
 * This script receives GitHub webhooks and automatically deploys your app
 * to your Bluehost hosting account.
 *
 * SETUP INSTRUCTIONS:
 * 1. Update configuration below with your details
 * 2. Create a webhook in your GitHub repository:
 *    - Go to Settings > Webhooks > Add webhook
 *    - Payload URL: https://yoursite.com/fidelspizzaevent/deploy.php
 *    - Content type: application/json
 *    - Secret: [your-secure-secret-here]
 *    - Events: Push events
 * 3. Update $GITHUB_SECRET to match the secret in your webhook
 */

// ===== CONFIGURATION =====
$GITHUB_SECRET = '0GwqQGt6ck2DhKUfaWmcbeTnzK4zNj8K';
$GITHUB_OWNER = 'Snoopy-too';
$GITHUB_REPO = 'fidelspizzaevent';
$GITHUB_BRANCH = 'master';

// Files/directories to preserve (won't be overwritten)
$PRESERVE_FILES = [
    'config.php',
    'deploy.php',
    'init-git.php',
    'deploy.log',
    'images',
    '.git'
];

// ===== END CONFIGURATION =====

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set up logging
$log_file = __DIR__ . '/deploy.log';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

function verify_github_webhook($secret, $payload, $signature) {
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($hash, $signature);
}

function send_response($code, $message) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => ($code === 200 ? 'success' : 'error'), 'message' => $message]);
    exit;
}

try {
    // First, create initial log entry to confirm script is running
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Deployment script executed\n", FILE_APPEND);

    log_message('Webhook received from GitHub');
    log_message('Headers: ' . json_encode(getallheaders()));
    log_message('Request method: ' . $_SERVER['REQUEST_METHOD']);

    // Get the raw POST data
    $payload = file_get_contents('php://input');
    log_message('Payload size: ' . strlen($payload) . ' bytes');

    if (empty($payload)) {
        log_message('Error: No payload received');
        send_response(400, 'No payload received');
    }

    // Verify webhook signature
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    log_message('Signature received: ' . substr($signature, 0, 20) . '...');
    log_message('Secret length: ' . strlen($GITHUB_SECRET));

    if (!verify_github_webhook($GITHUB_SECRET, $payload, $signature)) {
        log_message('Invalid webhook signature - verification failed');
        send_response(403, 'Invalid signature');
    }

    log_message('Webhook signature verified successfully');

    // Parse the JSON payload
    $data = json_decode($payload, true);

    if (!$data) {
        send_response(400, 'Invalid JSON payload');
    }

    // Check if this is a push event on the main branch
    if ($data['ref'] !== "refs/heads/$GITHUB_BRANCH") {
        log_message("Push to different branch received: {$data['ref']}, ignoring");
        send_response(200, 'Not target branch, ignoring');
    }

    log_message('Valid webhook received for ' . $GITHUB_BRANCH . ' branch');

    // Get the commit info
    $commit = $data['head_commit'];
    $author = $commit['author']['name'] ?? 'Unknown';
    $message = $commit['message'] ?? 'No message';

    log_message("Deployment triggered by: $author - $message");

    // Download and extract the latest release
    $download_url = "https://github.com/$GITHUB_OWNER/$GITHUB_REPO/archive/refs/heads/$GITHUB_BRANCH.zip";
    $temp_file = tempnam(sys_get_temp_dir(), 'deploy_');
    $extract_dir = sys_get_temp_dir() . '/deploy_extract_' . time();

    log_message("Downloading from: $download_url");

    // Download the repository as ZIP
    $ch = curl_init($download_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $zip_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($zip_content)) {
        log_message("Failed to download repository. HTTP Code: $http_code");
        send_response(500, 'Failed to download repository');
    }

    // Save the ZIP file
    if (file_put_contents($temp_file, $zip_content) === false) {
        log_message("Failed to save temporary ZIP file");
        send_response(500, 'Failed to save temporary file');
    }

    log_message("ZIP file downloaded, size: " . filesize($temp_file) . " bytes");

    // Extract the ZIP file
    if (!mkdir($extract_dir, 0755, true)) {
        log_message("Failed to create extraction directory: $extract_dir");
        send_response(500, 'Failed to create extraction directory');
    }

    $zip = new ZipArchive();
    if (!$zip->open($temp_file)) {
        log_message("Failed to open ZIP file");
        send_response(500, 'Failed to open ZIP file');
    }

    if (!$zip->extractTo($extract_dir)) {
        log_message("Failed to extract ZIP file");
        send_response(500, 'Failed to extract ZIP file');
    }

    $zip->close();
    log_message("ZIP extracted successfully");

    // Find the extracted folder (should be fidelspizzaevent-master or similar)
    $extracted_files = scandir($extract_dir);
    $source_dir = null;

    foreach ($extracted_files as $file) {
        if ($file !== '.' && $file !== '..' && is_dir("$extract_dir/$file")) {
            $source_dir = "$extract_dir/$file";
            break;
        }
    }

    if (!$source_dir) {
        log_message("Could not find extracted directory");
        send_response(500, 'Extraction failed - no directory found');
    }

    log_message("Source directory: $source_dir");

    // Get current app directory
    $app_dir = dirname(__FILE__);

    // Copy files from source to app directory, skipping preserved files
    log_message("Copying files from $source_dir to $app_dir");
    copy_dir_selective($source_dir, $app_dir, $PRESERVE_FILES);

    // Clean up temporary files
    @unlink($temp_file);
    remove_dir($extract_dir);

    log_message('Deployment completed successfully');
    send_response(200, 'Deployment successful');

} catch (Exception $e) {
    log_message('Error: ' . $e->getMessage());
    send_response(500, 'Deployment failed: ' . $e->getMessage());
}

/**
 * Recursively copy directory, excluding certain files/directories
 */
function copy_dir_selective($src, $dst, $exclude_items) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);

    while (false !== ($file = readdir($dir))) {
        if ($file != "." && $file != "..") {
            // Skip excluded files/directories
            if (in_array($file, $exclude_items)) {
                log_message("Skipping preserved item: $file");
                continue;
            }

            if (is_dir("$src/$file")) {
                copy_dir_selective("$src/$file", "$dst/$file", $exclude_items);
            } else {
                copy("$src/$file", "$dst/$file");
            }
        }
    }
    closedir($dir);
}

/**
 * Recursively remove directory
 */
function remove_dir($dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? remove_dir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
?>
