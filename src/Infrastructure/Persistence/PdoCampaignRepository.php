<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Persistence;

use FidelsPizza\Domain\Model\CampaignRecipient;
use FidelsPizza\Domain\Model\PromotionalCampaign;
use FidelsPizza\Domain\Repository\CampaignRepositoryInterface;
use PDO;

final class PdoCampaignRepository implements CampaignRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function save(PromotionalCampaign $campaign): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `promotional_campaigns` (
                `admin_id`, `subject`, `body_content`, `total_recipients`, 
                `sent_count`, `failed_count`, `status`, `created_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $campaign->getAdminId(),
            $campaign->getSubject(),
            $campaign->getBodyContent(),
            $campaign->getTotalRecipients(),
            $campaign->getSentCount(),
            $campaign->getFailedCount(),
            $campaign->getStatus(),
            $campaign->getCreatedAt() ?? date('Y-m-d H:i:s')
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $id): ?PromotionalCampaign
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `promotional_campaigns` WHERE `id` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new PromotionalCampaign(
            id: (int)$row['id'],
            adminId: (int)$row['admin_id'],
            subject: (string)$row['subject'],
            bodyContent: (string)$row['body_content'],
            totalRecipients: (int)$row['total_recipients'],
            sentCount: (int)$row['sent_count'],
            failedCount: (int)$row['failed_count'],
            status: (string)$row['status'],
            createdAt: (string)$row['created_at'],
            completedAt: $row['completed_at'] ? (string)$row['completed_at'] : null
        );
    }

    public function updateCampaignStatus(
        int $campaignId,
        int $sentCount,
        int $failedCount,
        string $status,
        ?string $completedAt = null
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE `promotional_campaigns` 
            SET `sent_count` = ?, `failed_count` = ?, `status` = ?, `completed_at` = ?
            WHERE `id` = ?
        ");
        $stmt->execute([$sentCount, $failedCount, $status, $completedAt, $campaignId]);
    }

    public function createRecipients(array $recipients): void
    {
        if (empty($recipients)) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `promotional_campaign_recipients` (
                    `campaign_id`, `user_id`, `email_sent_to`, `status`, `error_message`, `sent_at`
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($recipients as $recipient) {
                $stmt->execute([
                    $recipient->getCampaignId(),
                    $recipient->getUserId(),
                    $recipient->getEmailSentTo(),
                    $recipient->getStatus(),
                    $recipient->getErrorMessage(),
                    $recipient->getSentAt()
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findPendingRecipients(int $campaignId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `promotional_campaign_recipients` 
            WHERE `campaign_id` = ? AND `status` = 'pending'
            ORDER BY `id` ASC 
            LIMIT " . (int)$limit
        );
        $stmt->execute([$campaignId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = new CampaignRecipient(
                id: (int)$row['id'],
                campaignId: (int)$row['campaign_id'],
                userId: (int)$row['user_id'],
                emailSentTo: (string)$row['email_sent_to'],
                status: (string)$row['status'],
                errorMessage: $row['error_message'] ? (string)$row['error_message'] : null,
                sentAt: $row['sent_at'] ? (string)$row['sent_at'] : null
            );
        }

        return $results;
    }

    public function updateRecipient(CampaignRecipient $recipient): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `promotional_campaign_recipients` 
            SET `status` = ?, `error_message` = ?, `sent_at` = ?
            WHERE `campaign_id` = ? AND `user_id` = ?
        ");
        $stmt->execute([
            $recipient->getStatus(),
            $recipient->getErrorMessage(),
            $recipient->getSentAt(),
            $recipient->getCampaignId(),
            $recipient->getUserId()
        ]);
    }

    public function findRecipientsByCampaign(int $campaignId, ?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $sql = "
            SELECT r.*, u.first_name, u.last_name 
            FROM `promotional_campaign_recipients` r
            LEFT JOIN `users` u ON r.user_id = u.id
            WHERE r.campaign_id = ?
        ";
        $params = [$campaignId];

        if ($status !== null && $status !== '') {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY r.id ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllCampaigns(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, a.username as admin_username 
            FROM `promotional_campaigns` c
            LEFT JOIN `admins` a ON c.admin_id = a.id
            ORDER BY c.created_at DESC 
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAllCampaigns(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM `promotional_campaigns`");
        return (int)$stmt->fetchColumn();
    }
}
