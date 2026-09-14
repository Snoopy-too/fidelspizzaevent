<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Domain\Model\MarketingUser;
use FidelsPizza\Domain\Repository\PromotionalUserRepositoryInterface;

final class PreviewCampaignUseCase
{
    /**
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly PromotionalUserRepositoryInterface $userRepository,
        private readonly SendPromotionalCampaignUseCase $senderUseCase,
        private readonly array $siteConfig
    ) {
    }

    /**
     * @return array{subject: string, html: string, text: string, sample_user: string}
     */
    public function preview(string $subject, string $bodyContent, ?int $sampleUserId = null): array
    {
        $user = null;
        if ($sampleUserId !== null && $sampleUserId > 0) {
            $users = $this->userRepository->findByIds([$sampleUserId]);
            if (!empty($users)) {
                $user = $users[0];
            }
        }

        if (!$user) {
            $eligible = $this->userRepository->findAllEligible();
            if (!empty($eligible)) {
                $user = $eligible[0];
            } else {
                $user = new MarketingUser(
                    id: 999,
                    email: 'sample.customer@example.com',
                    firstName: 'Taro',
                    lastName: 'Yamada',
                    phone: '090-1234-5678',
                    isConfirmed: true,
                    acceptsMarketing: true,
                    unsubscribeToken: 'sample_token_preview_only'
                );
            }
        }

        // Render subject placeholders
        $siteTitle = (string)($this->siteConfig['site_title'] ?? "Fidel's Pizza Event");
        $eventDate = (string)($this->siteConfig['event_date'] ?? 'Upcoming');
        $subjectPreview = str_replace(
            ['{{first_name}}', '{{last_name}}', '{{full_name}}', '{{site_title}}', '{{event_date}}'],
            [$user->getFirstName(), $user->getLastName(), $user->getFullName(), $siteTitle, $eventDate],
            $subject
        );

        $rendered = $this->senderUseCase->renderEmailContent($bodyContent, $user);

        return [
            'subject' => $subjectPreview,
            'html' => $rendered['html'],
            'text' => $rendered['text'],
            'sample_user' => $user->getFullName() . ' (' . $user->getEmail() . ')'
        ];
    }
}
