<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => __('invalid_csrf_token')]);
    exit;
}

$subject = trim((string)($_POST['subject'] ?? ''));
$bodyContent = trim((string)($_POST['body_content'] ?? ''));
$sampleUserId = !empty($_POST['sample_user_id']) ? (int)$_POST['sample_user_id'] : null;

if ($subject === '') {
    echo json_encode(['status' => 'error', 'message' => 'Subject cannot be empty.']);
    exit;
}

if (preg_match('/[\r\n]/', $subject)) {
    echo json_encode(['status' => 'error', 'message' => 'Subject contains forbidden newline characters.']);
    exit;
}

if ($bodyContent === '') {
    echo json_encode(['status' => 'error', 'message' => 'Message body cannot be empty.']);
    exit;
}

try {
    $container = getServiceContainer();
    $previewUseCase = $container->getPreviewCampaignUseCase();

    $preview = $previewUseCase->preview($subject, $bodyContent, $sampleUserId);

    echo json_encode([
        'status' => 'success',
        'data' => $preview
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to generate preview: ' . $e->getMessage()
    ]);
}
