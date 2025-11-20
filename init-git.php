<?php
/**
 * Git Initialization Script for Bluehost Shared Hosting
 *
 * This script initializes git on your Bluehost server without needing SSH access.
 *
 * SECURITY: Delete this file after it completes successfully!
 */

// Disable output buffering to see live progress
while (ob_get_level()) {
    ob_end_clean();
}

// Configuration
$repo_path = '/home/mxbttmmy/public_html/fidelspizzaevent';
$github_url = 'https://github.com/Snoopy-too/fidelspizzaevent.git';

echo "<pre style='background: #f0f0f0; padding: 15px; font-family: monospace;'>";
echo "=== Git Initialization for Bluehost ===\n\n";

// Function to execute commands
function run_command($command, $cwd) {
    echo "Running: <strong>$command</strong>\n";

    $output = array();
    $return_var = 0;

    // Use proc_open for better control
    $descriptors = array(
        0 => array("pipe", "r"),  // stdin
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w")   // stderr
    );

    $process = proc_open("cd $cwd && $command 2>&1", $descriptors, $pipes);

    if (is_resource($process)) {
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $return_var = proc_close($process);
    } else {
        $output = "Failed to execute command";
        $return_var = 1;
    }

    echo $output;
    echo "\n";

    return $return_var;
}

// Check if directory exists
if (!is_dir($repo_path)) {
    echo "ERROR: Directory does not exist: $repo_path\n";
    echo "</pre>";
    exit;
}

echo "Repository path: $repo_path\n\n";

// Step 1: Check if .git already exists
if (is_dir($repo_path . '/.git')) {
    echo "✓ .git directory already exists. Updating remote...\n\n";
    run_command("git remote update origin", $repo_path);
} else {
    echo "Step 1: Initialize git repository...\n";
    $result = run_command("git init", $repo_path);
    if ($result !== 0) {
        echo "ERROR: git init failed\n";
        echo "</pre>";
        exit;
    }
    echo "✓ Git repository initialized\n\n";

    echo "Step 2: Add GitHub remote...\n";
    $result = run_command("git remote add origin $github_url", $repo_path);
    if ($result !== 0) {
        echo "ERROR: Could not add remote\n";
        echo "</pre>";
        exit;
    }
    echo "✓ GitHub remote added\n\n";
}

echo "Step 3: Fetch latest code from GitHub...\n";
$result = run_command("git fetch origin master", $repo_path);
if ($result !== 0) {
    echo "WARNING: git fetch may have issues, but continuing...\n\n";
}

echo "Step 4: Checkout master branch...\n";
$result = run_command("git checkout -b master origin/master", $repo_path);
if ($result !== 0) {
    echo "WARNING: Could not create local branch, trying to checkout existing...\n";
    $result = run_command("git checkout master", $repo_path);
    if ($result !== 0) {
        echo "ERROR: Could not checkout master branch\n";
        echo "</pre>";
        exit;
    }
}
echo "✓ Master branch checked out\n\n";

echo "Step 5: Reset to latest code...\n";
$result = run_command("git reset --hard origin/master", $repo_path);
if ($result !== 0) {
    echo "ERROR: Could not reset repository\n";
    echo "</pre>";
    exit;
}
echo "✓ Repository reset to latest version\n\n";

echo "Step 6: Verify setup...\n";
run_command("git log -1 --oneline", $repo_path);

// Check if config.php exists
if (file_exists($repo_path . '/config.php')) {
    echo "✓ config.php exists\n";
} else {
    echo "⚠ WARNING: config.php not found. You need to copy it from config.php.example\n";
}

echo "\n=== SUCCESS ===\n";
echo "Git has been initialized on your Bluehost server!\n\n";
echo "NEXT STEPS:\n";
echo "1. Delete this file (init-git.php) for security\n";
echo "2. Test the webhook by making a commit locally and pushing to GitHub\n";
echo "3. The deploy.php script should now automatically pull changes\n";
echo "</pre>";
?>
