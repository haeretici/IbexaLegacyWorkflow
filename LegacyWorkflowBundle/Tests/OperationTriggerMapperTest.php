<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\OperationTriggerMapper;
use PHPUnit\Framework\TestCase;

class OperationTriggerMapperTest extends TestCase
{
    public function testMapsPublishOperationToLegacyTriggerNames(): void
    {
        $this->assertSame(
            ['content', 'publish', 'pre_publish'],
            OperationTriggerMapper::map('content_publish', 'before')
        );
        $this->assertSame(
            ['content', 'publish', 'post_publish'],
            OperationTriggerMapper::map('content_publish', 'after')
        );
    }

    public function testMapsNonPublishOperationsToFunctionBasedTriggerNames(): void
    {
        $this->assertSame(
            ['content', 'hide', 'pre_hide'],
            OperationTriggerMapper::map('content_hide', 'before')
        );
        $this->assertSame(
            ['content', 'show', 'post_show'],
            OperationTriggerMapper::map('content_show', 'after')
        );
        $this->assertSame(
            ['content', 'updatesection', 'post_updatesection'],
            OperationTriggerMapper::map('content_updatesection', 'after')
        );
    }

    public function testMapWithConnectLetterIncludesLegacyConnectType(): void
    {
        $this->assertSame(
            ['content', 'delete', 'pre_delete', 'b'],
            OperationTriggerMapper::mapWithConnectLetter('content_delete', 'before')
        );
        $this->assertSame(
            ['content', 'move', 'post_move', 'a'],
            OperationTriggerMapper::mapWithConnectLetter('content_move', 'after')
        );
    }
}