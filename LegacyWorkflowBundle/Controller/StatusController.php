<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Controller;

use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StatusController
{
    public function __construct(
        private readonly WorkflowEventTypeRegistry $eventTypeRegistry,
        private readonly WorkflowAdminService $adminService,
        private readonly array $availableOperations,
        private readonly bool $enabled,
    ) {
    }

    public function status(): Response
    {
        $workflows = [];
        foreach ($this->adminService->listWorkflows() as $workflow) {
            $workflows[] = [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'is_enabled' => $workflow->isEnabled,
            ];
        }

        return new JsonResponse([
            'enabled' => $this->enabled,
            'operations' => $this->availableOperations,
            'event_types' => $this->eventTypeRegistry->getRegisteredTypeStrings(),
            'triggers' => $this->adminService->getTriggerMatrix(),
            'workflows' => $workflows,
        ]);
    }
}