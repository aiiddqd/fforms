# FForms — working context

## Purpose

FForms is a lightweight WordPress plugin for contact and lead forms, storing
submissions in WordPress and sending them REST-first. The plugin has to feel
right both on a regular Gutenberg site and in headless/Jamstack integrations.

The product position: a free, self-contained, predictable core; native Gutenberg
forms that inherit `theme.json`/Global Styles; submissions stored locally by
default. Complex integrations and paid capabilities are separate add-ons, not
core bloat.

## Sources of truth

Read the relevant document before changing the area it covers, and do not mix
the current state up with the plan:

1. `docs/specs/base.md` — the base specification as currently implemented and
   supported.
2. `docs/rfc/archive/gutenberg-form-builder.md` — the transition to the
   Gutenberg builder; unchecked acceptance criteria are planned work.
3. `ROADMAP.md` — the next product priority.
4. `docs/rfc/archive/mvp/rfc.md` — the history and contract of the MVP; its
   roadmap does not override the base specification.
5. `docs/rfc/archive/mvp/сf7.md` — product principles, not a technical spec.

On a conflict, never change backward compatibility silently: clarify the
decision, or update the corresponding specification/RFC together with the code.

### Documentation language

`docs/specs/*`, this file, and `LOG.md` are written **in English** — they are the
technical contract of the plugin, its working context, and its change log, read
outside the Russian-speaking team as well. The rule covers the whole file:
headings, prose, tables, and example captions.

- Identifiers are never translated or inflected: meta keys, hooks, filters,
  slugs, error codes, routes, and class names stay exactly as they are in code.
- Admin UI elements are named descriptively in English ("Form types",
  "View submissions"); the source of truth for the exact interface string is the
  code, not the specification.
- `docs/rfc/*`, `ROADMAP.md`, and `README.md` keep their current language.
- When you change code that changes behavior documented in `docs/specs/*`,
  update the specification in English as part of the same change.

### Interface language and translations

Every user-facing string in PHP and JS is written **in English** inside
`__()`/`_e()`/`_n()` and their escaping variants, with the `fforms` text domain.
Russian is a translation, never a second source: it lives in
`languages/fforms-ru_RU.po`.

- After adding, changing, or removing an interface string run `make i18n`
  (`tools/i18n.py`). It rewrites `languages/fforms.pot`, merges new msgids into
  `fforms-ru_RU.po` keeping existing translations, compiles `fforms-ru_RU.mo`,
  and regenerates the per-script `fforms-ru_RU-<md5>.json` catalogs that
  `wp.i18n` reads in the block editor.
- The JSON catalogs are keyed by the md5 of the script path, so editing a
  string in `src/` means running `npm run build` **before** `make i18n` — the
  catalogs are built from the enqueued files in `build/` and `assets/`.
- `make i18n` prints the untranslated msgids; the only ones that may stay empty
  are product and protocol terms identical in both languages (FForms, Email,
  SMTP, Endpoint, Meta, Ref, User ID, Headless API, Iframe, Js-script).
- Entity naming: the `fform_entry` CPT is "Submissions"/"Submission" in English
  and «Записи»/«Запись» in Russian. Keep both terminologies consistent across
  labels, notifications, and the export page.
- e2e specs in `specs/` run against an English site: match interface strings
  in English there.

## Domain model and public contract

- `fform` — the non-public form CPT; `fform_entry` — the private submission CPT.
- REST namespace: `fforms/v1`; text domain and PHP namespace: `fforms` and
  `FForms\` respectively.
- Public submit: `POST /wp-json/fforms/v1/submit`, with a payload of `form_id`,
  `fields`, the `website` honeypot, and `source`.
- Only published forms and schemas are publicly available. Entries, export, and
  status management require `manage_options`.
- Validation is always server-side: an allowlist of schema fields,
  normalization, required checks, and types. Never trust browser attributes or
  browser-side validation.
- The anti-spam contract must not be weakened: the default limit is 5 attempts
  per 60 seconds for a form+IP pair; a filled honeypot returns a fake successful
  response and neither stores an entry nor sends email.
- Data, IP, User-Agent, and source are stored by default. Any change to PII
  storage, retention, or export requires a separate product and privacy
  decision.

## Gutenberg architecture

- A form is edited as a single `fforms/form` with child field blocks and
  `fforms/submit`. Supported fields: text, textarea, email, tel, url, number,
  select, radio, checkbox, hidden.
- For a block-based form, `post_content` is the canonical source. `_fforms_schema`
  remains a derived cache / legacy data; reach the schema through
  `FForms\Schema\Schema_Repository` rather than reading the meta directly.
- A regular page holds a reference to a published form through `ref`; `formId`
  remains a legacy alias. One form must update every insertion centrally.
- Legacy forms with an empty `post_content` and a `_fforms_schema` must keep
  working. The JSON → blocks migration does not delete the original meta and
  does not run in bulk on update.
- Blocks are `apiVersion: 3` and run in the iframed editor. Do not touch the
  parent window's DOM. Describe block metadata, assets, attributes, and supports
  in `block.json`; do not duplicate them by hand in PHP.
- Rendering stays dynamic and semantic: associated label/control pairs,
  `fieldset`/`legend` where appropriate, `aria-live`, and unique DOM IDs for two
  insertions of the same form. Frontend assets load only when a form renders.
- Visual configuration uses Block Supports, `theme.json`, and CSS variables; do
  not introduce a separate design system or hard theme-specific styles.
- The submit UI must tie server field errors to their controls and move focus to
  the first error. Form changes require manual checks of keyboard navigation,
  label association, and the error/loading/success states.

## Code layout

```text
fforms.php                         bootstrap and constants
includes/<Class_Name>.php          CPTs, REST, settings, mail, export
includes/Schema/                   schema compiler and the single repository
includes/Blocks/                   PHP rendering and block registration
includes/Migration/                compatible legacy JSON migration
src/blocks/<block>/                block.json, editor, render, and styles
assets/                            legacy fallback/admin scripts
build/                             generated wp-scripts output
docs/                              specifications, RFCs, and roadmap
```

One class per file, and the file name matches the class name exactly
(`Notifications` -> `includes/Notifications.php`, `REST_Controller` ->
`includes/REST_Controller.php`, `Schema_Compiler` ->
`includes/Schema/Schema_Compiler.php`). The `class-*.php` prefix is not used.

Keep the separation: PHP templates and renderers stay thin, domain logic lives in
`includes/Schema` and the services, and React code never becomes the source of
server-side validation. Do not edit `build/` by hand: change `src/`, then build.

## Compatibility and security

- Supported minimum: WordPress 6.5, PHP 8.0; current WordPress 7.1 is checked as
  well. Node is pinned to 22 (`.node-version`).
- On WP 6.8+ blocks are registered through the metadata collection; on 6.5–6.7 a
  verified fallback is mandatory. Do not make manual block/asset-handle lists the
  primary path.
- Admin saves, CSV, and entry changes must have nonce and capability checks. The
  public submit deliberately has neither nonce nor auth.
- The built-in SMTP changes WordPress's global PHPMailer; do not grow it into an
  isolated FForms mailer, and warn about conflicts with SMTP plugins.

## Development and verification

The project uses npm with a lockfile and `@wordpress/scripts`; for a clean
install run `npm ci` rather than switching package manager. Main commands:

```bash
npm run build
npm run lint:js
npm run lint:css
npm run test:unit
make install
make start       # http://localhost:8890, admin/password
make status
make logs
```

`wp-env` mounts this plugin, so PHP/JS changes are visible immediately; blocks
need a build after `src/` changes. Before finishing a change, run at least the
relevant lint/build checks. For the server-side submit, verify at minimum 422 for
invalid data, 201 for valid data, the honeypot, the rate limit, and that
unauthorized access to entries is denied.

## LOG.md

Add an entry to `LOG.md` after every change to the plugin.

- New entries go on top.
- Each day starts with a `## YYYY-MM-DD` heading.
- Each change is a list item with a short description.
- This covers any edit — code, documentation, build configuration, and so on.
  Record it as part of the change itself, not as a separate step afterwards.
- The goal is a change log grouped by day.

## Near-term priorities

- The public form URL/page already exists; next come iframe embedding and
  convenient navigation from a form to its entries.
- Email notifications should become off by default, with explicit opt-in and a
  recipient list.
- Privacy: a setting to disable storage, retention, IP/UA minimization, deletion
  of a form's data, and WordPress privacy tools.
- Ecosystem: stable hooks/filters and a documented versioned REST/schema contract
  first, then a separate `fforms-addon` for CRM, webhooks, anti-spam, and
  commercial features.

Do not add conditional logic, multi-step, uploads, content/survey mapping,
analytics, or arbitrary HTML without a dedicated RFC once the builder has
stabilized.
