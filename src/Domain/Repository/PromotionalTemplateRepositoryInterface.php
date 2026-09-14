<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Repository;

use FidelsPizza\Domain\Model\PromotionalTemplate;

interface PromotionalTemplateRepositoryInterface
{
    /**
     * Retrieve all saved promotional templates ordered by updated_at DESC.
     * @return PromotionalTemplate[]
     */
    public function findAll(): array;

    /**
     * Find a single template by ID.
     */
    public function findById(int $id): ?PromotionalTemplate;

    /**
     * Persist a new template and return its generated ID.
     */
    public function save(PromotionalTemplate $template): int;

    /**
     * Update an existing template.
     */
    public function update(PromotionalTemplate $template): void;

    /**
     * Delete a template by ID.
     */
    public function delete(int $id): bool;
}
