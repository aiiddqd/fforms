---
status: current
updated: 2026-08-30
---

# FForms: base specification

## 1. Purpose and scope

FForms is a lightweight WordPress plugin for contact forms and lead capture. It stores forms and entries inside WordPress, accepts submissions over the REST API, renders forms through a dynamic Gutenberg block, and sends email notifications.

This specification describes what version `1.0.0` actually implements. Ideas from `ROADMAP.md` and from active RFCs are out of scope here.

Current system requirements:

- WordPress 6.5 or newer;
- PHP 8.0 or newer;
- JavaScript in the browser to submit a form rendered by the Gutenberg block.

## 2. Primary user flow

1. The user creates a post under **FForms → Add form**. A form has one shape: the `fforms/form` block tree in `post_content`. There is no mode to choose.
2. With global mail settings enabled, the user turns on the notification for that specific form and configures recipients, the success message, and optionally an autoreply.
3. The user publishes the form. Every published form is then insertable through the `fforms/form` block and the `[fform id=…]` shortcode; the "Share via link" toggle in the Publication panel additionally opens its token URL, iframe, and js-script.
4. A public submission is validated server-side and stored as a private entry with status `new`.
5. An administrator reviews the entry, changes its status, and exports the data to CSV when needed.

An integration that has no form in wp-admin does not follow this flow at all: it posts to `POST /fforms/v1/main`, which declares no schema and stores whatever it is sent (§5.2). The mode is a property of the entry point, not of the form.

The `contact` and `lead` types currently differ only by the stored type value; submission handling is identical for both.

## 3. Data model

The plugin uses two custom post types and one taxonomy.

| Entity | Storage | Notes |
| --- | --- | --- |
| Form | `fform` | Non-public CPT with an admin UI and REST support. Holds the title, JSON schema, and mail settings. The form's own business meaning is its `fform_type` term, not a separate field. |
| Entry | `fform_entry` | Private post with no public WordPress REST exposure. Creating one from the admin is forbidden; viewing and managing require `manage_options`. |
| Form type | `fform_type` | Non-public flat taxonomy for `fform_entry` and `fform`: the business meaning of a submission ("Consultation request"), independent of which form received it. See §3.1. |

Form meta:

- `_fforms_share_link` — boolean, `false` by default: whether the form is reachable through its share link, the embed view, and the two embed snippets;
- `_fforms_share_layout` — `site` (default) or `standalone`: whether the share link opens inside the theme's page or as a page of the form alone. Anything else falls back to `site`, so forms that predate the setting render exactly as before;
- `_fforms_share_token` — 16 hex characters from `random_bytes()`, issued on the form's first publish and used as its only public address. Reissuing it invalidates the previous link immediately;
- `_fforms_schema` — the normalized JSON schema: a derived cache of the schema compiled from blocks, and the compatible format for legacy forms that have no blocks;
- `_fforms_notifications_enabled` — enables the main notification for the form;
- `_fforms_notification_to`, `_fforms_notification_subject` — notification recipients and subject;
- `_fforms_success_message` — message shown after a successful submission;
- `_fforms_autoreply_*` — autoreply toggle, email field, subject, and body.

Entry meta:

- `_fforms_form_id` — link to the form;
- `_fforms_data` — validated data as JSON;
- `_fforms_status` — `new`, `read`, `replied`, or `spam`;
- `_fforms_form_key` — key of the code form or of the built-in main form (`main`) when the entry did not go to a CPT form;
- `_fforms_source`, `_fforms_ip`, `_fforms_user_agent` — request source and technical data;
- `_fforms_custom` — legacy JSON of off-schema fields, written by `POST /main` before it dropped its schema. It is still read on the entry screen and in CSV; nothing writes it any more;
- `_fforms_meta` — JSON of arbitrary request context: depth at most 3, at most 8 KiB once encoded;
- `_fforms_ref` — referral marker (`utm_source`, `ref`), up to 200 characters;
- `_fforms_user_id` — client-supplied user identifier. It grants nothing, and the admin renders it as a link only when such a user exists;
- `_fforms_form_type_raw` — the incoming form type value, kept when no term was created because the limit was reached;
- `_fforms_created_post_id` is registered for future content forms but is currently unused.

### 3.1. The `fform_type` taxonomy

`public => false`, `publicly_queryable => false`, `show_ui => true`, `show_in_rest => false`, flat, `show_admin_column => true`; every capability maps to `manage_options`. The "Form types" screen is added to the FForms menu once, right after "Submissions". A form has no type field of its own: the form *is* the type, through the term linked to it below. The `_fforms_type` meta (`contact`/`lead`) that predated the taxonomy is no longer registered, written, or read, and the one-time `Type_Meta_Migration` drops its remaining rows on the next admin request.

- Value normalization: `sanitize_key`, pattern `^[a-z0-9_-]{1,32}$`; anything else returns HTTP 422 `fforms_invalid_form_type`.
- Upsert by slug: an unknown slug creates a term with `name = slug` and the `_fforms_autocreated` meta. Renaming the term in the admin does not affect matching — the link is by slug.
- The autocreation limit is 50 terms (`fforms_max_form_types` filter). Beyond the limit no term is created, the submission is still stored, and the value goes to `_fforms_form_type_raw`.
- Strict mode (the "Accept existing types only" setting) answers 422 `fforms_unknown_form_type` for an unknown slug and creates no entry.
- **The form and its term are linked both ways.** Publishing a CPT form creates a term with `slug = post_name` (a numeric suffix is appended when the slug is taken) and `name` = the form title, assigns the term to the form itself, and writes the form ID into the term meta `_fforms_form_id`. Renaming the form changes only the term `name`; the slug stays stable. Deleting the form does not delete the term — entries keep their classification and only `_fforms_form_id` is cleared. Forms published before the taxonomy existed get their term on the next save.
- Entries from the block, the shortcode, and `POST /submit` inherit their form's term; a form without a term stores entries without a type.

## 4. Form schema and validation

The schema is stored in this format:

```json
{
  "fields": [
    {
      "name": "email",
      "label": "Email",
      "type": "email",
      "required": true,
      "placeholder": "name@example.com",
      "max_length": 200
    }
  ]
}
```

Supported types are `text`, `textarea`, `email`, `tel`, `url`, `number`, `select`, `radio`, `checkbox`, `hidden`. For choice fields the `options` property accepts strings or `{"value":"...","label":"..."}` objects.

Normalization rules:

- a field name is reduced to a safe WordPress key;
- duplicates, unnamed fields, and unknown types are dropped;
- at most 50 fields per form and 100 options per field;
- `max_length` is clamped to the 1–10,000 range;
- an invalid or empty schema falls back to the default "Name", "Email", "Message" fields;
- incoming `fields` keys that are not in the schema are not stored;
- required, email, URL, number, and allowed-option constraints are checked server-side;
- values are sanitized and truncated: 2,000 characters by default, 10,000 for `textarea`.

## 5. REST API

Namespace: `fforms/v1`.

| Method and route | Access | Purpose |
| --- | --- | --- |
| `POST /submit` | public | Validates and stores an entry; strict contract of `form_id`/`form_key` plus `fields`. |
| `POST /main` | public | Schema-free flat payload of the built-in main form. See §5.2. |
| `GET /forms` | public | The built-in main form plus up to 100 published forms in alphabetical order; every CPT form carries `share_link`. |
| `GET /forms/{id\|key}` | public | The form, its `share_link`, schema, success message, and submit URL. `key` is a code-form key, `main`, or the slug of the form's term. |
| `GET /forms/{id\|key}/schema` | public | The normalized schema only. |
| `POST /forms/{id}/share-token` | `edit_post` | Issues a new share token and returns the new URL and snippets. The previous link stops working at once. |
| `GET /entries` | `manage_options` | Entries with pagination and `form_id`/`form_key`/`form_type`/`status` filters. |
| `POST /entries/{id}/status` | `manage_options` | Changes the workflow status of an entry. |

### 5.1. Public form page

A form with `_fforms_share_link` on is additionally reachable without authentication at `/forms/{token}/` (`Public_Form`, rewrite rule `^forms/([a-f0-9]{16})/?$`). The page renders the same block markup as a regular `fforms/form` block on the site. Access is checked by `status === 'publish'` and `Public_Form::is_enabled( $id )`; a missing form, a different status, or the toggle being off returns a 404 page.

The request picks one of three renderings, in this order:

- `?fforms_embed=1` — the bare frame document (§7.2), whatever `_fforms_share_layout` says: inside someone else's page the chrome is that page's business, so the snippets never change when the layout does;
- `_fforms_share_layout = standalone` — a page of the form alone: `wp_head()`/`wp_footer()` but no theme header, footer, navigation or admin bar, and, unlike the frame, the form's title and the same padded, centred `fforms-public-form__content` column as the full page (`body` class `fforms-standalone`);
- otherwise — the full theme page through `get_header()`/`get_footer()`.

All three block indexing identically and the height reporter is enqueued in all of them; outside a frame it does not activate.

The address is the secret. `/forms/{id}/` no longer exists, so a form cannot be found by walking post IDs, and a token that is reissued invalidates every link already shared. The page is kept out of search entirely: `wp_robots` emits `noindex, nofollow`, the response carries the same `X-Robots-Tag` header, and the `fform` CPT is non-public, so neither the form nor its token URL appears in the sitemap or in site search.

The public submit accepts:

```json
{
  "form_id": 123,
  "fields": { "email": "name@example.com" },
  "website": "",
  "source": "https://example.com/contact"
}
```

A successful submission returns HTTP 201, `entry_id`, the custom message, and the result of sending the main notification. Validation errors return HTTP 422 and a per-field error object. Responses 404, 413, 429, and 500 are also defined.

### 5.2. The built-in main form and `POST /main`

The main form exists only in memory (`Registry\Main_Form`): `post_id = 0`, `key = main`, `source = builtin`, and **no schema at all**. It creates no database records, is not editable in the admin, and cannot be embedded by the block. The `main` key is reserved — `fforms_add_api_route( 'main', … )` returns a `WP_Error` with code `fforms_reserved_key`. It works right after activation, with no form to create first.

`POST /main` accepts a flat payload; every parameter is optional:

| Parameter | Purpose |
| --- | --- |
| `formType` (aliases `formId`, `form_type`, `form_id`) | The type of the submission, and nothing else. Aliases carrying different values return 400 `fforms_form_ref_conflict`. |
| any other top-level key | A field of the submission. Stored in `_fforms_data` under `sanitize_key()` of the key. |
| `meta`, `ref`, `userId` | Request context; see the meta keys in §3. |
| `_hp` | Honeypot for this route: when filled, returns 200 with no entry. On `/submit` the honeypot is still `website`. |
| `source` | Same as on `/submit`; falls back to `Referer` when absent. |
| `attachments` | Not accepted: 400 `fforms_attachments_require_multipart` in JSON, and 400 `fforms_attachments_disabled` for `multipart` with files. |

**There is no schema, so there is no validation.** `Schema::validate_submission()` is not called on this route: whatever keys arrive are what gets stored. Values are sanitized with `sanitize_text_field()` and truncated to 2,000 characters; a non-scalar value is stored as its JSON string rather than dropped; at most 50 fields are kept. The `email` key is additionally normalized with `sanitize_email()`, but an address that does not survive that is still stored — it only means no autoreply is sent. That is also why the honeypot here is named `_hp` rather than `website`: in a flat payload `website` is a perfectly ordinary user field.

**`formType` classifies, it never addresses.** A `/main` submission always goes to the built-in main form; the value is normalized to a slug and upserted into `fform_type`. Sending `formType: "123"` with the ID of an existing CPT form does *not* route the submission to that form — it creates an entry on the main form with the type `123`. Resolution by post ID, code-form key, or term slug lives on `POST /submit` only. When no type is given, the term `main` is assigned, so every `/main` entry is filterable.

A submission with no non-empty field at all is 422 `fforms_empty_submission`. An unusable type value is 422 `fforms_invalid_form_type`, and 422 `fforms_unknown_form_type` in strict mode. The rate limit is counted under the key `code:main`.

A successful response is 201 with `entry_id`, the message, and `form_type`.

## 6. Submission handling and anti-spam

Processing order:

1. Request body size check — at most 256 KiB by default.
2. Check that a published form exists.
3. Resolve the form (`/submit`) or the form type (`/main`).
4. Honeypot (`website` on `/submit`, `_hp` on `/main`): a filled honeypot gets a fake successful HTTP 200 response, but no entry, term, or email is created.
5. Rate limit — 5 attempts per 60 seconds per form + IP pair by default.
6. Data normalization, and server-side validation against the schema — on `/submit`; `/main` has no schema to validate against and only normalizes.
7. Creation of a private `fform_entry` and storage of source, IP, and User-Agent; request context (`meta`, `ref`, `userId`) is written to separate meta keys.
8. Assignment of the `fform_type` term.
9. Sending notifications and firing the `fforms_entry_created` action.

The rate limit counts invalid attempts too, but not honeypot hits. The IP comes from `REMOTE_ADDR`; proxy headers are not trusted automatically.

## 7. Gutenberg block and frontend

For any published form, the dynamic `fforms/form` block lets an editor:

- pick one of the published forms;
- jump straight to editing the picked form;
- use `wide` and `full` alignment.

Markup is generated server-side from the current schema. Fields have associated `label` elements, required fields carry `required`/`aria-required`, and the result message uses `role="status"` and `aria-live="polite"`. The client script collects the data, posts JSON through `fetch`, disables the button while the request is in flight, shows the result, and clears the form after success.

In the editor the block has two shapes, chosen by the post type being edited. Inside the `fform` CPT it is the builder itself: inner field blocks with a default template of name, email, message, and a submit button. On a regular page it is a reference. An empty reference (`ref = 0`) shows the "FForms" placeholder with the form picker; once a form is picked, the canvas renders that form through `GET /wp/v2/block-renderer/fforms/form`, so the editor sees the markup the front end will serve rather than a grey box. Only `ref` is sent to that route: block supports are already applied to the editor's own block wrapper, and sending them again would apply the padding, background, and border a second time. A failed or empty response falls back to the same placeholder with the picker, so the block never becomes a dead end. Under the picker, a block with a form selected offers an "Edit form" link to that form's editor, opened in a new tab so the unsaved page stays intact; the preview does not follow edits made there until the editor is reloaded.

The preview is inert. The Interactivity API is not enqueued in the editor, and `.fforms-block-preview` carries `pointer-events: none`, so a click anywhere in the rendered form selects the block instead of putting the caret into a field. `view.css` is not enqueued in the editor either, so the honeypot rules are repeated in the editor stylesheet. A reference block holds no inner blocks and no other settings: the page stores nothing but `<!-- wp:fforms/form {"ref":123} /-->`, and a change to the form reaches every page that references it.

Every published form is offered in the block picker and renders through a `ref`: there is no per-form setting that can withhold it. CSS and frontend JavaScript are enqueued only when the block actually rendered a published form. Styles are minimal and theme-agnostic, but there is no dedicated `theme.json`/Global Styles integration yet. Without JavaScript the form shows a warning and does not submit.

### 7.1. The `[fform id=123]` shortcode

The second insertion point is the `[fform id=123]` shortcode. Its only attribute is `id`, the post ID of the `fform` CPT; `[fform id=123]` and `[fform id="123"]` are equivalent. The shortcode does not duplicate markup — it calls the same server-side `Form_Renderer::render_form()` as the public form page, so markup, assets, and submit behavior match the `fforms/form` block with `ref=123`, down to the block's outer wrapper.

The unavailable-form policy is shared with the block: a missing or nonexistent `id`, a post of another type, and a draft render nothing — a visitor sees nothing, a user with `edit_posts` sees a text message. Code forms (`post_id=0`) are not reachable through the shortcode. `[fform id=X]` inside the content of form X itself hits the shared recursion guard and returns a circular-reference message.

Assets are enqueued along two paths: on `wp_enqueue_scripts` via `has_shortcode()` against the current post content (so styles land in `wp_head`), and again, idempotently, inside the callback itself — for renders coming from a widget or a theme template. On a page without a form no assets are enqueued.

The Publication panel of any published form shows the ready-to-copy `[fform id=<ID>]` value.

Both the shortcode and the block insert the form into `post_content`. Pages rendered by theme code that bypasses `the_content()` are served by neither — such a template must call `do_shortcode()` or `Form_Renderer::render_form()` itself.

### 7.2. External embedding: iframe and js-script

Two snippets sit on top of the share page `/forms/{token}/` for embedding on a third-party site, so both are available only for a published form whose "Share via link" toggle is on:

- `<iframe src="{home}/forms/{token}/?fforms_embed=1" style="width:100%;border:0" height="600" loading="lazy">` — a simple embed with a fixed height;
- `<script src="{plugin}/assets/embed.js" data-fforms-form="{id}" data-fforms-origin="{home}" data-fforms-src="{embed_url}"></script>` — the script replaces the tag with an iframe and keeps its height in sync.

The public form page, when opened inside a frame, sends the parent a `postMessage` of `{ type: 'fforms:height', formId, height }`. `assets/embed.js` applies the height only for messages coming from its own iframe's `contentWindow` with a matching `formId`, and sets it both as an attribute and as an inline style so a theme rule for `iframe` cannot override it.

The bare document force-resets `height`, `min-height`, and `overflow` on `html`/`body`: themes usually give them `height:100%` and their own `overflow`, which inside a frame turns body into a separate scroll container — the document then reports the frame's height instead of its own, so the form cannot be measured and gets clipped.

Height is measured from the content (`.fforms-embed__content`) rather than from `documentElement`, whose height is tied to the frame viewport; the maximum with `scrollHeight` is taken as well, to survive a theme that made body a scroll container anyway. An unchanged height is re-sent until the frame matches it: otherwise a frame that once ended up shorter than its content would stay clipped forever, because there would be nothing to send — the content height never changed. The page sends nothing outward besides the height and has no return channel, so `targetOrigin` is `'*'` (the embedding page's domain is not known in advance). Outside a frame the height reporter does not activate.

`assets/embed.js` runs on other people's sites: no dependencies, no build step, no global variables, and it survives several embeds of different forms on one page. The exact frame URL arrives in `data-fforms-src`; without that attribute the script assembles it from `data-fforms-origin` and `data-fforms-form`.

The `fforms_embed=1` parameter switches the public page into "bare" mode: a minimal HTML document with no header, footer, or admin bar, but still with `wp_head()`/`wp_footer()`, so block styles, Global Styles, and the Interactivity runtime work as usual. Without the parameter, `/forms/{token}/` stays a full theme page — it remains the link to share. Padding in bare mode is zero: spacing is the embedding page's job.

The form editor sidebar opens with an "Overview" panel: a "View submissions" link to the entry list filtered by this form's type term (falling back to the `form_ref` filter while the form has no term yet) and the number of stored submissions. Below it the two publication scenarios split into two independently collapsible panels. "Publication" is about this site and the sites that embed the form: the shortcode for any published form, and — while "Share via link" is on — the iframe and js-script snippets plus a "Reissue link" button. "Share via link" is about the link itself: the toggle that turns it on, the read-only link with "Open the form", and the "Page layout" choice between "With site header" (`site`) and "Form only" (`standalone`). The layout choice is shown only while the toggle is on and changes nothing about the snippets. Every snippet field is read-only and ready to copy. With the toggle off, both `/forms/{token}/` and its `?fforms_embed=1` view return 404 and the link fields are hidden.

## 8. Admin, mail, and export

The admin implements:

- creating and editing forms through the standard CPT interface;
- an "Overview" panel at the top of the form editor sidebar with the submission count and a link to this form's submissions;
- a "Publication" panel in the sidebar: the shortcode, the "Share via link" toggle, and under it the URL, the iframe and js-script snippets, and the reissue button;
- an overview page at `admin.php?page=fforms-dashboard` that opens the "Questions and answers" block with its first question, "How do I start accepting messages over the REST API?": the real `POST /fforms/v1/main` URL, the current settings state, the list of form types, and four ready-made request examples with a "Copy" button. The top-level menu slug stays `fforms` (both CPTs and the settings and export pages use it as their parent), and `admin.php?page=fforms` redirects to the new address;
- a "Form types" screen in the FForms menu; a term linked to a form has a link back to that form, and a form has a "View submissions" action leading to the list filtered by its term;
- the entry list with the form, form type, status, and a short preview; the single form-type dropdown is the only entry filter beside the status one, and an entry is titled `{form type} — {date}`, falling back to the form title when no type is assigned;
- a view of the full entry data, source, IP, and User-Agent, plus an "Additional data" block with the form type, `ref`, `user_id`, `meta`, and the legacy `customFields` of older entries;
- manual status changes on an entry;
- CSV export of all entries, of a selected form's entries, or of a selected form type's entries.

The interface is written in English and translated through the `fforms` text domain. The plugin ships its own catalogs in `languages/`: `fforms-ru_RU.po`/`.mo` for PHP and per-script `fforms-ru_RU-<md5>.json` files for the block editor, loaded with `load_plugin_textdomain()` and `wp_set_script_translations()` against the plugin directory. The `fform_entry` CPT is "Submissions" in English and «Записи» in Russian.

The CSV carries a UTF-8 BOM, merges the fields of every selected record into a shared column set, and guards values against spreadsheet formula injection. Besides the schema fields it contains the `form_type`, `ref`, `user_id`, `custom_fields`, and `meta` columns.

FForms settings also hold the allowed origins of the main form, the recipients of its notifications, and the strict form-type mode. A global setting unlocks the notification and autoreply settings in the form editor; it is off by default. The main notification is off by default too and is enabled per form. Recipients can be listed comma-separated; an empty value falls back to `admin_email`. The autoreply is enabled and configured on the form itself, then sent to the value of the configured email field. The form type and the off-schema data go into the email as a separate block after the form fields.

The built-in SMTP is optional and configures the global WordPress `PHPMailer`. Enabling it therefore affects every email on the site, not only FForms, and it must not be used alongside another SMTP plugin. The SMTP password is stored in the `fforms_smtp` WordPress option without any additional encryption by the plugin.

## 9. Extensibility

The following extension points are available:

- `fforms_max_request_bytes` — the maximum submit request size;
- `fforms_rate_limit` — the number of attempts per window;
- `fforms_rate_window` — the rate limit window length;
- `fforms_client_ip` — the computed client IP;
- `fforms_entry_created` — an action fired after the entry is stored and the emails have been attempted.

The REST contract is versioned through the `v1` namespace. CORS for the `fforms/v1` namespace is handled by the plugin itself, under an exact-match per-form allowlist; see `api-route-headless-cms-mode.md` §6, which also documents the extension points layered on top of this section.

## 10. Security and privacy

- Only published forms and their schemas are served publicly.
- Entries, SMTP settings, and export are available only to users with `manage_options`.
- Admin saves are protected by nonce and capability checks.
- Input goes through an allowlist, sanitization, and server-side validation.
- The public submit deliberately requires neither a nonce nor authentication.
- IP, User-Agent, and source are stored by default; there is no automatic retention period, anonymization, WordPress privacy exporter/eraser, or deletion of entries when a form is deleted.

## 11. Not part of the base version yet

- a visual field builder and nested Gutenberg field blocks;
- the `content` and `survey` types;
- conditional logic, multi-step, and file uploads;
- CAPTCHA/Turnstile, webhooks, and external integrations;
- analytics, per-form roles, and a retention policy;
- a server-side fallback for submitting without JavaScript;
- a dedicated automated PHPUnit/JavaScript test suite.

## 12. Current verification status

User-facing settings in E2E are configured the same way as on a real site — through wp-admin. The plugin adds no test forms, filters, or constants to the PHP/bootstrap configuration.

- All PHP files pass `php -l`.
- Both JavaScript files pass `node --check`.
- During the MVP, activation in `wp-env`, block registration, SSR markup, public form reads, a successful submit, 422 errors, the honeypot, entry protection, and the rate limit were verified.
- The local environment is described in `.wp-env.json`, the `Makefile`, and `README.md`; the wp-env testing environment is disabled until PHPUnit tests exist.
