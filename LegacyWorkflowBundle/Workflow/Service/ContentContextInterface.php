<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

interface ContentContextInterface
{
    public function getSectionId(int $contentId): int;

    public function getContentClassId(int $contentId): int;

    public function isUserContent(int $contentId): bool;

    public function finishUserRegistration(int $contentId): void;

    /** @param string[] $attributeIds */
    public function getEarliestDateAttributeTimestamp(int $contentId, int $version, array $attributeIds): ?int;

    public function updatePublishedDate(int $contentId, int $timestamp): void;
}