<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Haeretici\LegacyWorkflowBundle\Service\WorkflowIniInspector;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\YamlWorkflowStorage;
use PHPUnit\Framework\TestCase;

class WorkflowAdminServiceEventDataTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/legacy-event-data-' . uniqid('', true) . '.yaml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    public function testUpdateWorkflowEventDataPersistsLegacyDataFields(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(new EzApproveEventType());
        $adminService = new WorkflowAdminService(
            $storage,
            $registry,
            TestSupport::workflowIniInspector(),
        );

        $workflow = $adminService->createWorkflow('Approval');
        $event = $adminService->addWorkflowEvent($workflow->id, EzApproveEventType::TYPE_STRING);
        $this->assertNotNull($event);

        $adminService->updateWorkflowEventsData($workflow->id, [
            (string) $event->id => [
                'dataText3' => '42,99',
                'dataInt1' => 7,
                'description' => 'Approvers',
            ],
        ]);

        $reloaded = new YamlWorkflowStorage($this->storagePath);
        $storedEvent = $reloaded->findWorkflowEvent($event->id);

        $this->assertSame('42,99', $storedEvent?->dataText3);
        $this->assertSame(7, $storedEvent?->dataInt1);
        $this->assertSame('Approvers', $storedEvent?->description);
    }
}