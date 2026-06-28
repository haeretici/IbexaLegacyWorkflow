# Haeretici OnShowAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_show`) hook of `content_show` (location reveal / unhide).

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onshowafter` |
| Admin label | On show after |
| Allowed trigger | `content` / `show` / `after` only |
| Log file | `var/log/OnShowAfterEventType.log` |

Enable this bundle alone to test `content_show` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnShowAfterWorkflowBundle\` → `OnShowAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnShowAfterWorkflowBundle\OnShowAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On show after** (`event_haeretici_onshowafter`).
2. Assign the workflow to **content_show** → **after** in Settings → Triggers.
3. Reveal a hidden location in the admin UI (or call `TriggerRunner` in tests with `trigger_name` = `post_show`, `module_name` = `content`, `module_function` = `show`, `connect_type` = `after`).
4. Inspect `var/log/OnShowAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.