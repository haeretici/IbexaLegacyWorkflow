<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Controller\Admin;

use Haeretici\LegacyWorkflowBundle\Service\WorkflowAdminService;
use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Contracts\AdminUi\Notification\TranslatableNotificationHandlerInterface;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TriggerController extends Controller
{
    public function __construct(
        private readonly WorkflowAdminService $adminService,
        private readonly TranslatableNotificationHandlerInterface $notificationHandler,
    ) {
    }

    public function listAction(Request $request): Response
    {
        $this->denyAccessUnlessGranted(new Attribute('trigger', 'read'));

        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(new Attribute('trigger', 'edit'));

            $operation = (string) $request->request->get('operation', '');
            $connectType = (string) $request->request->get('connect_type', '');
            $workflowId = (int) $request->request->get('workflow_id', 0);

            if ($operation !== '' && in_array($connectType, ['before', 'after'], true)) {
                $this->adminService->assignTrigger($operation, $connectType, $workflowId);
                $this->notificationHandler->success('Trigger assignment saved', [], 'legacy_workflow');
            }

            return new RedirectResponse($this->generateUrl('ibexa_legacy_workflow.admin.triggers'));
        }

        return $this->render('@ibexadesign/legacy_workflow/config/triggers/list.html.twig', [
            'trigger_matrix' => $this->adminService->getTriggerMatrix(),
            'workflows' => $this->adminService->listWorkflows(),
            'breadcrumbs' => [
                ['value' => 'Settings'],
                ['value' => 'Triggers'],
            ],
        ]);
    }
}