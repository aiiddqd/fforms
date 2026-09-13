---
status: current
updated: 2026-09-13
---

# FForms: the headless entry point and code forms

Supplements [`base.md`](base.md): describes registering forms programmatically in code and the related REST contract changes introduced by the `use-fforms-as-headlesscms-backend` RFC. The base flow with CPT forms (`base.md`) is unchanged — code forms are a second, fully equal way to address a form.

Headless is a property of the entry point, not of a form. The plugin has exactly two modes: the schema-free route `POST /fforms/v1/main` (`base.md` §5.2), and the builder, whose published forms go out through the block, the shortcode, the share link, and the two embed snippets. A form has no mode setting, and there is no schema-container block — `_fforms_mode` and `fforms/headless-schema` were removed by the `two-modes-headless-and-builder` RFC.

## 1. Purpose

A headless frontend (AstroJS, for example) cannot rely on a post ID as a stable form identifier across environments. Code forms solve that: a form is described in the PHP of a theme or plugin (title, key, fields) with no database record, and becomes reachable through `fforms/v1` by a string key.

Two more paths arrived with the main form (`base.md` §5.2): the built-in form with the key `main`, which works right after activation and needs no registration at all, and the slug of an `fform_type` term, which gives a CPT form a stable string key in place of its post ID.

## 2. Registration

```php
add_action( 'fforms_register_forms', function () {
	fforms_add_api_route( 'contact_astro', array(
		'title'           => 'Contact (Astro)',
		'fields'          => array(
			array( 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ),
			array( 'name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true ),
		),
		'type'            => 'contact_astro',
		'origins'         => array( 'https://example.com' ),
		'success_message' => 'Thanks!',
		'notifications'   => array( 'enabled' => true, 'to' => 'sales@example.com', 'subject' => '' ),
		'autoreply'       => array( 'enabled' => false, 'email_field' => 'email', 'subject' => '', 'message' => '' ),
	) );
} );
```

- The `fforms_register_forms` action fires on `init` at priority 5 — before `rest_api_init`.
- `title` and a non-empty valid `fields` are required; `fields` goes through the same `Schema::normalize()` as CPT forms (limits: 50 fields, 100 options, `max_length`). Unlike the CPT editor, an empty or entirely invalid schema is a `WP_Error` rather than a fallback to `Schema::defaults()`.
- Key: `sanitize_key`, pattern `^[a-z0-9_]{1,32}$`. Registering a key twice is a `WP_Error`; the first registration wins. The registry (`FForms\Registry\Code_Forms`) lives only for the duration of the request — the code stays the source of truth.
- `fforms_add_api_route()` returns `true` or a `WP_Error`; a failed registration does not interrupt site loading.
- The `main` key is reserved for the built-in main form — registering it returns a `WP_Error` with code `fforms_reserved_key`.
- The optional `type` is the form type slug (`^[a-z0-9_-]{1,32}$`) that entries of this form receive. An invalid value is a `WP_Error` with code `fforms_invalid_form_type`. Without `type`, entries of a code form submitted through `/submit` stay without a term.

## 3. Unified form addressing

`FForms\Form_Ref` is a value object (`post_id`, `key`, `title`, `schema`, `success_message`, `origins`, `notifications`, `source` = `post`|`code`|`builtin`, `type`). `FForms\Form_Locator::resolve( int|string $ref ): Form_Ref|WP_Error` parses the reference in a fixed order:

1. a number — the post ID of a published CPT form;
2. the `main` key — the built-in main form (`post_id = 0`, `source = builtin`);
3. a registered code-form key;
4. the slug of an `fform_type` term with a non-empty `_fforms_form_id` meta — that very CPT form;
5. otherwise — a `WP_Error` with code `fforms_form_not_found`.

Submit, the read routes, `Notifications`, and the admin columns all work through `Form_Ref` only. Falling through to step 5 is handled differently per route: `/submit` answers 404, while `POST /main` treats the value as a form type and accepts the submission into the built-in main form.

CPT forms always resolve with `origins = []` — per-form CORS is configured for code forms and, through a separate setting, for the `/main` route (see §6).

## 4. REST contract (updated)

| Method and route | Access | Purpose |
| --- | --- | --- |
| `POST /submit` | public | `form_id` OR `form_key` — exactly one is required. |
| `POST /main` | public | Schema-free flat payload; see `base.md` §5.2. |
| `GET /forms` | public | The main form, CPT forms, and code forms together; each carries `key` (`null` for CPT forms), `source` (`post`\|`code`\|`builtin`), `share_link`, and `form_type`. |
| `GET /forms/{id}` | public | As before, for a CPT form. |
| `GET /forms/{id}/schema` | public | As before. |
| `GET /forms/{key}` | public | A code form by key, `main`, or the slug of a CPT form's term; registered after the numeric route, pattern `[a-z0-9_-]+`. |
| `GET /forms/{key}/schema` | public | The schema of the form addressed by that same key. `main` answers with an empty field list: the route has no schema. |
| `POST /forms/{id}/share-token` | `edit_post` | Issues a new share token for a CPT form. |
| `GET /entries` | `manage_options` | `form_id`, `form_key`, `form_type`, and `status` filters (combinable). |
| `POST /entries/{id}/status` | `manage_options` | Unchanged. |

A submit with neither `form_id` nor `form_key` returns 400 `fforms_form_ref_required`. A nonexistent `form_id`/`form_key` returns 404 `fforms_form_not_found`. Beyond that, submit is unchanged: honeypot, rate limit, validation, entry, emails, and `fforms_entry_created` are the same steps as in `base.md` §6 — they just operate on a `Form_Ref` instead of a bare `form_id`. The rate limit is counted per form reference (`post:{id}` or `code:{key}`) plus IP, so a code form whose key is a digit string does not share a transient with the CPT form of the same ID.

`fforms_entry_created` now receives a `Form_Ref` as its second argument instead of `int $form_id`.

**Breaking changes from the `two-modes-headless-and-builder` RFC.** `GET /forms` and `GET /forms/{id|key}` return `share_link` (bool) where they used to return `mode` (string). `POST /main` no longer resolves its value to a form: `formId: 123` classifies the submission as type `123` instead of directing it at form 123 — that resolution lives on `POST /submit`. `/main` also stopped validating against a schema and stopped writing `_fforms_custom`; unknown keys are now ordinary fields in `_fforms_data`.

## 5. Entries, emails, admin

- Code-form entry: `_fforms_form_id = 0`, `_fforms_form_key = <key>`. Main-form entry: `_fforms_form_id = 0`, `_fforms_form_key = main`. A CPT-form entry is unchanged (`_fforms_form_key` is an empty string).
- An entry's classification is stored as an `fform_type` term (`base.md` §3.1), separately from addressing: a submission can land in the main form and still carry the business type "consultation request".
- The entry list, the "Form" column, the CSV export, and the export select all resolve the title through `Form_Locator` rather than `get_the_title()`. If an entry's key is no longer registered in code, the column and the CSV show the key itself — no data is lost.
- The CSV gains the `form_key`, `form_type`, `ref`, `user_id`, `custom_fields` (legacy entries only), and `meta` columns; the export parameters are `form_ref` (`post:{id}` or `code:{key}`; the old `form_id` is still accepted for backward compatibility) and `form_type`.
- `Notifications::send( Form_Ref $form, int $entry_id, array $data, array $extras = array() )` takes the mail settings (`notifications`, `autoreply`) from the `Form_Ref` instead of `get_post_meta()`. The global `Settings::get()['notifications']` toggle remains the shared safety switch for both form sources.

## 6. CORS

The plugin sends CORS headers only for the `fforms/v1` namespace; every other REST route on the site keeps WordPress core's default behavior unchanged.

- `Access-Control-Allow-Origin` appears only when the request's `Origin` header matches exactly (scheme + host + port) one of the form's `origins`. The match is checked against the specific form for a real request (submit by `form_id`/`form_key`, reads by `{id}`/`{key}`); for a preflight `OPTIONS` — which has no body carrying `form_id`/`form_key` yet — an origin allowed by *any* registered code form, or by the main form setting, is accepted.
- `Access-Control-Allow-Credentials` is never sent, and a wildcard origin is not supported.
- A preflight `OPTIONS` on `/submit` returns 204 with `Access-Control-Allow-Methods: GET, POST, OPTIONS`, `Access-Control-Allow-Headers: Content-Type`, and `Access-Control-Max-Age: 600`.
- The resulting origin list is extended by the `fforms_allowed_origins( array $origins, ?Form_Ref $form )` filter.
- On the `/main` route the "Allowed origins of the main form" list from the plugin settings applies as well. It is named after the route, so it applies regardless of which form the payload addressed. The list is empty by default — zero-config covers creating a form, not CORS.
- A form (CPT or code) with no configured `origins` behaves as before: no headers are sent, and CORS stays the site's own concern.

## 7. Extensibility (supplements base.md §9)

- `fforms_add_api_route( string $key, array $args ): true|WP_Error` — the public registration function.
- `fforms_register_forms` — the action for registering code forms, `init` @5.
- `fforms_allowed_origins` — filter, extends the CORS origin allowlist.
- `fforms_rate_limit` / `fforms_rate_window` — the second parameter is now a string rate reference (`post:{id}`/`code:{key}`, and `code:main` for the main form) instead of `int $form_id`.
- `fforms_max_form_types` — filter, the autocreation limit for `fform_type` terms (50 by default).

## 8. Out of scope (unchanged from the RFC)

Editing code forms and the main form in the admin, rendering a code form through the `fforms/form` block, unregistering forms programmatically, per-form tokens and request signatures, a `v2` REST API, and accepting attachments (see `docs/rfc/main-form-attachments.md`).
