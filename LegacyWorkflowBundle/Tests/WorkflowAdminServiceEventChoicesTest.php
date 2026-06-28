<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Haeretici\LegacyWorkflowBundle\Service\WorkflowIniInspector;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\InMemoryWorkflowStorage;
use Haeretici\OnHideAfterWorkflowBundle\Workflow\EventType\OnHideAfterEventType;
use Haeretici\OnShowAfterWorkflowBundle\Workflow\EventType\OnShowAfterEventType;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\EventType\OnPublishAfterEventType;
use PHPUnit\Framework\TestCase;

class WorkflowAdminServiceEventChoicesTest extends TestCase
{
    public function testEventChoicesFilteredByWorkflowIniAndIsAllowed(): void
    {
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(new EzApproveEventType());
        $registry->register(TestSupport::onPublishAfterEventType());
        $registry->register(TestSupport::onHideAfterEventType());
        $registry->register(TestSupport::onShowAfterEventType());

        $adminService = new WorkflowAdminService(
            new InMemoryWorkflowStorage(),
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $beforeChoices = $adminService->getEventTypeChoices('before');
        $afterChoices = $adminService->getEventTypeChoices('after');

        $this->assertArrayHasKey(EzApproveEventType::TYPE_STRING, $beforeChoices);
        $this->assertArrayNotHasKey(OnPublishAfterEventType::TYPE_STRING, $beforeChoices);
        $this->assertArrayHasKey(OnPublishAfterEventType::TYPE_STRING, $afterChoices);
        $this->assertArrayHasKey(OnHideAfterEventType::TYPE_STRING, $afterChoices);
        $this->assertArrayHasKey(OnShowAfterEventType::TYPE_STRING, $afterChoices);
        $this->assertArrayNotHasKey(EzApproveEventType::TYPE_STRING, $afterChoices);
        $this->assertArrayNotHasKey(OnHideAfterEventType::TYPE_STRING, $beforeChoices);
        $this->assertArrayNotHasKey(OnShowAfterEventType::TYPE_STRING, $beforeChoices);
    }
}