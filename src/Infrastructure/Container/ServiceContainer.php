<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Container;

use FidelsPizza\Application\UseCase\PreviewCampaignUseCase;
use FidelsPizza\Application\UseCase\SendPromotionalCampaignUseCase;
use FidelsPizza\Application\UseCase\UnsubscribeUserUseCase;
use FidelsPizza\Domain\Repository\CampaignRepositoryInterface;
use FidelsPizza\Domain\Repository\PromotionalUserRepositoryInterface;
use FidelsPizza\Domain\Service\EmailSenderInterface;
use FidelsPizza\Infrastructure\Mail\NativePhpEmailSender;
use FidelsPizza\Infrastructure\Persistence\PdoCampaignRepository;
use FidelsPizza\Infrastructure\Persistence\PdoPromotionalUserRepository;
use PDO;

final class ServiceContainer
{
    private ?CampaignRepositoryInterface $campaignRepository = null;
    private ?PromotionalUserRepositoryInterface $userRepository = null;
    private ?EmailSenderInterface $emailSender = null;
    private ?SendPromotionalCampaignUseCase $sendPromotionalCampaignUseCase = null;
    private ?PreviewCampaignUseCase $previewCampaignUseCase = null;
    private ?UnsubscribeUserUseCase $unsubscribeUserUseCase = null;

    /**
     * @param PDO $pdo
     * @param array<string, mixed> $siteConfig
     * @param string $fromEmail
     * @param string $fromName
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $siteConfig,
        private readonly string $fromEmail = 'noreply@yoursite.com',
        private readonly string $fromName = "Fidel's Pizza Event"
    ) {
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getSiteConfig(): array
    {
        return $this->siteConfig;
    }

    public function getCampaignRepository(): CampaignRepositoryInterface
    {
        if ($this->campaignRepository === null) {
            $this->campaignRepository = new PdoCampaignRepository($this->pdo);
        }
        return $this->campaignRepository;
    }

    public function getUserRepository(): PromotionalUserRepositoryInterface
    {
        if ($this->userRepository === null) {
            $this->userRepository = new PdoPromotionalUserRepository($this->pdo);
        }
        return $this->userRepository;
    }

    public function getEmailSender(): EmailSenderInterface
    {
        if ($this->emailSender === null) {
            $this->emailSender = new NativePhpEmailSender($this->fromEmail, $this->fromName);
        }
        return $this->emailSender;
    }

    public function getSendPromotionalCampaignUseCase(): SendPromotionalCampaignUseCase
    {
        if ($this->sendPromotionalCampaignUseCase === null) {
            $this->sendPromotionalCampaignUseCase = new SendPromotionalCampaignUseCase(
                $this->getCampaignRepository(),
                $this->getUserRepository(),
                $this->getEmailSender(),
                $this->siteConfig
            );
        }
        return $this->sendPromotionalCampaignUseCase;
    }

    public function getPreviewCampaignUseCase(): PreviewCampaignUseCase
    {
        if ($this->previewCampaignUseCase === null) {
            $this->previewCampaignUseCase = new PreviewCampaignUseCase(
                $this->getUserRepository(),
                $this->getSendPromotionalCampaignUseCase(),
                $this->siteConfig
            );
        }
        return $this->previewCampaignUseCase;
    }

    public function getUnsubscribeUserUseCase(): UnsubscribeUserUseCase
    {
        if ($this->unsubscribeUserUseCase === null) {
            $this->unsubscribeUserUseCase = new UnsubscribeUserUseCase(
                $this->getUserRepository()
            );
        }
        return $this->unsubscribeUserUseCase;
    }

    private ?\FidelsPizza\Domain\Repository\PromotionalTemplateRepositoryInterface $templateRepository = null;
    private ?\FidelsPizza\Application\UseCase\ManagePromotionalTemplatesUseCase $managePromotionalTemplatesUseCase = null;

    public function getTemplateRepository(): \FidelsPizza\Domain\Repository\PromotionalTemplateRepositoryInterface
    {
        if ($this->templateRepository === null) {
            $this->templateRepository = new \FidelsPizza\Infrastructure\Persistence\PdoPromotionalTemplateRepository($this->pdo);
        }
        return $this->templateRepository;
    }

    public function getManagePromotionalTemplatesUseCase(): \FidelsPizza\Application\UseCase\ManagePromotionalTemplatesUseCase
    {
        if ($this->managePromotionalTemplatesUseCase === null) {
            $this->managePromotionalTemplatesUseCase = new \FidelsPizza\Application\UseCase\ManagePromotionalTemplatesUseCase(
                $this->getTemplateRepository()
            );
        }
        return $this->managePromotionalTemplatesUseCase;
    }
}
