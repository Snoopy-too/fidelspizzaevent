<?php
/**
 * GitHub Webhook Deployment Script for Bluehost
 *
 * This script listens for GitHub push webhooks and automatically pulls the latest
 * code from the GitHub repository to your Bluehost hosting.
 *
 * SETUP INSTRUCTIONS:
 * 1. Upload this file to your Bluehost public_html directory
 * 2. Create a webhook in your GitHub repository:
 *    - Go to Settings > Webhooks > Add webhook
 *    - Payload URL: https://yoursite.com/deploy.php
 *    - Content type: application/json
 *    - Secret: [your-secure-secret-here]
 *    - Events: Push events
 * 3. Update the SECRET variable below with the same secret you used in GitHub
 * 4. Update REPO_PATH to point to your application directory
 */

// Configuration
define('SECRET', 'your-webhook-secret-here'); // Change this to a secure secret
define('REPO_PATH', '/home/your_cpanel_username/public_html/fidelspizzaevent'); // Update this path
define('LOG_FILE', '/home/your_cpanel_username/public_html/fidelspizzaevent/deploy.log');
define('BRANCH', 'master'); // The branch to pull from

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);

/**
 * Log messages to deployment log file
 */
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";

    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "");
    }
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

/**
 * Verify GitHub webhook signature
 */
function verify_signature($payload, $signature) {
    $hash = 'sha256=' . hash_hmac('sha256', $payload, SECRET);
    return hash_equals($hash, $signature);
}

/**
 * Execute shell command and return output
 */
function execute_command($command, $cwd) {
    $output = array();
    $return_var = 0;
    exec("cd $cwd && $command 2>&1", $output, $return_var);
    return array(
        'output' => implode("\n", $output),
        'return_code' => $return_var
    );
}

// Get the request payload and signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Verify it's a valid GitHub webhook
if (!$signature) {
    log_message('ERROR: Missing X-Hub-Signature-256 header');
    http_response_code(403);
    die('Forbidden');
}

if (!verify_signature($payload, $signature)) {
    log_message('ERROR: Invalid webhook signature');
    http_response_code(403);
    die('Forbidden');
}

// Only process push events
$data = json_decode($payload, true);
if (!isset($data['ref']) || $data['ref'] !== "refs/heads/" . BRANCH) {
    log_message('INFO: Webhook received but not for branch: ' . ($data['ref'] ?? 'unknown'));
    http_response_code(200);
    die('OK - Not the target branch');
}

log_message('---START DEPLOYMENT---');
log_message('Received push event for branch: ' . BRANCH);

// Check if repository directory exists
if (!is_dir(REPO_PATH)) {
    log_message('ERROR: Repository path does not exist: ' . REPO_PATH);
    http_response_code(500);
    die('Repository path not found');
}

// Check if .git directory exists
if (!is_dir(REPO_PATH . '/.git')) {
    log_message('ERROR: .git directory not found. Repository not initialized.');
    http_response_code(500);
    die('Not a git repository');
}

// Fetch latest changes
log_message('Running: git fetch origin ' . BRANCH);
$result = execute_command('git fetch origin ' . BRANCH, REPO_PATH);
log_message('Output: ' . $result['output']);
if ($result['return_code'] !== 0) {
    log_message('ERROR: git fetch failed with code: ' . $result['return_code']);
    http_response_code(500);
    die('git fetch failed');
}

// Reset to latest remote version
log_message('Running: git reset --hard origin/' . BRANCH);
$result = execute_command('git reset --hard origin/' . BRANCH, REPO_PATH);
log_message('Output: ' . $result['output']);
if ($result['return_code'] !== 0) {
    log_message('ERROR: git reset failed with code: ' . $result['return_code']);
    http_response_code(500);
    die('git reset failed');
}

// Check if config.php exists (it should on production)
if (!file_exists(REPO_PATH . '/config.php')) {
    log_message('WARNING: config.php not found. Make sure it exists and is properly configured.');
}

// Get current commit info
log_message('Running: git log -1 --oneline');
$result = execute_command('git log -1 --oneline', REPO_PATH);
log_message('Current commit: ' . $result['output']);

log_message('---DEPLOYMENT COMPLETE---');

// Return success response
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(array(
    'status' => 'success',
    'message' => 'Deployment completed successfully'
));
?>
