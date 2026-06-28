<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\EventListener;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\AdminUi\Menu\MainMenuBuilder;
use Ibexa\Contracts\AdminUi\Menu\MenuItemFactoryInterface;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MenuListener implements EventSubscriberInterface
{
    public const ITEM_LEGACY_WORKFLOW_TRIGGERS = 'main__content__legacy_workflow_triggers';
    public const ITEM_LEGACY_WORKFLOW_WORKFLOWS = 'main__content__legacy_workflow_workflows';
    public const ITEM_LEGACY_WORKFLOW_PROCESSES = 'main__content__legacy_workflow_processes';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly MenuItemFactoryInterface $menuItemFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ConfigureMenuEvent::MAIN_MENU => 'onMainMenuBuild'];
    }

    public function onMainMenuBuild(ConfigureMenuEvent $event): void
    {
        $canReadWorkflow = $this->authorizationChecker->isGranted(new Attribute('workflow', 'read'));
        $canReadTrigger = $this->authorizationChecker->isGranted(new Attribute('trigger', 'read'));

        if (!$canReadWorkflow && !$canReadTrigger) {
            return;
        }

        $menu = $event->getMenu();
        $contentMenu = $menu->getChild(MainMenuBuilder::ITEM_CONTENT);
        if ($contentMenu === null) {
            return;
        }

        $settingsGroup = $contentMenu->getChild(MainMenuBuilder::ITEM_CONTENT_GROUP_SETTINGS);
        if ($settingsGroup === null) {
            return;
        }

        if ($canReadTrigger) {
            $settingsGroup->addChild(
                $this->menuItemFactory->createItem(self::ITEM_LEGACY_WORKFLOW_TRIGGERS, [
                    'route' => 'ibexa_legacy_workflow.admin.triggers',
                    'extras' => [
                        'orderNumber' => 80,
                    ],
                ])
            );
        }

        if ($canReadWorkflow) {
            $settingsGroup->addChild(
                $this->menuItemFactory->createItem(self::ITEM_LEGACY_WORKFLOW_WORKFLOWS, [
                    'route' => 'ibexa_legacy_workflow.admin.workflows',
                    'extras' => [
                        'orderNumber' => 81,
                        'routes' => [
                            'edit' => 'ibexa_legacy_workflow.admin.workflow_edit',
                            'create' => 'ibexa_legacy_workflow.admin.workflow_create',
                        ],
                    ],
                ])
            );

            $settingsGroup->addChild(
                $this->menuItemFactory->createItem(self::ITEM_LEGACY_WORKFLOW_PROCESSES, [
                    'route' => 'ibexa_legacy_workflow.admin.processes',
                    'extras' => [
                        'orderNumber' => 82,
                    ],
                ])
            );
        }
    }
}