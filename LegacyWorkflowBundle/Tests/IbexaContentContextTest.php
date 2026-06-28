<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\Service\IbexaContentContext;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\UserService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Contracts\Core\Repository\Values\User\User;
use Ibexa\Contracts\Core\Repository\Values\User\UserUpdateStruct;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class IbexaContentContextTest extends TestCase
{
    public function testIsUserContentUsesContentTypeIdentifier(): void
    {
        $contentType = $this->createMock(ContentType::class);
        $contentType->method('getIdentifier')->willReturn('user');
        $contentInfo = $this->createContentInfoWithTypeId(4);

        $contentService = $this->createMock(ContentService::class);
        $contentService->method('loadContentInfo')->willReturn($contentInfo);

        $contentTypeService = $this->createMock(ContentTypeService::class);
        $contentTypeService->method('loadContentType')->with(4)->willReturn($contentType);

        $context = new IbexaContentContext($contentService, $contentTypeService, $this->createMock(UserService::class));

        $this->assertTrue($context->isUserContent(42));
    }

    public function testFinishUserRegistrationEnablesUser(): void
    {
        $user = $this->createMock(User::class);
        $updateStruct = new UserUpdateStruct();

        $userService = $this->createMock(UserService::class);
        $userService->method('loadUser')->with(55)->willReturn($user);
        $userService->method('newUserUpdateStruct')->willReturn($updateStruct);
        $userService->expects($this->once())
            ->method('updateUser')
            ->with(
                $user,
                $this->callback(static function (UserUpdateStruct $struct): bool {
                    return $struct->enabled === true;
                })
            );

        $context = new IbexaContentContext(
            $this->createMock(ContentService::class),
            $this->createMock(ContentTypeService::class),
            $userService
        );

        $context->finishUserRegistration(55);
    }

    public function testGetEarliestDateAttributeTimestampMatchesFieldDefinitionId(): void
    {
        $fieldDefinition = $this->createMock(FieldDefinition::class);
        $fieldDefinition->method('getId')->willReturn(101);
        $fieldDefinition->method('getIdentifier')->willReturn('publish_date');
        $contentType = $this->createMock(ContentType::class);
        $contentType->method('getFieldDefinitions')->willReturn(new FieldDefinitionCollection([$fieldDefinition]));
        $contentInfo = $this->createContentInfoWithTypeId(8);
        $field = $this->createMock(Field::class);
        $field->method('getValue')->willReturn(new \DateTimeImmutable('2030-01-15'));
        $content = $this->createMock(Content::class);
        $content->method('getContentInfo')->willReturn($contentInfo);
        $content->method('getField')->with('publish_date')->willReturn($field);

        $contentService = $this->createMock(ContentService::class);
        $contentService->method('loadContent')->willReturn($content);

        $contentTypeService = $this->createMock(ContentTypeService::class);
        $contentTypeService->method('loadContentType')->with(8)->willReturn($contentType);

        $context = new IbexaContentContext($contentService, $contentTypeService, $this->createMock(UserService::class));

        $timestamp = $context->getEarliestDateAttributeTimestamp(1, 1, ['101']);

        $this->assertSame(strtotime('2030-01-15'), $timestamp);
    }

    private function createContentInfoWithTypeId(int $contentTypeId): ContentInfo
    {
        $contentInfo = (new ReflectionClass(ContentInfo::class))->newInstanceWithoutConstructor();
        $property = new ReflectionProperty(ContentInfo::class, 'contentTypeId');
        $property->setValue($contentInfo, $contentTypeId);

        return $contentInfo;
    }
}