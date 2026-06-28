<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Controller\Admin;

use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Symfony\Component\HttpFoundation\Response;

final class ProcessController extends Controller
{
    public function __construct(
        private readonly WorkflowAdminService $adminService,
    ) {
    }

    public function listAction(): Response
    {
        $this->denyAccessUnlessGranted(new Attribute('workflow', 'read'));

        return $this->render('@ibexadesign/legacy_workflow/config/processes/list.html.twig', [
            'processes' => $this->adminService->listProcessesForAdmin(),
            'breadcrumbs' => [
                ['value' => 'Settings'],
                ['value' => 'Workflow processes'],
            ],
        ]);
    }
}