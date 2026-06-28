<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\EventListener\MenuListener;
use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use PHPUnit\Framework\TestCase;

class MenuListenerTest extends TestCase
{
    public function testSubscribesToMainMenuBuild(): void
    {
        $this->assertSame(
            [ConfigureMenuEvent::MAIN_MENU => 'onMainMenuBuild'],
            MenuListener::getSubscribedEvents()
        );
    }

    public function testDeclaresSettingsMenuItemConstants(): void
    {
        $this->assertSame('main__content__legacy_workflow_triggers', MenuListener::ITEM_LEGACY_WORKFLOW_TRIGGERS);
        $this->assertSame('main__content__legacy_workflow_workflows', MenuListener::ITEM_LEGACY_WORKFLOW_WORKFLOWS);
        $this->assertSame('main__content__legacy_workflow_processes', MenuListener::ITEM_LEGACY_WORKFLOW_PROCESSES);
    }
}