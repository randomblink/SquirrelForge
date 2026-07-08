# SquirrelForge Private Records

SquirrelForge Private Records is a WordPress plugin that registers a private custom post type and exposes authorized read access through a custom REST API route.

## Custom Post Type

The plugin registers:

- Display name: `Private Record`
- Post type key: `sf_private_record`
- Admin UI: enabled
- Custom fields/meta: none for v1

## Privacy Behavior

Private Records are intended for admin-managed, non-public content.

The custom post type is configured with:

- no public frontend archive;
- no public single URL;
- no search indexing;
- not publicly queryable;
- no native CPT REST controller exposure.

The admin UI remains available for users with appropriate WordPress admin permissions.

## REST API

The plugin registers a custom REST route instead of using the native CPT REST controller.

| Field | Value |
|---|---|
| Namespace | `squirrelforge/v1` |
| Route | `/private-records` |
| Method | `GET` |
| Full path | `/wp-json/squirrelforge/v1/private-records` |

### Query Parameters

| Parameter | Type | Default | Maximum | Description |
|---|---|---:|---:|---|
| `page` | positive integer | `1` | — | Page of private records to return. |
| `per_page` | positive integer | `20` | `100` | Number of private records to return per page. |

## Permission Rule

REST access requires the `manage_options` capability.

The route uses a permission callback equivalent to:

```php
current_user_can( 'manage_options' )
```

Unauthorized users must be rejected by the REST API permission check.

## Returned Statuses

The endpoint returns only records with these WordPress post statuses:

- `publish`
- `private`

Draft, pending, future, trash, and other statuses are not returned by this endpoint.

## Response Schema

The endpoint returns a response object containing `data` and `pagination`.

Each item in `data` contains:

| Field | Type | Description |
|---|---|---|
| `id` | integer | WordPress post ID. |
| `title` | string | Private Record title. |
| `date` | string | Creation date in Atom format. |
| `modified` | string | Modified date in Atom format. |
| `status` | string | WordPress post status. |

Example response:

```json
{
  "data": [
    {
      "id": 123,
      "title": "Example Private Record",
      "date": "2026-07-08T12:00:00+00:00",
      "modified": "2026-07-08T12:30:00+00:00",
      "status": "private"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 1,
    "total_pages": 1
  }
}
```
