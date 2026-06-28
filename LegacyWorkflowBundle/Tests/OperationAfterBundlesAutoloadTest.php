<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\SupportedOperations;
use PHPUnit\Framework\TestCase;

class OperationAfterBundlesAutoloadTest extends TestCase
{
    /** @return array<string, array{0: class-string, 1: class-string, 2: string, 3: string}> */
    public static function bundleProvider(): array
    {
        $map = [
            'content_publish' => ['OnPublishAfter', 'publish'],
            'content_hide' => ['OnHideAfter', 'hide'],
            'content_show' => ['OnShowAfter', 'show'],
            'content_delete' => ['OnDeleteAfter', 'delete'],
            'content_move' => ['OnMoveAfter', 'move'],
            'content_addlocation' => ['OnAddLocationAfter', 'addlocation'],
            'content_removelocation' => ['OnRemoveLocationAfter', 'removelocation'],
            'content_swap' => ['OnSwapAfter', 'swap'],
            'content_updatepriority' => ['OnUpdatePriorityAfter', 'updatepriority'],
            'content_removetranslation' => ['OnRemoveTranslationAfter', 'removetranslation'],
            'content_updateobjectstate' => ['OnUpdateObjectStateAfter', 'updateobjectstate'],
            'content_updatesection' => ['OnUpdateSectionAfter', 'updatesection'],
        ];

        $cases = [];
        foreach (SupportedOperations::OPERATIONS as $operation) {
            [$prefix, $function] = $map[$operation];
            $bundleClass = "Haeretici\\{$prefix}WorkflowBundle\\{$prefix}WorkflowBundle";
            $eventClass = "Haeretici\\{$prefix}WorkflowBundle\\Workflow\\EventType\\{$prefix}EventType";
            $loggerClass = "Haeretici\\{$prefix}WorkflowBundle\\Workflow\\Service\\{$prefix}EventTypeLogger";
            $cases[$operation] = [$bundleClass, $eventClass, $loggerClass, $function];
        }

        return $cases;
    }

    /** @dataProvider bundleProvider */
    public function testBundleClassesAutoloadAndEventTypeMatchesOperation(
        string $bundleClass,
        string $eventClass,
        string $loggerClass,
        string $function,
    ): void {
        $this->assertTrue(class_exists($bundleClass));
        $this->assertTrue(class_exists($eventClass));
        $this->assertTrue(class_exists($loggerClass));

        $this->assertSame(
            'event_haeretici_on' . $function . 'after',
            $eventClass::TYPE_STRING
        );

        $eventType = new $eventClass(new $loggerClass(sys_get_temp_dir()));
        $this->assertTrue($eventType->isAllowed('content', $function, 'after'));
        $this->assertFalse($eventType->isAllowed('content', $function, 'before'));

        $otherFunction = $function === 'publish' ? 'hide' : 'publish';
        $this->assertFalse($eventType->isAllowed('content', $otherFunction, 'after'));
    }
}