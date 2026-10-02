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

    private ?\FidelsPizza\Domain\Repository\PickupTimeSlotRepositoryInterface $pickupTimeSlotRepository = null;
    private ?\FidelsPizza\Application\UseCase\ManagePickupTimeSlotsUseCase $managePickupTimeSlotsUseCase = null;

    public function getPickupTimeSlotRepository(): \FidelsPizza\Domain\Repository\PickupTimeSlotRepositoryInterface
    {
        if ($this->pickupTimeSlotRepository === null) {
            $this->pickupTimeSlotRepository = new \FidelsPizza\Infrastructure\Persistence\PdoPickupTimeSlotRepository($this->pdo);
        }
        return $this->pickupTimeSlotRepository;
    }

    public function getManagePickupTimeSlotsUseCase(): \FidelsPizza\Application\UseCase\ManagePickupTimeSlotsUseCase
    {
        if ($this->managePickupTimeSlotsUseCase === null) {
            $this->managePickupTimeSlotsUseCase = new \FidelsPizza\Application\UseCase\ManagePickupTimeSlotsUseCase(
                $this->getPickupTimeSlotRepository()
            );
        }
        return $this->managePickupTimeSlotsUseCase;
    }

    private ?\FidelsPizza\Application\UseCase\SendOrderNotificationUseCase $sendOrderNotificationUseCase = null;

    public function getSendOrderNotificationUseCase(): \FidelsPizza\Application\UseCase\SendOrderNotificationUseCase
    {
        if ($this->sendOrderNotificationUseCase === null) {
            $this->sendOrderNotificationUseCase = new \FidelsPizza\Application\UseCase\SendOrderNotificationUseCase(
                $this->pdo,
                $this->getPickupTimeSlotRepository(),
                $this->getEmailSender()
            );
        }
        return $this->sendOrderNotificationUseCase;
    }

    private ?\FidelsPizza\Domain\Repository\PasswordResetRepositoryInterface $passwordResetRepository = null;
    private ?\FidelsPizza\Application\UseCase\RequestPasswordResetUseCase $requestPasswordResetUseCase = null;
    private ?\FidelsPizza\Application\UseCase\ResetPasswordUseCase $resetPasswordUseCase = null;

    public function getPasswordResetRepository(): \FidelsPizza\Domain\Repository\PasswordResetRepositoryInterface
    {
        if ($this->passwordResetRepository === null) {
            $this->passwordResetRepository = new \FidelsPizza\Infrastructure\Persistence\PdoPasswordResetRepository($this->pdo);
        }
        return $this->passwordResetRepository;
    }

    public function getRequestPasswordResetUseCase(
        ?\FidelsPizza\Domain\Service\EmailSenderInterface $overrideEmailSender = null
    ): \FidelsPizza\Application\UseCase\RequestPasswordResetUseCase {
        if ($overrideEmailSender !== null) {
            return new \FidelsPizza\Application\UseCase\RequestPasswordResetUseCase(
                $this->getPasswordResetRepository(),
                $overrideEmailSender,
                $this->siteConfig
            );
        }
        if ($this->requestPasswordResetUseCase === null) {
            $this->requestPasswordResetUseCase = new \FidelsPizza\Application\UseCase\RequestPasswordResetUseCase(
                $this->getPasswordResetRepository(),
                $this->getEmailSender(),
                $this->siteConfig
            );
        }
        return $this->requestPasswordResetUseCase;
    }

    public function getResetPasswordUseCase(): \FidelsPizza\Application\UseCase\ResetPasswordUseCase
    {
        if ($this->resetPasswordUseCase === null) {
            $this->resetPasswordUseCase = new \FidelsPizza\Application\UseCase\ResetPasswordUseCase(
                $this->getPasswordResetRepository()
            );
        }
        return $this->resetPasswordUseCase;
    }

    private ?\FidelsPizza\Application\UseCase\SendRegistrationConfirmationUseCase $sendRegistrationConfirmationUseCase = null;

    public function getSendRegistrationConfirmationUseCase(
        ?\FidelsPizza\Domain\Service\EmailSenderInterface $overrideEmailSender = null
    ): \FidelsPizza\Application\UseCase\SendRegistrationConfirmationUseCase {
        if ($overrideEmailSender !== null) {
            return new \FidelsPizza\Application\UseCase\SendRegistrationConfirmationUseCase(
                $overrideEmailSender,
                $this->siteConfig
            );
        }
        if ($this->sendRegistrationConfirmationUseCase === null) {
            $this->sendRegistrationConfirmationUseCase = new \FidelsPizza\Application\UseCase\SendRegistrationConfirmationUseCase(
                $this->getEmailSender(),
                $this->siteConfig
            );
        }
        return $this->sendRegistrationConfirmationUseCase;
    }

    private ?\FidelsPizza\Domain\Service\OrderWindowService $orderWindowService = null;

    public function getOrderWindowService(): \FidelsPizza\Domain\Service\OrderWindowService
    {
        if ($this->orderWindowService === null) {
            $this->orderWindowService = new \FidelsPizza\Domain\Service\OrderWindowService();
        }
        return $this->orderWindowService;
    }
}
