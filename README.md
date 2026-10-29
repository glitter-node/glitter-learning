# Glitter Learning

Learner-facing layouts use only host baseline HTML components and do not depend on a specific user template's composite component library. Course, enrollment, progress, and completion behavior remains owned by the module API.

Glitter Learning is a Gnuboard7 module for free course publishing, enrollment, lesson progress, and completion history.

## Required plugin

Glitter Learning requires the official `sirsoft-ckeditor5` plugin (`>=1.1.1`).
Install and activate the plugin before activating or updating this module. The
administrator course form continues to use Gnuboard7's official `html_editor`
extension point; the module does not bundle or replace the CKEditor plugin.

## Search provider declaration

The module declares `courses` and `lessons` through `getSearchProviders(): array`. Glitter Search Hub discovers these declarations from active modules and keeps the existing `SearchLearningListener` responsible for query execution and access control. Courses expose published title/description matches; lessons remain subject to the module's visibility and enrollment checks.

## Scope

- Administrators manage courses and lessons and review enrollment/completion records.
- Members enroll in published courses, manually complete lessons, and review progress.
- Completion requires every required lesson; courses without required lessons are not auto-completed.
- Payments, exams, assignments, live classes, PDF certificates, AI grading, and custom media/search/analytics engines are outside this release.

## Course authoring (1.1.1)

The administrator form groups basic information, learning design, content, and publication settings. Existing `description` values remain plain text (`description_mode=text`); editing does not convert them to HTML. New HTML descriptions use the host `HtmlEditor`, and the module sanitizes them on save and through the authenticated preview endpoint. Only `h2`, `h3`, `p`, `ul`, `ol`, `li`, `strong`, `em`, `br`, and `blockquote` are stored; attributes and executable/embed/form elements are removed. Text mode is escaped by the G7 renderer. The module's baseline user layout still renders description as text. A template wishing to display sanitized HTML formatting must use its own safe HTML renderer and honor `description_mode`.

Learning objectives are an ordered JSON list. Category and difficulty are stable language-independent identifiers. Estimated duration is stored as nonnegative minutes. The existing publication status and `published_at` condition are evaluated at read time; no new scheduler is used. The new columns are additive and nullable except `description_mode`, which defaults to `text` for old courses.

The optional cover image uses the official administrator attachment upload endpoint. The editor needs the host `core.attachments.create` permission in addition to course permissions. Only an image uploaded by that editor for `glitter-learning` and `course_thumbnail` can be claimed; the module checks MIME, size, and ownership again at save time. Replacing or clearing the cover does not delete the previous attachment, so historical files remain available until a separately approved cleanup policy is defined.

The 1.1.1 security patch hardens the HTML sanitizer's DOM traversal when unwrapping CKEditor figure elements, including nested wrappers and moved siblings. It also fixes safe-link validation. No database schema changes are included.

## Lifecycle

Use the Gnuboard7 module lifecycle commands to build, install, and activate this bundled source. Do not edit the installed module directory directly.

## Starter course sample data

The bundled sample seeder can create six published courses and 26 published lessons. It requires an existing member ID as the course author and never creates enrollment, progress, or completion records.

```bash
php artisan module:seed glitter-learning --sample --count=author_id=<user-id> --force
```

The seeder checks stable slugs and lesson content inside a transaction. Existing or conflicting records are preserved and conflicting data aborts the run. Run it only after explicit production-data approval.
