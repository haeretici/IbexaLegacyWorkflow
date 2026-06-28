# Haeretici OnMoveAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_move`) hook of `content_move`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onmoveafter` |
| Admin label | On move after |
| Allowed trigger | `content` / `move` / `after` only |
| Log file | `var/log/OnMoveAfterEventType.log` |

Enable this bundle alone to test `content_move` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnMoveAfterWorkflowBundle\` → `OnMoveAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnMoveAfterWorkflowBundle\OnMoveAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On move after** (`event_haeretici_onmoveafter`).
2. Assign the workflow to **content_move** → **after** in Settings → Triggers.
3. Invoke the operation (or call `TriggerRunner` in tests with `trigger_name` = `post_move`, `module_name` = `content`, `module_function` = `move`, `connect_type` = `after`).
4. Inspect `var/log/OnMoveAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
