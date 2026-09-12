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

1. The user creates a post under **FForms → Add form** — `block` mode by default.
2. The sidebar can switch the mode: `block` edits `fforms/form`, `headless` edits a locked `fforms/headless-schema` holding the same field blocks. Switching converts the field set between the two root blocks; the JSON schema stays a derived cache and the REST format.
3. With global mail settings enabled, the user turns on the notification for that specific form and configures recipients, the success message, and optionally an autoreply.
4. The user publishes the form and either places the dynamic `fforms/form` block on a page or calls the REST API directly.
5. A public submission is validated server-side and stored as a private entry with status `new`.
6. An administrator reviews the entry, changes its status, and exports the data to CSV when needed.

The `contact` and `lead` types currently differ only by the stored type value; submission handling is identical for both.

## 3. Data model

The plugin uses two custom post types and one taxonomy.

| Entity | Storage | Notes |
| --- | --- | --- |
| Form | `fform` | Non-public CPT with an admin UI and REST support. Holds the title, type, JSON schema, and mail settings. |
| Entry | `fform_entry` | Private post with no public WordPress REST exposure. Creating one from the admin is forbidden; viewing and managing require `manage_options`. |
| Form type | `fform_type` | Non-public flat taxonomy for `fform_entry` and `fform`: the business meaning of a submission ("Consultation request"), independent of which form received it. See §3.1. |

Form meta:

- `_fforms_type` — `contact` or `lead`;
- `_fforms_mode` — `block`, `public`, or `headless`. A missing value on a previously created form reads as `block`, and an `fforms/headless-schema` block in the content forces `headless` regardless of the meta value. `public` is technically identical to `block` everywhere except `Public_Form`: same block markup, same rendering, same availability in the `ref` picker — the only difference is that `Public_Form::is_enabled()` treats the form as reachable through its public link;
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
- `_fforms_custom` — JSON of off-schema fields (`customFields` on the `POST /main` route): at most 20 keys, scalars only, up to 2,000 characters per value;
- `_fforms_meta` — JSON of arbitrary request context: depth at most 3, at most 8 KiB once encoded;
- `_fforms_ref` — referral marker (`utm_source`, `ref`), up to 200 characters;
- `_fforms_user_id` — client-supplied user identifier. It grants nothing, and the admin renders it as a link only when such a user exists;
- `_fforms_form_type_raw` — the incoming form type value, kept when no term was created because the limit was reached;
- `_fforms_created_post_id` is registered for future content forms but is currently unused.

### 3.1. The `fform_type` taxonomy

`public => false`, `publicly_queryable => false`, `show_ui => true`, `show_in_rest => false`, flat, `show_admin_column => true`; every capability maps to `manage_options`. The "Form types" screen is added to the FForms menu once, right after "Submissions". The existing `_fforms_type` form meta (`contact`/`lead`) is unrelated to this taxonomy.

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
| `POST /main` | public | Lenient flat payload of the built-in main form. See §5.2. |
| `GET /forms` | public | The built-in main form plus up to 100 published forms in alphabetical order; every CPT form carries `mode`. |
| `GET /forms/{id\|key}` | public | The form, its `mode`, schema, success message, and submit URL. `key` is a code-form key, `main`, or the slug of the form's term. |
| `GET /forms/{id\|key}/schema` | public | The normalized schema only. |
| `GET /entries` | `manage_options` | Entries with pagination and `form_id`/`form_key`/`form_type`/`status` filters. |
| `POST /entries/{id}/status` | `manage_options` | Changes the workflow status of an entry. |

### 5.1. Public form page

A form in `public` mode is additionally reachable without authentication at `/forms/{id}/` (`Public_Form`, rewrite rule `^forms/([0-9]+)/?$`). The page renders the same block markup as a regular `fforms/form` block on the site, inside the current theme's markup (`get_header()`/`get_footer()`). Access is checked by `status === 'publish'` and `Post_Types::form_mode( $id ) === 'public'`; a missing form, a different status, or a different mode returns a 404 page. For forms in `block` and `headless` mode this URL always returns 404.

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

The main form exists only in memory (`Registry\Main_Form`): `post_id = 0`, `key = main`, `source = builtin`, and a schema of four optional fields (`name` text, `email` email, `phone` tel, `message` textarea). It creates no database records, is not editable in the admin, and cannot be embedded by the block. The `main` key is reserved — `fforms_add_api_route( 'main', … )` returns a `WP_Error` with code `fforms_reserved_key`. It works right after activation, with no form to create first.

`POST /main` accepts a flat camelCase payload; every parameter is optional:

| Parameter | Purpose |
| --- | --- |
| `formType` (aliases `formId`, `form_type`, `form_id`) | Both the form address and its type. Aliases carrying different values return 400 `fforms_form_ref_conflict`. |
| schema fields of the addressed form | Go through the regular `Schema::validate_submission()`. |
| `customFields`, `meta`, `ref`, `userId` | Off-schema data; see the meta keys in §3. |
| `_hp` | Honeypot for this route: when filled, returns 200 with no entry. On `/submit` the honeypot is still `website`. |
| `source` | Same as on `/submit`; falls back to `Referer` when absent. |
| `attachments` | Not accepted: 400 `fforms_attachments_require_multipart` in JSON, and 400 `fforms_attachments_disabled` for `multipart` with files. |

Unknown top-level keys are not discarded — they are stored as `customFields`. That is why the honeypot here is named `_hp` rather than `website`: in a flat payload `website` is a perfectly ordinary user field.

**Addressing and classification are independent.** The value is resolved to a form in this order: a number → post ID, a code-form key, the slug of an `fform_type` term with a non-empty `_fforms_form_id`. If no form matches, the submission goes to the built-in main form. The type is then assigned as a term: a CPT form contributes its own term, a code form its `type` argument or the key itself, and the main form the normalized incoming value. A useful side effect is that the term slug becomes a stable string key for a CPT form, replacing the post ID that differs between environments.

The main form also enforces a rule against empty submissions: at least one of `email`, `phone`, `message` must be non-empty, otherwise 422 `fforms_empty_submission`. The rate limit is counted under the key `code:main`.

A successful response is 201 with `entry_id`, the message, and — when a type was determined — `form_type`.

## 6. Submission handling and anti-spam

Processing order:

1. Request body size check — at most 256 KiB by default.
2. Check that a published form exists.
3. Resolve the form and, for `POST /main`, the form type.
4. Honeypot (`website` on `/submit`, `_hp` on `/main`): a filled honeypot gets a fake successful HTTP 200 response, but no entry, term, or email is created.
5. Rate limit — 5 attempts per 60 seconds per form + IP pair by default.
6. Data normalization and server-side validation.
7. Creation of a private `fform_entry` and storage of source, IP, and User-Agent; off-schema data is written to separate meta keys and never enters `_fforms_data`.
8. Assignment of the `fform_type` term.
9. Sending notifications and firing the `fforms_entry_created` action.

The rate limit counts invalid attempts too, but not honeypot hits. The IP comes from `REMOTE_ADDR`; proxy headers are not trusted automatically.

## 7. Gutenberg block and frontend

For a published form in `block` mode, the dynamic `fforms/form` block lets an editor:

- pick one of the published forms;
- show or hide its title;
- change the submit button label;
- use `wide` and `full` alignment.

Markup is generated server-side from the current schema. Fields have associated `label` elements, required fields carry `required`/`aria-required`, and the result message uses `role="status"` and `aria-live="polite"`. The client script collects the data, posts JSON through `fetch`, disables the button while the request is in flight, shows the result, and clears the form after success.

Headless forms remain fully available through `fforms/v1` but are not offered in the block picker, and server-side rendering rejects a manually set `ref` pointing at one. CSS and frontend JavaScript are enqueued only when the block actually rendered a published form. Styles are minimal and theme-agnostic, but there is no dedicated `theme.json`/Global Styles integration yet. Without JavaScript the form shows a warning and does not submit.

### 7.1. The `[fform id=123]` shortcode

The second insertion point is the `[fform id=123]` shortcode. Its only attribute is `id`, the post ID of the `fform` CPT; `[fform id=123]` and `[fform id="123"]` are equivalent. The shortcode does not duplicate markup — it calls the same server-side `Form_Renderer::render_form()` as the public form page, so markup, assets, and submit behavior match the `fforms/form` block with `ref=123`, down to the block's outer wrapper.

The unavailable-form policy is shared with the block: a missing or nonexistent `id`, a post of another type, a draft, and `headless` mode render nothing — a visitor sees nothing, a user with `edit_posts` sees a text message. Code forms (`post_id=0`) are not reachable through the shortcode. `[fform id=X]` inside the content of form X itself hits the shared recursion guard and returns a circular-reference message.

Assets are enqueued along two paths: on `wp_enqueue_scripts` via `has_shortcode()` against the current post content (so styles land in `wp_head`), and again, idempotently, inside the callback itself — for renders coming from a widget or a theme template. On a page without a form no assets are enqueued.

The sidebar of a published form in `block`/`public` mode shows the ready-to-copy `[fform id=<ID>]` value; for a `headless` form the field is not rendered.

Both the shortcode and the block insert the form into `post_content`. Pages rendered by theme code that bypasses `the_content()` are served by neither — such a template must call `do_shortcode()` or `Form_Renderer::render_form()` itself.

### 7.2. External embedding: iframe and js-script

Two snippets sit on top of the public form page `/forms/{id}/` for embedding on a third-party site, so both are available only for a published form in `public` mode ("Share via URL"):

- `<iframe src="{home}/forms/{id}?fforms_embed=1" style="width:100%;border:0" height="600" loading="lazy">` — a simple embed with a fixed height;
- `<script src="{plugin}/assets/embed.js" data-fforms-form="{id}" data-fforms-origin="{home}" data-fforms-src="{embed_url}"></script>` — the script replaces the tag with an iframe and keeps its height in sync.

The public form page, when opened inside a frame, sends the parent a `postMessage` of `{ type: 'fforms:height', formId, height }`. `assets/embed.js` applies the height only for messages coming from its own iframe's `contentWindow` with a matching `formId`, and sets it both as an attribute and as an inline style so a theme rule for `iframe` cannot override it.

The bare document force-resets `height`, `min-height`, and `overflow` on `html`/`body`: themes usually give them `height:100%` and their own `overflow`, which inside a frame turns body into a separate scroll container — the document then reports the frame's height instead of its own, so the form cannot be measured and gets clipped.

Height is measured from the content (`.fforms-embed__content`) rather than from `documentElement`, whose height is tied to the frame viewport; the maximum with `scrollHeight` is taken as well, to survive a theme that made body a scroll container anyway. An unchanged height is re-sent until the frame matches it: otherwise a frame that once ended up shorter than its content would stay clipped forever, because there would be nothing to send — the content height never changed. The page sends nothing outward besides the height and has no return channel, so `targetOrigin` is `'*'` (the embedding page's domain is not known in advance). Outside a frame the height reporter does not activate.

`assets/embed.js` runs on other people's sites: no dependencies, no build step, no global variables, and it survives several embeds of different forms on one page. The exact frame URL arrives in `data-fforms-src`; without that attribute the script assembles it from `data-fforms-origin` and `data-fforms-form`.

The `fforms_embed=1` parameter switches the public page into "bare" mode: a minimal HTML document with no header, footer, or admin bar, but still with `wp_head()`/`wp_footer()`, so block styles, Global Styles, and the Interactivity runtime work as usual. Without the parameter, `/forms/{id}` stays a full theme page — it remains the link to share. Padding in bare mode is zero: spacing is the embedding page's job.

All insertion points are collected in the form editor sidebar: the shortcode (`block`/`public`), the public link, the iframe and the js-script (`public` only); for `headless` nothing is shown.

## 8. Admin, mail, and export

The admin implements:

- creating and editing forms through the standard CPT interface;
- a `Block editor` or `Headless API` mode in the sidebar. A new form starts as `Block editor`, and switching the mode converts the current fields between blocks and the JSON schema. In `Headless API` the container accepts FForms field blocks only, so the REST schema cannot drift from the editor content;
- an overview page at `admin.php?page=fforms-dashboard` that opens with the first FAQ question, "How do I start accepting messages over the REST API?": the real `POST /fforms/v1/main` URL, the current settings state, the list of form types, and four ready-made request examples with a "Copy" button. The top-level menu slug stays `fforms` (both CPTs and the settings and export pages use it as their parent), and `admin.php?page=fforms` redirects to the new address;
- a "Form types" screen in the FForms menu; a term linked to a form has a link back to that form, and a form has a "View submissions" action leading to the list filtered by its term;
- the entry list with the form, form type, status, and a short preview; the single form-type dropdown is the only entry filter beside the status one, and an entry is titled `{form type} — {date}`, falling back to the form title when no type is assigned;
- a view of the full entry data, source, IP, and User-Agent, plus an "Additional data" block with the form type, `ref`, `user_id`, `customFields`, and `meta`;
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
