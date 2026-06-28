# Haeretici OnSwapAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_swap`) hook of `content_swap`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onswapafter` |
| Admin label | On swap after |
| Allowed trigger | `content` / `swap` / `after` only |
| Log file | `var/log/OnSwapAfterEventType.log` |

Enable this bundle alone to test `content_swap` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnSwapAfterWorkflowBundle\` → `OnSwapAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnSwapAfterWorkflowBundle\OnSwapAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On swap after** (`event_haeretici_onswapafter`).
2. Assign the workflow to **content_swap** → **after** in Settings → Triggers.
3. Invoke the operation (or call `TriggerRunner` in tests with `trigger_name` = `post_swap`, `module_name` = `content`, `module_function` = `swap`, `connect_type` = `after`).
4. Inspect `var/log/OnSwapAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
