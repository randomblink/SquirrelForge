# SquirrelForge WordPress Knowledge: Settings API

## Knowledge Metadata

| Field | Value |
|---|---|
| Domain | WordPress |
| Topic | Settings API |
| Applies To | Plugins, themes, administration interfaces, REST-exposed settings |
| Primary Authority | WordPress Settings API and Options API behavior |
| Security Priority | High |
| Review Trigger | WordPress API changes, security policy changes, or storage architecture changes |

## Purpose

This document defines the authoritative SquirrelForge guidance for registering, rendering, validating, sanitizing, storing, and retrieving WordPress settings.

SquirrelForge must use the Settings API for conventional administration settings unless a documented requirement makes another storage mechanism more appropriate.

## Scope

This guidance covers:

- Settings registration.
- Option groups and option names.
- Settings sections and fields.
- Administration page rendering.
- Sanitization and validation callbacks.
- Capability enforcement.
- Nonce handling through WordPress settings forms.
- Option retrieval and output escaping.
- REST API exposure of registered settings.
- Registration, update, and uninstall behavior.

It does not define custom-table schemas, post metadata architecture, user metadata architecture, or secret-storage policy beyond identifying when the Options API is inappropriate.

## Core Principle

Settings must be registered explicitly, sanitized before persistence, authorized before modification, and escaped for their output context.

Components must not process arbitrary settings form submissions manually when the Settings API provides the required behavior.

## Required Practices

### Register Settings

Register settings on `admin_init` with `register_setting()`.

Every registration must define:

- A stable option group.
- A unique, prefixed option name.
- An explicit data type where practical.
- A safe default value where appropriate.
- A `sanitize_callback` suitable for the complete option value.
- REST visibility only when explicitly required.

```php
register_setting(
    'squirrelforge_example_group',
    'squirrelforge_example_options',
    [
        'type'              => 'array',
        'default'           => [],
        'sanitize_callback' => 'squirrelforge_sanitize_example_options',
        'show_in_rest'      => false,
    ]
);
```

### Define Sections and Fields

Use `add_settings_section()` to group related settings and `add_settings_field()` to register individual fields.

Identifiers must be unique, stable, and project-prefixed. Field callbacks render controls; they must not perform persistence or unrelated business logic.

### Render Settings Forms

Settings pages must:

1. Verify the required capability before rendering.
2. Submit to `options.php` unless a documented design requires a custom handler.
3. Call `settings_fields()` for the registered option group.
4. Call `do_settings_sections()` for the page slug.
5. Call `submit_button()` or provide an equally accessible submission control.

```php
if (!current_user_can('manage_options')) {
    return;
}
?>
<form action="options.php" method="post">
    <?php
    settings_fields('squirrelforge_example_group');
    do_settings_sections('squirrelforge-example');
    submit_button();
    ?>
</form>
<?php
```

### Sanitize Complete Option Values

When one option stores an array, its sanitization callback must validate the entire array and return only approved keys.

```php
function squirrelforge_sanitize_example_options(array $input): array
{
    return [
        'label'   => sanitize_text_field($input['label'] ?? ''),
        'enabled' => !empty($input['enabled']),
        'limit'   => absint($input['limit'] ?? 0),
    ];
}
```

Unknown fields must not be copied into the stored option automatically.

### Retrieve and Escape Values

Use `get_option()` with a safe default. Treat stored values as untrusted and escape at the point of output.

| Context | Required Handling |
|---|---|
| Plain text | `esc_html()` |
| HTML attribute | `esc_attr()` |
| URL | `esc_url()` |
| Rich HTML | An approved `wp_kses()` policy |
| JSON response | `wp_json_encode()` or WordPress REST response handling |

### Manage Option Lifecycles

- Add defaults only when needed; avoid unnecessary database writes during every request.
- Update options through approved WordPress APIs.
- Delete temporary or project-owned options during uninstall when retention is not required.
- Do not delete shared or user-owned data without an explicit uninstall policy.
- Do not use autoloaded options for large or rarely used values without justification.

## Standard Workflow

1. Define the setting owner, purpose, schema, default, and lifecycle.
2. Select the correct WordPress storage API.
3. Define the capability required to view and modify the setting.
4. Register the setting, section, and fields.
5. Implement complete sanitization and validation.
6. Render the form with WordPress security fields.
7. Retrieve values with safe defaults.
8. Escape every rendered value for its destination context.
9. Test authorized, unauthorized, valid, invalid, missing, and legacy values.
10. Document migration and uninstall behavior.

## Security Requirements

- Settings pages must use an appropriate capability such as `manage_options` or a narrower project capability.
- Settings forms must include and verify the security fields generated for the registered option group.
- Input must be unslashed where required before sanitization.
- Sanitization callbacks must not trust field names, types, or nested structures.
- Sensitive credentials must not be displayed after storage.
- Secrets must not be stored in ordinary options when approved secure configuration is available.
- REST-exposed settings require deliberate schema, permission, and data-classification review.
- Error messages must not disclose credentials, tokens, internal paths, or protected configuration.

## Validation Checklist

- [ ] The setting has a unique, prefixed option name.
- [ ] Registration occurs on the correct hook.
- [ ] The option group used by `register_setting()` matches `settings_fields()`.
- [ ] The settings page enforces an appropriate capability.
- [ ] A complete sanitization callback is registered.
- [ ] Each field validates its expected type, range, and allowed values.
- [ ] Unknown input keys are discarded.
- [ ] Stored values are escaped at output.
- [ ] Defaults and missing values are handled safely.
- [ ] REST exposure is disabled unless required and reviewed.
- [ ] Large values and autoload behavior have been evaluated.
- [ ] Migration and uninstall behavior are documented.
- [ ] Sensitive values are excluded from logs and error output.

## Common Failure Conditions

SquirrelForge must reject or require revision when code:

- Saves settings without authorization.
- Omits Settings API security fields or bypasses required nonce verification.
- Registers a setting without appropriate sanitization.
- Stores raw request data.
- Outputs stored values without context-appropriate escaping.
- Uses generic or conflicting option names.
- Exposes protected settings through REST without explicit permission design.
- Stores secrets insecurely.
- Writes large values to autoloaded options without justification.
- Deletes data during uninstall without an approved retention decision.

## Related Knowledge

- `SECURITY.md`
- `../SECURITY-VALIDATOR.md`
- `../CODING-STANDARDS.md`
- `../DATABASE.md`
- `../REST-API.md`
- `../PLUGIN-HANDBOOK.md`

## Rule

No WordPress setting may be approved until its registration, authorization, sanitization, persistence, retrieval, output escaping, and lifecycle behavior have been explicitly validated.
