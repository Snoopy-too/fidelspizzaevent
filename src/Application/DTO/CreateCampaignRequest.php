<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

use InvalidArgumentException;

final class CreateCampaignRequest
{
    /**
     * @param int $adminId
     * @param string $subject
     * @param string $bodyContent
     * @param int[] $selectedUserIds
     */
    public function __construct(
        public readonly int $adminId,
        public readonly string $subject,
        public readonly string $bodyContent,
        public readonly array $selectedUserIds = []
    ) {
        if ($this->adminId <= 0) {
            throw new InvalidArgumentException('Invalid admin ID.');
        }

        $cleanSubject = trim($this->subject);
        if ($cleanSubject === '') {
            throw new InvalidArgumentException('Campaign subject is required.');
        }

        if (preg_match('/[\r\n]/', $cleanSubject)) {
            throw new InvalidArgumentException('Subject line contains forbidden newline characters.');
        }

        if (trim($this->bodyContent) === '') {
            throw new InvalidArgumentException('Campaign body content is required.');
        }

        foreach ($this->selectedUserIds as $userId) {
            if (!is_int($userId) || $userId <= 0) {
                throw new InvalidArgumentException('Selected user IDs must be positive integers.');
            }
        }
    }
}
