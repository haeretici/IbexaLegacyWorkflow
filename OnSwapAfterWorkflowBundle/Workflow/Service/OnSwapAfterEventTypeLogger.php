<?php

declare(strict_types=1);

namespace Haeretici\OnSwapAfterWorkflowBundle\Workflow\Service;

use Haeretici\OnSwapAfterWorkflowBundle\Workflow\EventType\OnSwapAfterEventType;

final class OnSwapAfterEventTypeLogger
{
    public const LOG_FILENAME = 'OnSwapAfterEventType.log';

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
                'event_type' => OnSwapAfterEventType::TYPE_STRING,
                'parameters' => $parameters,
            ],
            JSON_THROW_ON_ERROR
        );

        $logPath = rtrim($this->logsDir, '/') . '/' . self::LOG_FILENAME;
        file_put_contents($logPath, $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
