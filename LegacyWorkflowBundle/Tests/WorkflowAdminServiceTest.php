<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Haeretici\LegacyWorkflowBundle\Service\WorkflowIniInspector;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullCurrentUserResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\YamlWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use PHPUnit\Framework\TestCase;

class WorkflowAdminServiceTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/legacy-workflow-admin-' . uniqid('', true) . '.yaml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    public function testAdminDefinedWorkflowIsExecutedByTriggerRunner(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(new EzApproveEventType());
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Publish approval');
        $event = $adminService->addWorkflowEvent($workflow->id, EzApproveEventType::TYPE_STRING);
        $this->assertNotNull($event);
        $adminService->updateWorkflowEventData($workflow->id, $event->id, ['dataText3' => '999']);
        $adminService->assignTrigger('content_publish', 'before', $workflow->id);

        $processRunner = new WorkflowProcessRunner($storage, $registry);
        $triggerRunner = new TriggerRunner(
            $storage,
            $processRunner,
            new NullCurrentUserResolver(),
        );

        $result = $triggerRunner->runTrigger(
            'pre_publish',
            'content',
            'publish',
            ['object_id' => 10, 'version' => 1, 'user_id' => 14],
            ['object_id', 'version']
        );

        $this->assertSame(TriggerStatus::FETCH_TEMPLATE_REPEAT, $result['Status']);
    }

    public function testTriggerMatrixReflectsAssignedWorkflow(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Post publish');
        $adminService->assignTrigger('content_publish', 'after', $workflow->id);

        $matrix = $adminService->getTriggerMatrix();
        $afterRow = array_values(array_filter(
            $matrix,
            static fn (array $row): bool => $row['operation'] === 'content_publish' && $row['connect_type'] === 'after'
        ))[0];

        $this->assertSame($workflow->id, $afterRow['workflow_id']);
    }
}