<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\EventType\OnPublishAfterEventType;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\Service\OnPublishAfterEventTypeLogger;
use PHPUnit\Framework\TestCase;

class OnPublishAfterEventTypeLoggerTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        $this->logsDir = sys_get_temp_dir() . '/onpublishafter-log-' . uniqid('', true);
        mkdir($this->logsDir);
    }

    protected function tearDown(): void
    {
        $logFile = $this->logsDir . '/' . OnPublishAfterEventTypeLogger::LOG_FILENAME;
        if (is_file($logFile)) {
            unlink($logFile);
        }
        rmdir($this->logsDir);
    }

    public function testExecuteWritesJsonLineToDedicatedLogFile(): void
    {
        $eventType = TestSupport::onPublishAfterEventType($this->logsDir);
        $process = new WorkflowProcess();
        $process->parameters = [
            'object_id' => 99,
            'version' => 4,
            'user_id' => 7,
            'operation' => 'content_publish',
            'trigger_name' => 'post_publish',
            'module_name' => 'content',
            'module_function' => 'publish',
            'connect_type' => 'after',
        ];

        $eventType->execute($process, new WorkflowEvent(1, 1, OnPublishAfterEventType::TYPE_STRING, placement: 1));

        $logFile = $this->logsDir . '/' . OnPublishAfterEventTypeLogger::LOG_FILENAME;
        $this->assertFileExists($logFile);

        $line = trim((string) file_get_contents($logFile));
        $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(OnPublishAfterEventType::TYPE_STRING, $decoded['event_type']);
        $this->assertArrayHasKey('triggered_at', $decoded);
        $this->assertSame(99, $decoded['parameters']['object_id']);
        $this->assertSame(4, $decoded['parameters']['version']);
    }
}