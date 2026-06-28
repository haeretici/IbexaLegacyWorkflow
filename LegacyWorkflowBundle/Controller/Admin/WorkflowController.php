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

final class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowAdminService $adminService,
        private readonly TranslatableNotificationHandlerInterface $notificationHandler,
    ) {
    }

    public function listAction(): Response
    {
        $this->denyAccessUnlessGranted(new Attribute('workflow', 'read'));

        return $this->render('@ibexadesign/legacy_workflow/config/workflows/list.html.twig', [
            'workflows' => $this->adminService->listWorkflows(),
            'breadcrumbs' => [
                ['value' => 'Settings'],
                ['value' => 'Workflows'],
            ],
        ]);
    }

    public function createAction(Request $request): Response
    {
        $this->denyAccessUnlessGranted(new Attribute('workflow', 'edit'));

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            if ($name !== '') {
                $workflow = $this->adminService->createWorkflow($name);
                $this->notificationHandler->success('Workflow created', [], 'legacy_workflow');

                return new RedirectResponse($this->generateUrl('ibexa_legacy_workflow.admin.workflow_edit', [
                    'workflowId' => $workflow->id,
                ]));
            }
        }

        return $this->render('@ibexadesign/legacy_workflow/config/workflows/create.html.twig', [
            'breadcrumbs' => [
                ['value' => 'Settings'],
                ['value' => 'Workflows'],
                ['value' => 'Create'],
            ],
        ]);
    }

    public function editAction(Request $request, int $workflowId): Response
    {
        $this->denyAccessUnlessGranted(new Attribute('workflow', 'read'));

        $workflow = $this->adminService->getWorkflow($workflowId);
        if ($workflow === null) {
            throw $this->createNotFoundException(sprintf('Workflow %d not found.', $workflowId));
        }

        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(new Attribute('workflow', 'edit'));

            if ($request->request->has('delete_workflow')) {
                $this->adminService->deleteWorkflow($workflowId);
                $this->notificationHandler->success('Workflow deleted', [], 'legacy_workflow');

                return new RedirectResponse($this->generateUrl('ibexa_legacy_workflow.admin.workflows'));
            }

            if ($request->request->has('save_workflow')) {
                $this->adminService->updateWorkflow(
                    $workflowId,
                    trim((string) $request->request->get('name', $workflow->name)),
                    $request->request->has('is_enabled') ? (bool) $request->request->get('is_enabled') : $workflow->isEnabled
                );
                $this->notificationHandler->success('Workflow saved', [], 'legacy_workflow');
            }

            if ($request->request->has('add_event')) {
                $eventType = (string) $request->request->get('event_type', '');
                if ($this->adminService->addWorkflowEvent($workflowId, $eventType) !== null) {
                    $this->notificationHandler->success('Event added', [], 'legacy_workflow');
                }
            }

            $removeEventId = (int) $request->request->get('remove_event_id', 0);
            if ($removeEventId > 0) {
                $this->adminService->removeWorkflowEvent($workflowId, $removeEventId);
                $this->notificationHandler->success('Event removed', [], 'legacy_workflow');
            }

            if ($request->request->has('save_events')) {
                /** @var array<string, array<string, mixed>> $eventsData */
                $eventsData = $request->request->all('events');
                $this->adminService->updateWorkflowEventsData($workflowId, $eventsData);
                $this->notificationHandler->success('Event configuration saved', [], 'legacy_workflow');
            }

            return new RedirectResponse($this->generateUrl('ibexa_legacy_workflow.admin.workflow_edit', [
                'workflowId' => $workflowId,
            ]));
        }

        return $this->render('@ibexadesign/legacy_workflow/config/workflows/edit.html.twig', [
            'workflow' => $this->adminService->getWorkflow($workflowId),
            'events' => $this->adminService->getWorkflowEvents($workflowId),
            'event_type_choices' => $this->adminService->getEventTypeChoices(),
            'breadcrumbs' => [
                ['value' => 'Settings'],
                ['value' => 'Workflows'],
                ['value' => $workflow->name],
            ],
        ]);
    }
}