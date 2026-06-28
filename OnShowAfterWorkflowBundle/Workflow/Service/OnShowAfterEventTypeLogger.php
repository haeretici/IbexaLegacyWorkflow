<?php

declare(strict_types=1);

namespace Haeretici\OnShowAfterWorkflowBundle\Workflow\Service;

use Haeretici\OnShowAfterWorkflowBundle\Workflow\EventType\OnShowAfterEventType;

final class OnShowAfterEventTypeLogger
{
    public const LOG_FILENAME = 'OnShowAfterEventType.log';

    public function __construct(
        private readonly string $logsDir,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function logTriggered(array $parameters): void
    {
        $payload = json_encode(
            [
                'triggered_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'event_type' => OnShowAfterEventType::TYPE_STRING,
                'parameters' => $parameters,
            ],
            JSON_THROW_ON_ERROR
        );

        $logPath = rtrim($this->logsDir, '/') . '/' . self::LOG_FILENAME;
        file_put_contents($logPath, $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}