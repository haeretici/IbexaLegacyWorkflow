#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Audits Ibexa Repository workflow hooks against LegacyWorkflowBundle wiring
 * and extension bundle coverage (OneForAllBeforeWorkflowBundle, On*AfterWorkflowBundle).
 *
 * Scans:
 *   - vendor/ibexa/core (contracts + event dispatchers)
 *   - LegacyWorkflowBundle/EventSubscriber/
 *   - OneForAllBeforeWorkflowBundle/Workflow/EventType/
 *   - On*AfterWorkflowBundle/Workflow/EventType/
 */

$projectRoot = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
$jsonOutput = in_array('--json', $argv, true);
$verbose = in_array('--verbose', $argv, true) || $jsonOutput;

$ibexaContractsDir = $projectRoot . '/vendor/ibexa/core/src/contracts/Repository/Events';
$ibexaDispatchDir = $projectRoot . '/vendor/ibexa/core/src/lib/Event';
$legacyBundleDir = $projectRoot . '/LegacyWorkflowBundle';
$oneForAllBeforeDir = $projectRoot . '/OneForAllBeforeWorkflowBundle';

/** Domains treated as content-lifecycle workflow candidates (not admin/settings). */
$workflowDomains = ['Content', 'Location', 'Trash', 'ObjectState', 'Section'];

/** Ibexa services whose before/after events are workflow-relevant. */
$workflowDispatchServices = [
    'ContentService.php',
    'LocationService.php',
    'TrashService.php',
    'ObjectStateService.php',
    'SectionService.php',
];

/**
 * Parallel Ibexa APIs where the bundle intentionally uses one variant (see SUPPORTED.md).
 *
 * @var array<string, array{preferred: string, note: string}>
 */
$parallelApiNotes = [
    'BeforeHideContentEvent' => [
        'preferred' => 'BeforeHideLocationEvent',
        'note' => 'ContentService::hideContent exists; LegacyWorkflowBundle uses LocationService hide for admin tree parity.',
    ],
    'HideContentEvent' => [
        'preferred' => 'HideLocationEvent',
        'note' => 'Content-level hide event; bundle maps content_hide to location hide.',
    ],
    'BeforeRevealContentEvent' => [
        'preferred' => 'BeforeUnhideLocationEvent',
        'note' => 'ContentService::revealContent exists; bundle uses location unhide for content_show.',
    ],
    'RevealContentEvent' => [
        'preferred' => 'UnhideLocationEvent',
        'note' => 'Content-level reveal event; bundle maps content_show to location unhide.',
    ],
];

$report = [
    'project_root' => $projectRoot,
    'ibexa_contracts_found' => is_dir($ibexaContractsDir),
    'ibexa_dispatch_found' => is_dir($ibexaDispatchDir),
    'supported_operations' => extractSupportedOperations($legacyBundleDir),
    'ibexa_dispatched_events' => [],
    'ibexa_contract_events' => [],
    'legacy_subscriber_wiring' => [],
    'one_for_all_before' => [],
    'on_after_bundles' => [],
    'coverage' => [],
    'gaps' => [],
    'deprecated' => [],
    'parallel_api' => [],
    'summary' => [],
];

if (!$report['ibexa_contracts_found']) {
    fwrite(STDERR, "ERROR: Ibexa contracts not found at {$ibexaContractsDir}. Run composer install in the project root.\n");
    exit(2);
}

$report['ibexa_dispatched_events'] = scanDispatchedEvents($ibexaDispatchDir, $workflowDispatchServices);
$report['ibexa_contract_events'] = scanContractEvents($ibexaContractsDir, $workflowDomains);
$report['legacy_subscriber_wiring'] = scanLegacySubscribers($legacyBundleDir . '/EventSubscriber');
$report['one_for_all_before'] = scanOneForAllBefore($oneForAllBeforeDir);
$report['on_after_bundles'] = scanOnAfterBundles($projectRoot);

$report['deprecated'] = collectDeprecationSignals($report['ibexa_contract_events'], $ibexaContractsDir, $workflowDomains);
$report['parallel_api'] = collectParallelApiNotes(
    $report['ibexa_dispatched_events'],
    $report['legacy_subscriber_wiring'],
    $parallelApiNotes
);

$report['coverage'] = buildCoverageMatrix(
    $report['supported_operations'],
    $report['legacy_subscriber_wiring'],
    $report['one_for_all_before'],
    $report['on_after_bundles']
);

$report['gaps'] = findGaps(
    $report['ibexa_dispatched_events'],
    $report['legacy_subscriber_wiring'],
    $report['supported_operations'],
    $parallelApiNotes
);

$report['summary'] = buildSummary($report);

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($report['summary']['exit_code']);
}

renderTextReport($report, $verbose);
exit($report['summary']['exit_code']);

// ---------------------------------------------------------------------------
// Scanners
// ---------------------------------------------------------------------------

/** @return string[] */
function extractSupportedOperations(string $legacyBundleDir): array
{
    $file = $legacyBundleDir . '/Workflow/SupportedOperations.php';
    if (!is_readable($file)) {
        return [];
    }

    $contents = file_get_contents($file);
    if (!preg_match_all("/'((?:content_)[a-z]+)'/", $contents, $matches)) {
        return [];
    }

    return $matches[1];
}

/**
 * @param string[] $serviceFiles
 * @return array<string, array{service: string, phase: string, contract_path: string}>
 */
function scanDispatchedEvents(string $dispatchDir, array $serviceFiles): array
{
    $events = [];

    foreach ($serviceFiles as $serviceFile) {
        $path = $dispatchDir . '/' . $serviceFile;
        if (!is_readable($path)) {
            continue;
        }

        $contents = file_get_contents($path);
        if (!preg_match_all('/new\s+(\w+Event)\s*\(/', $contents, $matches)) {
            continue;
        }

        foreach (array_unique($matches[1]) as $shortName) {
            $phase = str_starts_with($shortName, 'Before') ? 'before' : 'after';
            $events[$shortName] = [
                'service' => basename($serviceFile, '.php'),
                'phase' => $phase,
                'contract_path' => "vendor/ibexa/core/src/contracts/Repository/Events/**/{$shortName}.php",
            ];
        }
    }

    ksort($events);

    return $events;
}

/**
 * @param string[] $domains
 * @return array<string, array{domain: string, phase: string, has_legacy_alias: bool, deprecated: bool, deprecation_note: string|null, file: string}>
 */
function scanContractEvents(string $contractsDir, array $domains): array
{
    $events = [];

    foreach ($domains as $domain) {
        $domainDir = $contractsDir . '/' . $domain;
        if (!is_dir($domainDir)) {
            continue;
        }

        foreach (glob($domainDir . '/*Event.php') ?: [] as $file) {
            $shortName = basename($file, '.php');
            $contents = file_get_contents($file);
            $phase = str_starts_with($shortName, 'Before') ? 'before' : 'after';
            $hasLegacyAlias = (bool) preg_match("/class_alias\\([^,]+,\\s*'eZ\\\\Publish\\\\API\\\\Repository\\\\Events\\\\/", $contents);
            $deprecation = extractDeprecationFromDocblock($contents);

            $events[$shortName] = [
                'domain' => $domain,
                'phase' => $phase,
                'has_legacy_alias' => $hasLegacyAlias,
                'deprecated' => $deprecation !== null,
                'deprecation_note' => $deprecation,
                'file' => str_replace(realpath(dirname($contractsDir, 4)) . '/', '', realpath($file) ?: $file),
            ];
        }
    }

    ksort($events);

    return $events;
}

/**
 * @return array<string, array{subscriber: string, handler: string, operation: string|null, phase: string}>
 */
function scanLegacySubscribers(string $subscriberDir): array
{
    $wiring = [];

    foreach (glob($subscriberDir . '/*WorkflowSubscriber.php') ?: [] as $file) {
        $subscriber = basename($file, '.php');
        $contents = file_get_contents($file);

        if (!preg_match('/public\s+static\s+function\s+getSubscribedEvents\(\)\s*:\s*array\s*\{(.+?)\n\s*\}/s', $contents, $block)) {
            continue;
        }

        if (!preg_match_all('/(\w+Event)::class\s*=>\s*\'(\w+)\'/', $block[1], $pairs, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($pairs as [, $eventClass, $handler]) {
            $phase = str_starts_with($handler, 'onBefore') ? 'before' : 'after';
            $operation = resolveLegacyEventOperation($eventClass);

            $wiring[$eventClass] = [
                'subscriber' => $subscriber,
                'handler' => $handler,
                'operation' => $operation,
                'phase' => $phase,
            ];
        }
    }

    ksort($wiring);

    return $wiring;
}

/**
 * Canonical map from Ibexa event short name → legacy operation.
 * Keep aligned with SUPPORTED.md and EventSubscriber handlers.
 *
 * @return array<string, string>
 */
function legacyEventOperationMap(): array
{
    return [
        'BeforePublishVersionEvent' => 'content_publish',
        'PublishVersionEvent' => 'content_publish',
        'BeforeHideLocationEvent' => 'content_hide',
        'HideLocationEvent' => 'content_hide',
        'BeforeUnhideLocationEvent' => 'content_show',
        'UnhideLocationEvent' => 'content_show',
        'BeforeDeleteContentEvent' => 'content_delete',
        'DeleteContentEvent' => 'content_delete',
        'BeforeDeleteTrashItemEvent' => 'content_delete',
        'DeleteTrashItemEvent' => 'content_delete',
        'BeforeTrashEvent' => 'content_delete|content_removelocation',
        'TrashEvent' => 'content_delete|content_removelocation',
        'BeforeMoveSubtreeEvent' => 'content_move',
        'MoveSubtreeEvent' => 'content_move',
        'BeforeCreateLocationEvent' => 'content_addlocation',
        'CreateLocationEvent' => 'content_addlocation',
        'BeforeDeleteLocationEvent' => 'content_removelocation',
        'DeleteLocationEvent' => 'content_removelocation',
        'BeforeSwapLocationEvent' => 'content_swap',
        'SwapLocationEvent' => 'content_swap',
        'BeforeUpdateLocationEvent' => 'content_updatepriority',
        'UpdateLocationEvent' => 'content_updatepriority',
        'BeforeDeleteTranslationEvent' => 'content_removetranslation',
        'DeleteTranslationEvent' => 'content_removetranslation',
        'BeforeSetContentStateEvent' => 'content_updateobjectstate',
        'SetContentStateEvent' => 'content_updateobjectstate',
        'BeforeAssignSectionEvent' => 'content_updatesection',
        'AssignSectionEvent' => 'content_updatesection',
        'BeforeAssignSectionToSubtreeEvent' => 'content_updatesection',
        'AssignSectionToSubtreeEvent' => 'content_updatesection',
    ];
}

function resolveLegacyEventOperation(string $eventClass): ?string
{
    return legacyEventOperationMap()[$eventClass] ?? null;
}

/**
 * @return string[]
 */
function operationsForWiringRow(array $row): array
{
    if ($row['operation'] === null) {
        return [];
    }

    return explode('|', $row['operation']);
}

/**
 * @return array<string, array{class: string, type_string: string, function: string, file: string}>
 */
function scanOneForAllBefore(string $bundleDir): array
{
    $types = [];
    $eventDir = $bundleDir . '/Workflow/EventType';

    if (!is_dir($eventDir)) {
        return $types;
    }

    foreach (glob($eventDir . '/On*BeforeEventType.php') ?: [] as $file) {
        $contents = file_get_contents($file);
        $class = basename($file, '.php');

        if (!preg_match("/TYPE_STRING = '([^']+)'/", $contents, $typeMatch)) {
            continue;
        }

        if (!preg_match("/\['content' => \['([a-z]+)' => \['before'\]\]\]/", $contents, $funcMatch)) {
            continue;
        }

        $types[$funcMatch[1]] = [
            'class' => $class,
            'type_string' => $typeMatch[1],
            'function' => $funcMatch[1],
            'file' => str_replace(realpath(dirname($bundleDir)) . '/', '', realpath($file) ?: $file),
        ];
    }

    ksort($types);

    return $types;
}

/**
 * @return array<string, array{bundle: string, class: string, type_string: string, function: string}>
 */
function scanOnAfterBundles(string $projectRoot): array
{
    $types = [];

    foreach (glob($projectRoot . '/On*AfterWorkflowBundle/Workflow/EventType/On*AfterEventType.php') ?: [] as $file) {
        $contents = file_get_contents($file);
        $class = basename($file, '.php');
        $bundle = basename(dirname($file, 3));

        if (!preg_match("/TYPE_STRING = '([^']+)'/", $contents, $typeMatch)) {
            continue;
        }

        if (!preg_match("/\['content' => \['([a-z]+)' => \['after'\]\]\]/", $contents, $funcMatch)) {
            continue;
        }

        $types[$funcMatch[1]] = [
            'bundle' => $bundle,
            'class' => $class,
            'type_string' => $typeMatch[1],
            'function' => $funcMatch[1],
        ];
    }

    ksort($types);

    return $types;
}

/**
 * @param array<string, array<string, mixed>> $contractEvents
 * @param string[] $domains
 * @return array<int, array{event: string, kind: string, detail: string}>
 */
function collectDeprecationSignals(array $contractEvents, string $contractsDir, array $domains): array
{
    $signals = [];

    foreach ($contractEvents as $eventName => $meta) {
        if ($meta['deprecated']) {
            $signals[] = [
                'event' => $eventName,
                'kind' => 'docblock_deprecated',
                'detail' => $meta['deprecation_note'] ?? 'Marked @deprecated in contract',
            ];
        }

        if ($meta['has_legacy_alias']) {
            $signals[] = [
                'event' => $eventName,
                'kind' => 'legacy_ez_publish_alias',
                'detail' => 'class_alias to eZ\\Publish\\API\\Repository\\Events\\* (BC alias, not removed)',
            ];
        }
    }

    return $signals;
}

/**
 * @param array<string, array<string, mixed>> $dispatched
 * @param array<string, array<string, mixed>> $wiring
 * @param array<string, array{preferred: string, note: string}> $notes
 * @return array<int, array{event: string, preferred: string, wired: bool, note: string}>
 */
function collectParallelApiNotes(array $dispatched, array $wiring, array $notes): array
{
    $rows = [];

    foreach ($notes as $event => $info) {
        if (!isset($dispatched[$event])) {
            continue;
        }

        $rows[] = [
            'event' => $event,
            'preferred' => $info['preferred'],
            'wired' => isset($wiring[$event]),
            'preferred_wired' => isset($wiring[$info['preferred']]),
            'note' => $info['note'],
        ];
    }

    return $rows;
}

/**
 * @param string[] $operations
 * @param array<string, array<string, mixed>> $wiring
 * @param array<string, array<string, mixed>> $beforeTypes
 * @param array<string, array<string, mixed>> $afterTypes
 * @return array<int, array<string, mixed>>
 */
function buildCoverageMatrix(array $operations, array $wiring, array $beforeTypes, array $afterTypes): array
{
    $rows = [];

    foreach ($operations as $operation) {
        $function = preg_replace('/^content_/', '', $operation) ?: $operation;

        $beforeEvents = array_keys(array_filter(
            $wiring,
            static fn (array $row): bool => $row['phase'] === 'before'
                && in_array($operation, operationsForWiringRow($row), true)
        ));
        $afterEvents = array_keys(array_filter(
            $wiring,
            static fn (array $row): bool => $row['phase'] === 'after'
                && in_array($operation, operationsForWiringRow($row), true)
        ));

        $rows[] = [
            'operation' => $operation,
            'function' => $function,
            'legacy_before_events' => $beforeEvents,
            'legacy_after_events' => $afterEvents,
            'one_for_all_before' => $beforeTypes[$function] ?? null,
            'on_after_bundle' => $afterTypes[$function] ?? null,
            'before_extension_ok' => isset($beforeTypes[$function]),
            'after_extension_ok' => isset($afterTypes[$function]),
            'legacy_before_ok' => $beforeEvents !== [],
            'legacy_after_ok' => $afterEvents !== [],
        ];
    }

    return $rows;
}

/**
 * @param array<string, array<string, mixed>> $dispatched
 * @param array<string, array<string, mixed>> $wiring
 * @param string[] $supportedOperations
 * @param array<string, array{preferred: string, note: string}> $parallelApiNotes
 * @return array<int, array<string, string>>
 */
function findGaps(array $dispatched, array $wiring, array $supportedOperations, array $parallelApiNotes): array
{
    $gaps = [];
    $wiredEvents = array_keys($wiring);
    $parallelEvents = array_keys($parallelApiNotes);

    foreach ($dispatched as $eventName => $meta) {
        if (in_array($eventName, $parallelEvents, true)) {
            continue;
        }

        if (in_array($eventName, $wiredEvents, true)) {
            continue;
        }

        $gaps[] = [
            'event' => $eventName,
            'phase' => $meta['phase'],
            'service' => $meta['service'],
            'severity' => 'info',
            'detail' => 'Dispatched by Ibexa but not wired in LegacyWorkflowBundle subscribers',
        ];
    }

    foreach ($supportedOperations as $operation) {
        $hasBefore = (bool) array_filter(
            $wiring,
            static fn (array $row): bool => $row['phase'] === 'before'
                && in_array($operation, operationsForWiringRow($row), true)
        );

        $hasAfter = (bool) array_filter(
            $wiring,
            static fn (array $row): bool => $row['phase'] === 'after'
                && in_array($operation, operationsForWiringRow($row), true)
        );

        if (!$hasBefore) {
            $gaps[] = [
                'event' => $operation,
                'phase' => 'before',
                'service' => 'LegacyWorkflowBundle',
                'severity' => 'error',
                'detail' => 'Supported operation missing before subscriber wiring',
            ];
        }

        if (!$hasAfter) {
            $gaps[] = [
                'event' => $operation,
                'phase' => 'after',
                'service' => 'LegacyWorkflowBundle',
                'severity' => 'error',
                'detail' => 'Supported operation missing after subscriber wiring',
            ];
        }
    }

    return $gaps;
}

/** @param array<string, mixed> $report */
function buildSummary(array $report): array
{
    $operations = count($report['supported_operations']);
    $beforeCovered = count(array_filter($report['coverage'], static fn (array $r): bool => $r['before_extension_ok']));
    $afterCovered = count(array_filter($report['coverage'], static fn (array $r): bool => $r['after_extension_ok']));
    $errors = count(array_filter($report['gaps'], static fn (array $g): bool => $g['severity'] === 'error'));
    $docDeprecated = count(array_filter($report['deprecated'], static fn (array $d): bool => $d['kind'] === 'docblock_deprecated'));

    $exitCode = 0;
    if ($errors > 0 || $beforeCovered < $operations) {
        $exitCode = 1;
    }

    return [
        'supported_operations' => $operations,
        'ibexa_dispatched_workflow_events' => count($report['ibexa_dispatched_events']),
        'legacy_wired_events' => count($report['legacy_subscriber_wiring']),
        'one_for_all_before_types' => count($report['one_for_all_before']),
        'on_after_bundle_types' => count($report['on_after_bundles']),
        'before_extension_coverage' => "{$beforeCovered}/{$operations}",
        'after_extension_coverage' => "{$afterCovered}/{$operations}",
        'unwired_ibexa_events' => count(array_filter(
            $report['gaps'],
            static fn (array $g): bool => $g['service'] !== 'LegacyWorkflowBundle'
        )),
        'coverage_errors' => $errors,
        'docblock_deprecated_events' => $docDeprecated,
        'legacy_alias_events' => count(array_filter(
            $report['deprecated'],
            static fn (array $d): bool => $d['kind'] === 'legacy_ez_publish_alias'
        )),
        'exit_code' => $exitCode,
    ];
}

function extractDeprecationFromDocblock(string $contents): ?string
{
    if (!preg_match('/\/\*\*(.*?)\*\//s', $contents, $doc)) {
        return null;
    }

    if (!preg_match('/@deprecated\s+(.+)/i', $doc[1], $dep)) {
        return null;
    }

    return trim(preg_replace('/\s+/', ' ', $dep[1]));
}

/** @param array<string, mixed> $report */
function renderTextReport(array $report, bool $verbose): void
{
    $s = $report['summary'];

    echo "Workflow hook coverage audit\n";
    echo str_repeat('=', 72) . "\n\n";

    echo "Summary\n";
    echo "  Supported operations:              {$s['supported_operations']}\n";
    echo "  Ibexa dispatched (workflow svc): {$s['ibexa_dispatched_workflow_events']}\n";
    echo "  LegacyWorkflowBundle wired:        {$s['legacy_wired_events']}\n";
    echo "  OneForAllBefore event types:       {$s['one_for_all_before_types']} ({$s['before_extension_coverage']})\n";
    echo "  On*After bundle event types:       {$s['on_after_bundle_types']} ({$s['after_extension_coverage']})\n";
    echo "  Unwired Ibexa events (info):       {$s['unwired_ibexa_events']}\n";
    echo "  Coverage errors:                   {$s['coverage_errors']}\n";
    echo "  @deprecated contract events:       {$s['docblock_deprecated_events']}\n";
    echo "  Legacy eZ Publish class_alias:     {$s['legacy_alias_events']}\n\n";

    echo "Per-operation coverage\n";
    echo str_repeat('-', 72) . "\n";
    printf("%-28s %-8s %-8s %-8s %-8s\n", 'Operation', 'Legacy B', 'Legacy A', 'BeforeEx', 'AfterEx');
    foreach ($report['coverage'] as $row) {
        printf(
            "%-28s %-8s %-8s %-8s %-8s\n",
            $row['operation'],
            $row['legacy_before_ok'] ? 'yes' : 'NO',
            $row['legacy_after_ok'] ? 'yes' : 'NO',
            $row['before_extension_ok'] ? 'yes' : 'NO',
            $row['after_extension_ok'] ? 'yes' : 'NO'
        );
    }
    echo "\n";

    if ($report['parallel_api'] !== []) {
        echo "Parallel Ibexa APIs (intentional mapping choices)\n";
        echo str_repeat('-', 72) . "\n";
        foreach ($report['parallel_api'] as $row) {
            echo "  {$row['event']}\n";
            echo "    preferred: {$row['preferred']} (wired: " . ($row['preferred_wired'] ? 'yes' : 'no') . ")\n";
            echo "    note: {$row['note']}\n";
        }
        echo "\n";
    }

    $errors = array_filter($report['gaps'], static fn (array $g): bool => $g['severity'] === 'error');
    if ($errors !== []) {
        echo "Coverage errors\n";
        echo str_repeat('-', 72) . "\n";
        foreach ($errors as $gap) {
            echo "  [ERROR] {$gap['event']} ({$gap['phase']}): {$gap['detail']}\n";
        }
        echo "\n";
    }

    if ($verbose) {
        $infos = array_filter($report['gaps'], static fn (array $g): bool => $g['severity'] === 'info');
        if ($infos !== []) {
            echo "Unwired Ibexa workflow events (candidates for future mapping)\n";
            echo str_repeat('-', 72) . "\n";
            foreach ($infos as $gap) {
                echo "  [INFO] {$gap['event']} ({$gap['phase']}, {$gap['service']}): {$gap['detail']}\n";
            }
            echo "\n";
        }

        $docDep = array_filter($report['deprecated'], static fn (array $d): bool => $d['kind'] === 'docblock_deprecated');
        if ($docDep !== []) {
            echo "Events marked @deprecated in Ibexa contracts\n";
            echo str_repeat('-', 72) . "\n";
            foreach ($docDep as $item) {
                echo "  {$item['event']}: {$item['detail']}\n";
            }
            echo "\n";
        }
    }

    if ($s['exit_code'] === 0) {
        echo "RESULT: PASS — supported operations are wired; extension bundles cover all before/after types.\n";
    } else {
        echo "RESULT: FAIL — see coverage errors above.\n";
    }
}