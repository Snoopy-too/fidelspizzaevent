<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Domain\Model\PromotionalTemplate;
use FidelsPizza\Domain\Repository\PromotionalTemplateRepositoryInterface;
use InvalidArgumentException;

final class ManagePromotionalTemplatesUseCase
{
    public function __construct(
        private readonly PromotionalTemplateRepositoryInterface $templateRepository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllTemplates(): array
    {
        $templates = $this->templateRepository->findAll();
        return array_map(fn(PromotionalTemplate $t) => $t->toArray(), $templates);
    }

    public function getTemplateById(int $id): ?PromotionalTemplate
    {
        return $this->templateRepository->findById($id);
    }

    public function saveTemplate(
        int $adminId,
        string $name,
        string $subject,
        string $bodyContent,
        ?int $templateId = null
    ): PromotionalTemplate {
        $cleanName = trim($name);
        $cleanSubject = trim($subject);
        $cleanBody = trim($bodyContent);

        if ($cleanName === '') {
            throw new InvalidArgumentException('Template name is required.');
        }

        if ($cleanSubject === '') {
            throw new InvalidArgumentException('Template subject is required.');
        }

        if ($cleanBody === '') {
            throw new InvalidArgumentException('Template body content is required.');
        }

        if ($templateId !== null && $templateId > 0) {
            $existing = $this->templateRepository->findById($templateId);
            if ($existing !== null) {
                $updated = new PromotionalTemplate(
                    id: $templateId,
                    adminId: $adminId,
                    name: $cleanName,
                    subject: $cleanSubject,
                    bodyContent: $cleanBody,
                    createdAt: $existing->getCreatedAt()
                );
                $this->templateRepository->update($updated);
                return $this->templateRepository->findById($templateId) ?? $updated;
            }
        }

        // New template
        $template = new PromotionalTemplate(
            id: null,
            adminId: $adminId,
            name: $cleanName,
            subject: $cleanSubject,
            bodyContent: $cleanBody
        );

        $newId = $this->templateRepository->save($template);
        return $this->templateRepository->findById($newId)
            ?? throw new \RuntimeException('Failed to retrieve newly saved template.');
    }

    public function deleteTemplate(int $id): bool
    {
        return $this->templateRepository->delete($id);
    }
}
