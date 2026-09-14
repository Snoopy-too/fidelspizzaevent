<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Persistence;

use FidelsPizza\Domain\Model\PromotionalTemplate;
use FidelsPizza\Domain\Repository\PromotionalTemplateRepositoryInterface;
use PDO;

final class PdoPromotionalTemplateRepository implements PromotionalTemplateRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM `promotional_templates` 
            ORDER BY `updated_at` DESC, `id` DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $id): ?PromotionalTemplate
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `promotional_templates` WHERE `id` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function save(PromotionalTemplate $template): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO `promotional_templates` (`admin_id`, `name`, `subject`, `body_content`, `created_at`)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $template->getAdminId(),
            $template->getName(),
            $template->getSubject(),
            $template->getBodyContent(),
            $template->getCreatedAt() ?? date('Y-m-d H:i:s')
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(PromotionalTemplate $template): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `promotional_templates` 
            SET `name` = ?, `subject` = ?, `body_content` = ?, `updated_at` = ?
            WHERE `id` = ?
        ");

        $stmt->execute([
            $template->getName(),
            $template->getSubject(),
            $template->getBodyContent(),
            date('Y-m-d H:i:s'),
            $template->getId()
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `promotional_templates` WHERE `id` = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PromotionalTemplate
    {
        return new PromotionalTemplate(
            id: (int)$row['id'],
            adminId: (int)$row['admin_id'],
            name: (string)$row['name'],
            subject: (string)$row['subject'],
            bodyContent: (string)$row['body_content'],
            createdAt: (string)$row['created_at'],
            updatedAt: (string)$row['updated_at']
        );
    }
}
