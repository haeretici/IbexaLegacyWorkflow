<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

class NullContentContext implements ContentContextInterface
{
    public function getSectionId(int $contentId): int { return 0; }
    public function getContentClassId(int $contentId): int { return 0; }
    public function isUserContent(int $contentId): bool { return false; }
    public function finishUserRegistration(int $contentId): void {}
    public function getEarliestDateAttributeTimestamp(int $contentId, int $version, array $attributeIds): ?int { return null; }
    public function updatePublishedDate(int $contentId, int $timestamp): void {}
}