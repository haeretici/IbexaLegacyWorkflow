<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\EventSubscriber;

use Haeretici\LegacyWorkflowBundle\Workflow\Exception\WorkflowHaltedException;
use Haeretici\LegacyWorkflowBundle\Workflow\OperationTriggerMapper;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TrashWorkflowOperationResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerResultEvaluator;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Ibexa\Contracts\Core\Repository\Events\Content\BeforeDeleteContentEvent;
use Ibexa\Contracts\Core\Repository\Events\Content\BeforeDeleteTranslationEvent;
use Ibexa\Contracts\Core\Repository\Events\Content\DeleteContentEvent;
use Ibexa\Contracts\Core\Repository\Events\Content\DeleteTranslationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\BeforeCreateLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\BeforeDeleteLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\BeforeHideLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\BeforeMoveSubtreeEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\BeforeSwapLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\BeforeUnhideLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\BeforeUpdateLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\CreateLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\DeleteLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\HideLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\MoveSubtreeEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\SwapLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\UnhideLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\UpdateLocationEvent;
use Ibexa\Contracts\Core\Repository\Events\ObjectState\BeforeSetContentStateEvent;
use Ibexa\Contracts\Core\Repository\Events\ObjectState\SetContentStateEvent;
use Ibexa\Contracts\Core\Repository\Events\Section\AssignSectionEvent;
use Ibexa\Contracts\Core\Repository\Events\Section\AssignSectionToSubtreeEvent;
use Ibexa\Contracts\Core\Repository\Events\Section\BeforeAssignSectionEvent;
use Ibexa\Contracts\Core\Repository\Events\Section\BeforeAssignSectionToSubtreeEvent;
use Ibexa\Contracts\Core\Repository\Events\Trash\BeforeDeleteTrashItemEvent;
use Ibexa\Contracts\Core\Repository\Events\Trash\BeforeTrashEvent;
use Ibexa\Contracts\Core\Repository\Events\Trash\DeleteTrashItemEvent;
use Ibexa\Contracts\Core\Repository\Events\Trash\TrashEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationUpdateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\Section;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Bridges Ibexa Repository content/location/section/object-state events to legacy workflow triggers.
 */
class ContentOperationsWorkflowSubscriber implements EventSubscriberInterface
{
    public const MODULE_CONTENT = 'content';

    public const OPERATION_HIDE = 'content_hide';
    public const OPERATION_SHOW = 'content_show';
    public const OPERATION_DELETE = 'content_delete';
    public const OPERATION_MOVE = 'content_move';
    public const OPERATION_ADD_LOCATION = 'content_addlocation';
    public const OPERATION_REMOVE_LOCATION = 'content_removelocation';
    public const OPERATION_SWAP = 'content_swap';
    public const OPERATION_UPDATE_PRIORITY = 'content_updatepriority';
    public const OPERATION_REMOVE_TRANSLATION = 'content_removetranslation';
    public const OPERATION_UPDATE_OBJECT_STATE = 'content_updateobjectstate';
    public const OPERATION_UPDATE_SECTION = 'content_updatesection';

    /** @var array{Status: int, Result: mixed, WorkflowProcess?: mixed}|null */
    private ?array $lastResult = null;

    public function __construct(
        private readonly TriggerRunner $triggerRunner,
        private readonly PermissionResolver $permissionResolver,
        private readonly TrashWorkflowOperationResolver $trashOperationResolver,
        private readonly bool $enabled = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeHideLocationEvent::class => 'onBeforeHideLocation',
            HideLocationEvent::class => 'onHideLocation',
            BeforeUnhideLocationEvent::class => 'onBeforeUnhideLocation',
            UnhideLocationEvent::class => 'onUnhideLocation',
            BeforeDeleteContentEvent::class => 'onBeforeDeleteContent',
            DeleteContentEvent::class => 'onDeleteContent',
            BeforeMoveSubtreeEvent::class => 'onBeforeMoveSubtree',
            MoveSubtreeEvent::class => 'onMoveSubtree',
            BeforeCreateLocationEvent::class => 'onBeforeCreateLocation',
            CreateLocationEvent::class => 'onCreateLocation',
            BeforeDeleteLocationEvent::class => 'onBeforeDeleteLocation',
            DeleteLocationEvent::class => 'onDeleteLocation',
            BeforeTrashEvent::class => 'onBeforeTrashLocation',
            TrashEvent::class => 'onTrashLocation',
            BeforeDeleteTrashItemEvent::class => 'onBeforeDeleteTrashItem',
            DeleteTrashItemEvent::class => 'onDeleteTrashItem',
            BeforeSwapLocationEvent::class => 'onBeforeSwapLocation',
            SwapLocationEvent::class => 'onSwapLocation',
            BeforeUpdateLocationEvent::class => 'onBeforeUpdateLocation',
            UpdateLocationEvent::class => 'onUpdateLocation',
            BeforeDeleteTranslationEvent::class => 'onBeforeDeleteTranslation',
            DeleteTranslationEvent::class => 'onDeleteTranslation',
            BeforeSetContentStateEvent::class => 'onBeforeSetContentState',
            SetContentStateEvent::class => 'onSetContentState',
            BeforeAssignSectionEvent::class => 'onBeforeAssignSection',
            AssignSectionEvent::class => 'onAssignSection',
            BeforeAssignSectionToSubtreeEvent::class => 'onBeforeAssignSectionToSubtree',
            AssignSectionToSubtreeEvent::class => 'onAssignSectionToSubtree',
        ];
    }

    public function onBeforeHideLocation(BeforeHideLocationEvent $event): void
    {
        $this->runBefore(
            'hide',
            self::OPERATION_HIDE,
            $this->parametersFromLocation($event->getLocation()),
            ['node_id']
        );
    }

    public function onHideLocation(HideLocationEvent $event): void
    {
        $this->runAfter(
            'hide',
            self::OPERATION_HIDE,
            $this->parametersFromLocation($event->getHiddenLocation()),
            ['node_id']
        );
    }

    public function onBeforeUnhideLocation(BeforeUnhideLocationEvent $event): void
    {
        $this->runBefore(
            'show',
            self::OPERATION_SHOW,
            $this->parametersFromLocation($event->getLocation()),
            ['node_id']
        );
    }

    public function onUnhideLocation(UnhideLocationEvent $event): void
    {
        $this->runAfter(
            'show',
            self::OPERATION_SHOW,
            $this->parametersFromLocation($event->getRevealedLocation()),
            ['node_id']
        );
    }

    public function onBeforeDeleteContent(BeforeDeleteContentEvent $event): void
    {
        $this->runDeleteBefore($this->parametersFromContentInfo($event->getContentInfo()), 0);
    }

    public function onDeleteContent(DeleteContentEvent $event): void
    {
        $this->runDeleteAfter($this->parametersFromContentInfo($event->getContentInfo()), 0);
    }

    public function onBeforeMoveSubtree(BeforeMoveSubtreeEvent $event): void
    {
        $this->runBefore(
            'move',
            self::OPERATION_MOVE,
            $this->parametersFromMove($event->getLocation(), $event->getNewParentLocation()),
            ['node_id']
        );
    }

    public function onMoveSubtree(MoveSubtreeEvent $event): void
    {
        $this->runAfter(
            'move',
            self::OPERATION_MOVE,
            $this->parametersFromMove($event->getLocation(), $event->getNewParentLocation()),
            ['node_id']
        );
    }

    public function onBeforeCreateLocation(BeforeCreateLocationEvent $event): void
    {
        $parameters = $this->parametersFromContentInfo($event->getContentInfo());
        $parameters['location_parent_node_id'] = (int) ($event->getLocationCreateStruct()->parentLocationId ?? 0);

        $this->runBefore('addlocation', self::OPERATION_ADD_LOCATION, $parameters, ['object_id']);
    }

    public function onCreateLocation(CreateLocationEvent $event): void
    {
        $this->runAfter(
            'addlocation',
            self::OPERATION_ADD_LOCATION,
            $this->parametersFromLocation($event->getLocation()),
            ['node_id']
        );
    }

    public function onBeforeDeleteLocation(BeforeDeleteLocationEvent $event): void
    {
        $this->runRemoveLocationBefore($event->getLocation());
    }

    public function onDeleteLocation(DeleteLocationEvent $event): void
    {
        $this->runRemoveLocationAfter($event->getLocation());
    }

    public function onBeforeTrashLocation(BeforeTrashEvent $event): void
    {
        $this->runTrashBefore($event->getLocation());
    }

    public function onTrashLocation(TrashEvent $event): void
    {
        $this->runTrashAfter($event->getLocation());
    }

    public function onBeforeDeleteTrashItem(BeforeDeleteTrashItemEvent $event): void
    {
        $this->runDeleteBefore($this->parametersFromLocation($event->getTrashItem()), 0);
    }

    public function onDeleteTrashItem(DeleteTrashItemEvent $event): void
    {
        $this->runDeleteAfter($this->parametersFromLocation($event->getTrashItem()), 0);
    }

    public function onBeforeSwapLocation(BeforeSwapLocationEvent $event): void
    {
        $this->runBefore(
            'swap',
            self::OPERATION_SWAP,
            $this->parametersFromSwap($event->getLocation1(), $event->getLocation2()),
            ['node_id_list']
        );
    }

    public function onSwapLocation(SwapLocationEvent $event): void
    {
        $this->runAfter(
            'swap',
            self::OPERATION_SWAP,
            $this->parametersFromSwap($event->getLocation1(), $event->getLocation2()),
            ['node_id_list']
        );
    }

    public function onBeforeUpdateLocation(BeforeUpdateLocationEvent $event): void
    {
        if (!$this->isPriorityUpdate($event->getLocationUpdateStruct())) {
            return;
        }

        $this->runBefore(
            'updatepriority',
            self::OPERATION_UPDATE_PRIORITY,
            $this->parametersFromLocation($event->getLocation()),
            ['node_id']
        );
    }

    public function onUpdateLocation(UpdateLocationEvent $event): void
    {
        if (!$this->isPriorityUpdate($event->getLocationUpdateStruct())) {
            return;
        }

        $this->runAfter(
            'updatepriority',
            self::OPERATION_UPDATE_PRIORITY,
            $this->parametersFromLocation($event->getLocation()),
            ['node_id']
        );
    }

    public function onBeforeDeleteTranslation(BeforeDeleteTranslationEvent $event): void
    {
        $this->runBefore(
            'removetranslation',
            self::OPERATION_REMOVE_TRANSLATION,
            $this->parametersFromTranslation($event->getContentInfo(), $event->getLanguageCode()),
            ['object_id']
        );
    }

    public function onDeleteTranslation(DeleteTranslationEvent $event): void
    {
        $this->runAfter(
            'removetranslation',
            self::OPERATION_REMOVE_TRANSLATION,
            $this->parametersFromTranslation($event->getContentInfo(), $event->getLanguageCode()),
            ['object_id']
        );
    }

    public function onBeforeSetContentState(BeforeSetContentStateEvent $event): void
    {
        $this->runBefore(
            'updateobjectstate',
            self::OPERATION_UPDATE_OBJECT_STATE,
            $this->parametersFromObjectState($event->getContentInfo(), $event->getObjectState()),
            ['object_id']
        );
    }

    public function onSetContentState(SetContentStateEvent $event): void
    {
        $this->runAfter(
            'updateobjectstate',
            self::OPERATION_UPDATE_OBJECT_STATE,
            $this->parametersFromObjectState($event->getContentInfo(), $event->getObjectState()),
            ['object_id']
        );
    }

    public function onBeforeAssignSection(BeforeAssignSectionEvent $event): void
    {
        $this->runBefore(
            'updatesection',
            self::OPERATION_UPDATE_SECTION,
            $this->parametersFromSectionAssignment($event->getContentInfo(), $event->getSection()),
            ['object_id', 'selected_section_id']
        );
    }

    public function onAssignSection(AssignSectionEvent $event): void
    {
        $this->runAfter(
            'updatesection',
            self::OPERATION_UPDATE_SECTION,
            $this->parametersFromSectionAssignment($event->getContentInfo(), $event->getSection()),
            ['object_id', 'selected_section_id']
        );
    }

    public function onBeforeAssignSectionToSubtree(BeforeAssignSectionToSubtreeEvent $event): void
    {
        $this->runBefore(
            'updatesection',
            self::OPERATION_UPDATE_SECTION,
            $this->parametersFromSubtreeSectionAssignment($event->getLocation(), $event->getSection()),
            ['node_id', 'selected_section_id']
        );
    }

    public function onAssignSectionToSubtree(AssignSectionToSubtreeEvent $event): void
    {
        $this->runAfter(
            'updatesection',
            self::OPERATION_UPDATE_SECTION,
            $this->parametersFromSubtreeSectionAssignment($event->getLocation(), $event->getSection()),
            ['node_id', 'selected_section_id']
        );
    }

    /** @return array{Status: int, Result: mixed, WorkflowProcess?: mixed}|null */
    public function getLastResult(): ?array
    {
        return $this->lastResult;
    }

    private function runTrashBefore(Location $location): void
    {
        if ($this->trashOperationResolver->resolveOperation($location) === TrashWorkflowOperationResolver::OPERATION_REMOVE_LOCATION) {
            $this->runRemoveLocationBefore($location);

            return;
        }

        $this->runDeleteBefore($this->parametersFromDeleteTrash($location), 1);
    }

    private function runTrashAfter(Location $location): void
    {
        if ($this->trashOperationResolver->resolveOperation($location) === TrashWorkflowOperationResolver::OPERATION_REMOVE_LOCATION) {
            $this->runRemoveLocationAfter($location);

            return;
        }

        $this->runDeleteAfter($this->parametersFromDeleteTrash($location), 1);
    }

    /** @param array<string, int|string|int[]> $parameters */
    private function runDeleteBefore(array $parameters, int $moveToTrash): void
    {
        $this->runBefore(
            'delete',
            self::OPERATION_DELETE,
            $this->withDeleteTrashFlags($parameters, $moveToTrash),
            ['object_id']
        );
    }

    /** @param array<string, int|string|int[]> $parameters */
    private function runDeleteAfter(array $parameters, int $moveToTrash): void
    {
        $this->runAfter(
            'delete',
            self::OPERATION_DELETE,
            $this->withDeleteTrashFlags($parameters, $moveToTrash),
            ['object_id']
        );
    }

    private function runRemoveLocationBefore(Location $location): void
    {
        $this->runBefore(
            'removelocation',
            self::OPERATION_REMOVE_LOCATION,
            $this->parametersFromLocation($location),
            ['node_id']
        );
    }

    private function runRemoveLocationAfter(Location $location): void
    {
        $this->runAfter(
            'removelocation',
            self::OPERATION_REMOVE_LOCATION,
            $this->parametersFromLocation($location),
            ['node_id']
        );
    }

    /** @param array<string, int|string|int[]> $parameters @return array<string, int|string|int[]> */
    private function withDeleteTrashFlags(array $parameters, int $moveToTrash): array
    {
        $parameters['move_to_trash'] = $moveToTrash;
        if (!isset($parameters['node_id_list']) && isset($parameters['node_id'])) {
            $parameters['node_id_list'] = [(int) $parameters['node_id']];
        }

        return $parameters;
    }

    /** @return array<string, int|string|int[]> */
    private function parametersFromDeleteTrash(Location $location): array
    {
        return $this->parametersFromLocation($location);
    }

    /** @param array<string, mixed> $parameters @param string[] $processKeys */
    private function runBefore(string $function, string $operation, array $parameters, array $processKeys): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->applyTriggerResult(
            $this->executeTrigger(
                OperationTriggerMapper::triggerName($function, 'before'),
                $function,
                $operation,
                $parameters,
                $processKeys
            ),
            haltOnBlocking: true,
            operationLabel: $function
        );
    }

    /** @param array<string, mixed> $parameters @param string[] $processKeys */
    private function runAfter(string $function, string $operation, array $parameters, array $processKeys): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->applyTriggerResult(
            $this->executeTrigger(
                OperationTriggerMapper::triggerName($function, 'after'),
                $function,
                $operation,
                $parameters,
                $processKeys
            ),
            haltOnBlocking: false,
            operationLabel: $function
        );
    }

    /** @param array<string, mixed> $parameters @param string[] $processKeys @return array{Status: int, Result: mixed, WorkflowProcess?: mixed} */
    private function executeTrigger(
        string $triggerName,
        string $function,
        string $operation,
        array $parameters,
        array $processKeys,
    ): array {
        $parameters['user_id'] = $this->permissionResolver->getCurrentUserReference()->getUserId();
        $parameters['operation'] = $operation;

        return $this->triggerRunner->runTrigger(
            $triggerName,
            self::MODULE_CONTENT,
            $function,
            $parameters,
            $processKeys
        );
    }

    /** @param array{Status: int, Result: mixed, WorkflowProcess?: mixed} $result */
    private function applyTriggerResult(array $result, bool $haltOnBlocking, string $operationLabel): void
    {
        $this->lastResult = $result;

        if (!$haltOnBlocking) {
            return;
        }

        if (TriggerResultEvaluator::shouldCancelOperation($result['Status'])) {
            throw new WorkflowHaltedException($result, sprintf('Workflow cancelled content %s operation', $operationLabel));
        }

        if (TriggerResultEvaluator::shouldHaltOperation($result['Status'])) {
            throw new WorkflowHaltedException($result, sprintf('Workflow halted content %s operation', $operationLabel));
        }
    }

    /** @return array<string, int|string|int[]> */
    private function parametersFromLocation(Location $location): array
    {
        return $this->parametersFromContentInfo($location->getContentInfo(), $location->getId());
    }

    /** @return array<string, int|string|int[]> */
    private function parametersFromContentInfo(ContentInfo $contentInfo, ?int $nodeId = null): array
    {
        return [
            'object_id' => $contentInfo->getId(),
            'node_id' => $nodeId ?? ($contentInfo->getMainLocationId() ?? 0),
            'version' => $this->versionFromContentInfo($contentInfo),
        ];
    }

    /** @return array<string, int|string|int[]> */
    private function parametersFromMove(Location $location, Location $newParentLocation): array
    {
        $parameters = $this->parametersFromLocation($location);
        $parameters['new_parent_node_id'] = $newParentLocation->getId();

        return $parameters;
    }

    /** @return array<string, int|string|int[]> */
    private function parametersFromSwap(Location $location1, Location $location2): array
    {
        return [
            'object_id' => $location1->getContentId(),
            'node_id' => $location1->getId(),
            'selected_node_id' => $location2->getId(),
            'node_id_list' => [$location1->getId(), $location2->getId()],
            'version' => $this->versionFromContentInfo($location1->getContentInfo()),
        ];
    }

    /** @return array<string, int|string> */
    private function parametersFromTranslation(ContentInfo $contentInfo, mixed $languageCode): array
    {
        $parameters = $this->parametersFromContentInfo($contentInfo);
        $parameters['language_code'] = (string) $languageCode;

        return $parameters;
    }

    /** @return array<string, int> */
    private function parametersFromObjectState(ContentInfo $contentInfo, object $objectState): array
    {
        $parameters = $this->parametersFromContentInfo($contentInfo);
        $parameters['object_state_id'] = (int) ($objectState->id ?? 0);

        return $parameters;
    }

    /** @return array<string, int> */
    private function parametersFromSectionAssignment(ContentInfo $contentInfo, Section $section): array
    {
        $parameters = $this->parametersFromContentInfo($contentInfo);
        $parameters['selected_section_id'] = (int) ($section->id ?? 0);

        return $parameters;
    }

    /** @return array<string, int> */
    private function parametersFromSubtreeSectionAssignment(Location $location, Section $section): array
    {
        $parameters = $this->parametersFromLocation($location);
        $parameters['selected_section_id'] = (int) ($section->id ?? 0);

        return $parameters;
    }

    private function versionFromContentInfo(ContentInfo $contentInfo): int
    {
        return (int) ($contentInfo->currentVersionNo ?? 0);
    }

    private function isPriorityUpdate(LocationUpdateStruct $locationUpdateStruct): bool
    {
        return isset($locationUpdateStruct->priority);
    }
}