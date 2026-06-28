<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\UserService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;

class IbexaContentContext implements ContentContextInterface
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly UserService $userService,
    ) {
    }

    public function getSectionId(int $contentId): int
    {
        try {
            return $this->contentService->loadContentInfo($contentId)->getSectionId();
        } catch (NotFoundException) {
            return 0;
        }
    }

    public function getContentClassId(int $contentId): int
    {
        try {
            return $this->contentService->loadContentInfo($contentId)->contentTypeId;
        } catch (NotFoundException) {
            return 0;
        }
    }

    public function isUserContent(int $contentId): bool
    {
        try {
            $contentTypeId = $this->contentService->loadContentInfo($contentId)->contentTypeId;

            return $this->contentTypeService->loadContentType($contentTypeId)->getIdentifier() === 'user';
        } catch (NotFoundException) {
            return false;
        }
    }

    public function finishUserRegistration(int $contentId): void
    {
        try {
            $user = $this->userService->loadUser($contentId);
            $update = $this->userService->newUserUpdateStruct();
            $update->enabled = true;
            $this->userService->updateUser($user, $update);
        } catch (NotFoundException) {
        }
    }

    public function getEarliestDateAttributeTimestamp(int $contentId, int $version, array $attributeIds): ?int
    {
        if ($attributeIds === []) {
            return null;
        }

        try {
            $content = $this->contentService->loadContent($contentId, null, $version);
        } catch (NotFoundException) {
            return null;
        }

        $earliest = null;
        foreach ($attributeIds as $attributeId) {
            $field = $this->findFieldByClassAttributeId($content, (int) $attributeId);
            if ($field === null) {
                continue;
            }

            $timestamp = $this->extractTimestamp($field->getValue());
            if ($timestamp !== null && ($earliest === null || $timestamp < $earliest)) {
                $earliest = $timestamp;
            }
        }

        return $earliest;
    }

    public function updatePublishedDate(int $contentId, int $timestamp): void
    {
        try {
            $info = $this->contentService->loadContentInfo($contentId);
            $metadataUpdate = $this->contentService->newContentMetadataUpdateStruct();
            $metadataUpdate->publishedDate = (new \DateTimeImmutable())->setTimestamp($timestamp);
            $this->contentService->updateContentMetadata($info, $metadataUpdate);
        } catch (NotFoundException) {
        }
    }

    private function findFieldByClassAttributeId(Content $content, int $classAttributeId)
    {
        try {
            $contentType = $this->contentTypeService->loadContentType($content->getContentInfo()->contentTypeId);
        } catch (NotFoundException) {
            return null;
        }

        foreach ($contentType->getFieldDefinitions() as $fieldDefinition) {
            if ($fieldDefinition->getId() !== $classAttributeId) {
                continue;
            }

            return $content->getField($fieldDefinition->getIdentifier());
        }

        return null;
    }

    private function extractTimestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_array($value) && isset($value['timestamp'])) {
            return (int) $value['timestamp'];
        }

        if (is_array($value) && isset($value['date'])) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value['date']);

            return $date !== false ? $date->getTimestamp() : null;
        }

        return null;
    }
}