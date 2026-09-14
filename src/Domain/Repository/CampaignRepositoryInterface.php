<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Repository;

use FidelsPizza\Domain\Model\CampaignRecipient;
use FidelsPizza\Domain\Model\PromotionalCampaign;

interface CampaignRepositoryInterface
{
    /**
     * Persist a campaign and return its generated ID.
     */
    public function save(PromotionalCampaign $campaign): int;

    /**
     * Find a campaign by primary key.
     */
    public function findById(int $id): ?PromotionalCampaign;

    /**
     * Update campaign status and sent/failed counts.
     */
    public function updateCampaignStatus(
        int $campaignId,
        int $sentCount,
        int $failedCount,
        string $status,
        ?string $completedAt = null
    ): void;

    /**
     * Bulk insert recipient delivery records for a campaign.
     * @param CampaignRecipient[] $recipients
     */
    public function createRecipients(array $recipients): void;

    /**
     * Fetch pending recipients for batch delivery.
     * @return CampaignRecipient[]
     */
    public function findPendingRecipients(int $campaignId, int $limit = 10): array;

    /**
     * Update an individual recipient delivery log entry.
     */
    public function updateRecipient(CampaignRecipient $recipient): void;

    /**
     * Get recipients of a campaign with optional status filter and pagination.
     * @return CampaignRecipient[]
     */
    public function findRecipientsByCampaign(int $campaignId, ?string $status = null, int $limit = 100, int $offset = 0): array;

    /**
     * Get a list of past campaigns with pagination.
     * @return array<int, array<string, mixed>>
     */
    public function findAllCampaigns(int $limit = 20, int $offset = 0): array;

    /**
     * Count total campaigns for pagination.
     */
    public function countAllCampaigns(): int;
}
