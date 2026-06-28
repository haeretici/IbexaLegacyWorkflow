# /check-workflow-hooks

Audit whether **LegacyWorkflowBundle** and extension bundles cover Ibexa Repository workflow hooks, and surface deprecated or parallel APIs.

Use when the user asks to:
- verify `OneForAllBeforeWorkflowBundle` covers all before hooks
- check if workflow bundles match what Ibexa provides
- find deprecated or unwired Repository events
- run a workflow hook coverage report

## What to run

From the repository root:

```bash
php CHECK_FEATURES/scripts/check_workflow_hooks.php
```

Options:
- `--verbose` — also list unwired Ibexa events and `@deprecated` contract markers
- `--json` — machine-readable full report on stdout

Save output when reporting back to the user:

```bash
php CHECK_FEATURES/scripts/check_workflow_hooks.php --verbose | tee /tmp/workflow-hooks-audit.log
```

Exit codes: `0` = pass, `1` = coverage gap, `2` = vendor/Ibexa tree missing.

## What the script scans

| Source | Path | Purpose |
|--------|------|---------|
| Ibexa contracts | `vendor/ibexa/core/src/contracts/Repository/Events/{Content,Location,Trash,ObjectState,Section}/` | Event classes that exist in the CMS API |
| Ibexa dispatchers | `vendor/ibexa/core/src/lib/Event/{Content,Location,Trash,ObjectState,Section}Service.php` | Events actually fired at runtime |
| Core wiring | `LegacyWorkflowBundle/EventSubscriber/*WorkflowSubscriber.php` | `getSubscribedEvents()` → operation + before/after phase |
| Supported scope | `LegacyWorkflowBundle/Workflow/SupportedOperations.php` | Operations expected in admin triggers |
| Before extensions | `OneForAllBeforeWorkflowBundle/Workflow/EventType/On*BeforeEventType.php` | `event_haeretici_on*before` coverage |
| After extensions | `On*AfterWorkflowBundle/Workflow/EventType/On*AfterEventType.php` | Per-operation after sample bundles |

## How to interpret results

### Summary table (per-operation)

| Column | Meaning |
|--------|---------|
| Legacy B | `LegacyWorkflowBundle` subscribes to at least one **before** Ibexa event for this operation |
| Legacy A | Same for **after** |
| BeforeEx | `OneForAllBeforeWorkflowBundle` defines a matching `On*BeforeEventType` |
| AfterEx | Matching `On*AfterWorkflowBundle` exists |

All four columns should be **yes** for every row in `SupportedOperations`.

### Parallel Ibexa APIs

Some Ibexa services expose multiple events for the same user action (e.g. `BeforeHideContentEvent` vs `BeforeHideLocationEvent`). The script lists these with the **preferred** event LegacyWorkflowBundle uses and a note from `SUPPORTED.md` intent. These are not failures.

### Unwired Ibexa events (info)

Events dispatched by Ibexa workflow services but not subscribed by LegacyWorkflowBundle. Examples: `BeforeCopyContentEvent`, `BeforeRecoverEvent`, `BeforeEmptyTrashEvent`. Cross-check with `SUPPORTED.md` before treating as a bug — many are intentionally out of scope.

### Deprecation signals

1. **`docblock_deprecated`** — `@deprecated` in the Ibexa event contract file (true deprecation).
2. **`legacy_ez_publish_alias`** — `class_alias(..., 'eZ\\Publish\\API\\...')` for BC; informational only.

## Agent workflow

1. Run the script (`--verbose` unless the user wants a short summary).
2. If exit code is non-zero, read **Coverage errors** first and fix `LegacyWorkflowBundle` or extension bundles.
3. Compare **Unwired Ibexa events** against `SUPPORTED.md` — propose new operations only when the user asks to expand scope.
4. When `OneForAllBeforeWorkflowBundle` is missing a before type, add `On{Operation}BeforeEventType` under `Workflow/EventType/` and tag it in `Resources/config/services.yaml`.
5. Re-run the script until exit code `0`.
6. Optionally run `cd LegacyWorkflowBundle && php phpunit.phar -c phpunit.xml.dist` after code changes.

## Prerequisites

- `composer install` must have populated `vendor/ibexa/core`.
- No Symfony kernel or Ibexa instance required — static file analysis only.

## Related docs

- [SUPPORTED.md](../../SUPPORTED.md) — intentional scope boundaries
- [OneForAllBeforeWorkflowBundle/README.md](../../OneForAllBeforeWorkflowBundle/README.md) — consolidated before types
- [AGENTS.md](../../AGENTS.md) — bundle architecture