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

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

$container = getServiceContainer();
$sendUseCase = $container->getSendPromotionalCampaignUseCase();
$campaignRepo = $container->getCampaignRepository();

try {
    switch ($action) {
        case 'create':
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

            $adminId = (int)($_SESSION['admin_id'] ?? 0);
            $subject = trim((string)($_POST['subject'] ?? ''));
            $bodyContent = trim((string)($_POST['body_content'] ?? ''));
            $rawUserIds = $_POST['user_ids'] ?? [];

            $selectedUserIds = [];
            if (is_array($rawUserIds)) {
                foreach ($rawUserIds as $uid) {
                    $id = (int)$uid;
                    if ($id > 0) {
                        $selectedUserIds[] = $id;
                    }
                }
            }

            $requestDto = new \FidelsPizza\Application\DTO\CreateCampaignRequest(
                adminId: $adminId,
                subject: $subject,
                bodyContent: $bodyContent,
                selectedUserIds: $selectedUserIds
            );

            $campaign = $sendUseCase->createCampaign($requestDto);

            echo json_encode([
                'status' => 'success',
                'campaign_id' => $campaign->getId(),
                'total_recipients' => $campaign->getTotalRecipients(),
                'message' => 'Campaign initialized successfully.'
            ]);
            break;

        case 'batch':
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

            $campaignId = (int)($_POST['campaign_id'] ?? 0);
            $batchSize = max(1, min(20, (int)($_POST['batch_size'] ?? 5)));

            if ($campaignId <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid campaign ID.']);
                exit;
            }

            $batchResult = $sendUseCase->processBatch($campaignId, $batchSize);

            echo json_encode([
                'status' => 'success',
                'data' => $batchResult->toArray()
            ]);
            break;

        case 'recipients':
            $campaignId = (int)($_GET['campaign_id'] ?? 0);
            if ($campaignId <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid campaign ID.']);
                exit;
            }

            $recipients = $campaignRepo->findRecipientsByCampaign($campaignId, null, 200);

            echo json_encode([
                'status' => 'success',
                'data' => $recipients
            ]);
            break;

        case 'save_template':
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

            $adminId = (int)($_SESSION['admin_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $subject = trim((string)($_POST['subject'] ?? ''));
            $bodyContent = trim((string)($_POST['body_content'] ?? ''));
            $templateId = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;

            $templateUseCase = $container->getManagePromotionalTemplatesUseCase();
            $saved = $templateUseCase->saveTemplate($adminId, $name, $subject, $bodyContent, $templateId);

            echo json_encode([
                'status' => 'success',
                'template' => $saved->toArray(),
                'message' => 'Template saved successfully.'
            ]);
            break;

        case 'delete_template':
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

            $templateId = (int)($_POST['template_id'] ?? 0);
            if ($templateId <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid template ID.']);
                exit;
            }

            $templateUseCase = $container->getManagePromotionalTemplatesUseCase();
            $deleted = $templateUseCase->deleteTemplate($templateId);

            echo json_encode([
                'status' => $deleted ? 'success' : 'error',
                'message' => $deleted ? 'Template deleted successfully.' : 'Could not delete template.'
            ]);
            break;

        case 'list_templates':
            $templateUseCase = $container->getManagePromotionalTemplatesUseCase();
            $templates = $templateUseCase->getAllTemplates();

            echo json_encode([
                'status' => 'success',
                'templates' => $templates
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
