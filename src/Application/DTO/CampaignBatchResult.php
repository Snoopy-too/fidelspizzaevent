<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

final class CampaignBatchResult
{
    /**
     * @param int $campaignId
     * @param int $totalRecipients
     * @param int $processedInBatch
     * @param int $totalSent
     * @param int $totalFailed
     * @param int $remainingCount
     * @param bool $isFinished
     * @param string[] $errors
     */
    public function __construct(
        public readonly int $campaignId,
        public readonly int $totalRecipients,
        public readonly int $processedInBatch,
        public readonly int $totalSent,
        public readonly int $totalFailed,
        public readonly int $remainingCount,
        public readonly bool $isFinished,
        public readonly array $errors = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'total_recipients' => $this->totalRecipients,
            'processed_in_batch' => $this->processedInBatch,
            'total_sent' => $this->totalSent,
            'total_failed' => $this->totalFailed,
            'remaining_count' => $this->remainingCount,
            'is_finished' => $this->isFinished,
            'errors' => $this->errors,
            'percentage' => $this->totalRecipients > 0
                ? (int)round((($this->totalSent + $this->totalFailed) / $this->totalRecipients) * 100)
                : 100
        ];
    }
}
