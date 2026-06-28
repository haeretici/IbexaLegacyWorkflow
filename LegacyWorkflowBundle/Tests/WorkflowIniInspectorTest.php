<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Service\WorkflowIniInspector;
use PHPUnit\Framework\TestCase;

class WorkflowIniInspectorTest extends TestCase
{
    public function testReturnsConfiguredSupportedOperations(): void
    {
        $inspector = TestSupport::workflowIniInspector();

        $operations = $inspector->getAvailableOperations();

        $this->assertSame(TestSupport::SUPPORTED_OPERATIONS, $operations);
    }

    public function testReturnsConfiguredSupportedEventTypes(): void
    {
        $inspector = TestSupport::workflowIniInspector();

        $eventTypes = $inspector->getAvailableEventTypes();

        $this->assertSame(TestSupport::SUPPORTED_EVENT_TYPES, $eventTypes);
    }

    public function testLegacyIniStillReadableForDocumentation(): void
    {
        $inspector = TestSupport::workflowIniInspector();

        $this->assertContains('shop_checkout', $inspector->getLegacyIniOperations());
        $this->assertContains('event_ezsimpleshipping', $inspector->getLegacyIniEventTypes());
        $this->assertNotContains('shop_checkout', $inspector->getAvailableOperations());
        $this->assertNotContains('event_ezsimpleshipping', $inspector->getAvailableEventTypes());
    }
}