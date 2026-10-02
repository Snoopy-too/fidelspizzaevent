<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Application\DTO\CampaignBatchResult;
use FidelsPizza\Application\DTO\CreateCampaignRequest;
use FidelsPizza\Domain\Model\CampaignRecipient;
use FidelsPizza\Domain\Model\MarketingUser;
use FidelsPizza\Domain\Model\PromotionalCampaign;
use FidelsPizza\Domain\Repository\CampaignRepositoryInterface;
use FidelsPizza\Domain\Repository\PromotionalUserRepositoryInterface;
use FidelsPizza\Domain\Service\EmailSenderInterface;
use RuntimeException;

final class SendPromotionalCampaignUseCase
{
    /**
     * @param array<string, mixed> $siteConfig
     */
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly PromotionalUserRepositoryInterface $userRepository,
        private readonly EmailSenderInterface $emailSender,
        private readonly array $siteConfig
    ) {
    }

    /**
     * Create campaign entity and recipient list.
     */
    public function createCampaign(CreateCampaignRequest $request): PromotionalCampaign
    {
        // 1. Fetch targeted users
        if (!empty($request->selectedUserIds)) {
            $targetedUsers = $this->userRepository->findByIds($request->selectedUserIds);
        } else {
            $targetedUsers = $this->userRepository->findAllEligible();
        }

        if (empty($targetedUsers)) {
            throw new RuntimeException('No registered users found for the selected criteria.');
        }

        // 2. Count total recipients
        $totalRecipients = count($targetedUsers);

        // 3. Instantiate campaign
        $campaign = new PromotionalCampaign(
            id: null,
            adminId: $request->adminId,
            subject: $request->subject,
            bodyContent: $request->bodyContent,
            totalRecipients: $totalRecipients,
            sentCount: 0,
            failedCount: 0,
            status: PromotionalCampaign::STATUS_SENDING,
            createdAt: date('Y-m-d H:i:s')
        );

        $campaignId = $this->campaignRepository->save($campaign);

        // 4. Create recipient records
        $recipientEntities = [];
        foreach ($targetedUsers as $user) {
            $recipient = new CampaignRecipient(
                id: null,
                campaignId: $campaignId,
                userId: $user->getId(),
                emailSentTo: $user->getEmail(),
                status: CampaignRecipient::STATUS_PENDING
            );

            // If user opted out, mark immediately as opted_out
            if (!$user->acceptsMarketing()) {
                $recipient->markOptedOut();
            }

            $recipientEntities[] = $recipient;
        }

        $this->campaignRepository->createRecipients($recipientEntities);

        return $this->campaignRepository->findById($campaignId)
            ?? throw new RuntimeException("Failed to reload created campaign with ID $campaignId.");
    }

    /**
     * Process a batch of pending recipients for a campaign.
     */
    public function processBatch(int $campaignId, int $batchSize = 10): CampaignBatchResult
    {
        $campaign = $this->campaignRepository->findById($campaignId);
        if (!$campaign) {
            throw new RuntimeException("Campaign not found with ID $campaignId.");
        }

        $pendingRecipients = $this->campaignRepository->findPendingRecipients($campaignId, $batchSize);
        $errors = [];
        $processedInBatch = count($pendingRecipients);

        if ($processedInBatch > 0) {
            $userIds = array_map(fn(CampaignRecipient $r) => $r->getUserId(), $pendingRecipients);
            $users = $this->userRepository->findByIds($userIds);
            $userMap = [];
            foreach ($users as $u) {
                $userMap[$u->getId()] = $u;
            }

            foreach ($pendingRecipients as $recipient) {
                $user = $userMap[$recipient->getUserId()] ?? null;
                if (!$user) {
                    $recipient->markFailed('User profile not found.');
                    $this->campaignRepository->updateRecipient($recipient);
                    $campaign->recordFailed();
                    $errors[] = "User #{$recipient->getUserId()} not found.";
                    continue;
                }

                // Check opt-out again right before sending
                if (!$user->acceptsMarketing()) {
                    $recipient->markOptedOut();
                    $this->campaignRepository->updateRecipient($recipient);
                    $campaign->recordFailed();
                    continue;
                }

                $rendered = $this->renderEmailContent($campaign->getBodyContent(), $user);
                $personalizedSubject = $this->renderSubject($campaign->getSubject(), $user);

                try {
                    $success = $this->emailSender->send(
                        toEmail: $user->getEmail(),
                        toName: $user->getFullName(),
                        subject: $personalizedSubject,
                        htmlBody: $rendered['html'],
                        textBody: $rendered['text']
                    );

                    if ($success) {
                        $recipient->markSent();
                        $campaign->recordSent();
                    } else {
                        $errorMsg = 'Mail transport rejected delivery.';
                        $recipient->markFailed($errorMsg);
                        $campaign->recordFailed();
                        $errors[] = "Failed sending to {$user->getEmail()}: $errorMsg";
                    }
                } catch (\Throwable $e) {
                    $recipient->markFailed($e->getMessage());
                    $campaign->recordFailed();
                    $errors[] = "Error sending to {$user->getEmail()}: " . $e->getMessage();
                }

                $this->campaignRepository->updateRecipient($recipient);
            }
        }

        // Check if there are remaining pending recipients
        $remainingPending = $this->campaignRepository->findPendingRecipients($campaignId, 1);
        $isFinished = empty($remainingPending);

        $newStatus = $isFinished
            ? ($campaign->getSentCount() > 0 ? PromotionalCampaign::STATUS_COMPLETED : PromotionalCampaign::STATUS_FAILED)
            : PromotionalCampaign::STATUS_SENDING;

        $completedAt = $isFinished ? date('Y-m-d H:i:s') : null;

        $this->campaignRepository->updateCampaignStatus(
            campaignId: $campaignId,
            sentCount: $campaign->getSentCount(),
            failedCount: $campaign->getFailedCount(),
            status: $newStatus,
            completedAt: $completedAt
        );

        $remainingCount = max(0, $campaign->getTotalRecipients() - ($campaign->getSentCount() + $campaign->getFailedCount()));

        return new CampaignBatchResult(
            campaignId: $campaignId,
            totalRecipients: $campaign->getTotalRecipients(),
            processedInBatch: $processedInBatch,
            totalSent: $campaign->getSentCount(),
            totalFailed: $campaign->getFailedCount(),
            remainingCount: $remainingCount,
            isFinished: $isFinished,
            errors: $errors
        );
    }

    /**
     * Resolve the base site URL, honoring configured domain or dynamically resolving if default placeholder is used.
     */
    public function resolveBaseUrl(): string
    {
        $configured = trim((string)($this->siteConfig['site_url'] ?? ''));
        if ($configured !== '' && $configured !== 'https://yoursite.com') {
            return rtrim($configured, '/');
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '' && preg_match('/^[a-zA-Z0-9.\-:]+$/', $host)) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $scheme = $isHttps ? 'https' : 'http';
            $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
            if (str_ends_with($scriptDir, '/admin')) {
                $scriptDir = substr($scriptDir, 0, -6);
            }
            $scriptDir = rtrim($scriptDir, '/');
            return $scheme . '://' . $host . $scriptDir;
        }

        return $configured !== '' ? rtrim($configured, '/') : 'http://localhost/fidelspizzaevent';
    }

    /**
     * Render subject line with dynamic personalization tokens safely.
     */
    public function renderSubject(string $rawSubject, MarketingUser $user): string
    {
        $siteTitle = (string)($this->siteConfig['site_title'] ?? "Fidel's Pizza Event");
        $siteUrl = $this->resolveBaseUrl();
        $eventDate = (string)($this->siteConfig['event_date'] ?? 'Upcoming');
        $eventLocation = (string)($this->siteConfig['event_location'] ?? 'Fidel\'s Pizza');

        $tokens = [
            '{{first_name}}' => $user->getFirstName(),
            '{{last_name}}' => $user->getLastName(),
            '{{full_name}}' => $user->getFullName(),
            '{{email}}' => $user->getEmail(),
            '{{phone}}' => $user->getPhone() ?? '',
            '{{site_title}}' => $siteTitle,
            '{{site_url}}' => $siteUrl,
            '{{event_date}}' => $eventDate,
            '{{event_location}}' => $eventLocation,
        ];

        return str_replace(array_keys($tokens), array_values($tokens), $rawSubject);
    }

    /**
     * Render message content with dynamic personalization tokens and legal unsubscribe footer.
     * @return array{html: string, text: string}
     */
    public function renderEmailContent(string $rawContent, MarketingUser $user): array
    {
        $siteUrl = $this->resolveBaseUrl();
        $siteTitle = (string)($this->siteConfig['site_title'] ?? "Fidel's Pizza Event");
        $eventDate = (string)($this->siteConfig['event_date'] ?? 'Upcoming');
        $eventLocation = (string)($this->siteConfig['event_location'] ?? 'Fidel\'s Pizza');

        $unsubscribeToken = $user->getUnsubscribeToken() ?? '';
        $unsubscribeUrl = $siteUrl . '/unsubscribe.php?token=' . urlencode($unsubscribeToken);

        $placeholders = [
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'full_name' => $user->getFullName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone() ?? '',
            'site_title' => $siteTitle,
            'site_url' => $siteUrl,
            'event_date' => $eventDate,
            'event_location' => $eventLocation,
            'unsubscribe_link' => $unsubscribeUrl,
        ];

        $renderedText = $rawContent;
        foreach ($placeholders as $key => $val) {
            $renderedText = str_replace('{{' . $key . '}}', (string)$val, $renderedText);
        }

        // If unsubscribe link wasn't explicitly used, append standard legal compliance footer
        if (!str_contains($rawContent, '{{unsubscribe_link}}')) {
            $renderedText .= "\n\n---\n";
            $renderedText .= "To unsubscribe from promotional emails, click here: " . $unsubscribeUrl;
        }

        // Render HTML counterpart
        $escapedBody = nl2br(htmlspecialchars($renderedText, ENT_QUOTES, 'UTF-8'));
        // Convert any plain URLs into clickable links safely
        $escapedBody = preg_replace(
            '/(https?:\/\/[^\s<]+)/',
            '<a href="$1" style="color: #e74c3c; text-decoration: underline;" target="_blank" rel="noopener noreferrer">$1</a>',
            $escapedBody
        );

        $htmlLayout = <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$siteTitle}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 20px; color: #333333; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
        <tr>
            <td style="background-color: #d32f2f; padding: 24px; text-align: center; color: #ffffff;">
                <h1 style="margin: 0; font-size: 22px; font-weight: bold; letter-spacing: 0.5px;">
                    <a href="{$siteUrl}" style="color: #ffffff; text-decoration: none; display: inline-block;" target="_blank" rel="noopener noreferrer">🍕 {$siteTitle}</a>
                </h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px 24px; font-size: 15px;">
                {$escapedBody}
            </td>
        </tr>
        <tr>
            <td style="background-color: #f1f3f5; padding: 18px 24px; text-align: center; font-size: 12px; color: #6c757d; border-top: 1px solid #e9ecef;">
                <p style="margin: 0 0 6px 0;"><a href="{$siteUrl}" style="color: #495057; text-decoration: underline;" target="_blank" rel="noopener noreferrer"><strong>{$siteTitle}</strong></a> | {$eventLocation}</p>
                <p style="margin: 0;">
                    <a href="{$unsubscribeUrl}" style="color: #6c757d; text-decoration: underline;" target="_blank">Unsubscribe / 配信停止</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        return [
            'html' => $htmlLayout,
            'text' => $renderedText
        ];
    }
}
