Status: Stable

---
# SquirrelForge WordPress Media Knowledge

## Purpose

Defines knowledge for uploads, attachments, image handling, and media safety across plugins, themes, and blocks.

## Review Areas

Review upload validation, MIME type and file-type checking, attachment metadata handling, image sizing and srcset generation, media library integration, and storage/serving of user-supplied files.

## Output

This Knowledge file must support:

- media handling review notes;
- upload-safety risk classification;
- image and attachment processing recommendations;
- storage and serving guidance;
- and media validation handoff.

## Validation Requirements

Media guidance is valid only when:

- uploaded files are validated by actual file content and MIME type via `wp_check_filetype()` and equivalent checks, never by client-supplied filename or MIME header alone;
- executable and other disallowed file types are rejected regardless of extension spoofing;
- attachments are created through WordPress's media APIs (`wp_insert_attachment()`, `wp_generate_attachment_metadata()`) rather than direct filesystem writes outside the upload pipeline;
- image sizes and `srcset` are generated through WordPress's registered image-size and responsive-image APIs;
- attachment metadata and alt text are sanitized and escaped for their output context;
- and user-supplied files are never served or stored in a way that allows script execution from the uploads directory.

## Handoff Rules

- Upload validation and file-safety issues route to `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`.
- Media processing and attachment implementation route to the relevant `38_WORDPRESS/ROLES/PHP-ENGINEER.md` implementation owner.
- Image/asset performance concerns route to `38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md`.
- Media-related documentation routes to `38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md`.

## Completion Criteria

This Knowledge file is complete when media work can be reviewed for upload validation, safe storage, attachment API usage, and responsive image handling.

## Rule

Uploaded files must be validated by actual content and type, never by client-supplied name or header alone, and the uploads directory must never allow script execution.
