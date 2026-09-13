# LOG

Plugin change log in reverse chronological order (newest on top).
Each entry is a single line: the date and a short description of the change.

Entry format:

```
## YYYY-MM-DD
- short description of the change
- ...
```

## 2026-09-13
- `Schema_Compiler::find_form_block()` only looked at the top level of the parsed tree, so a `fforms/form` block wrapped in a Group (or Columns, or any other container) was invisible to it. `has_form_block()` then returned `false` for such a form, and every gate built on it fell through to the legacy branch: `cache_compiled_schema()` skipped the save hook, `prevent_invalid_publish()` skipped validation, and `Schema_Repository::for_form()` returned the stale `_fforms_schema` meta instead of compiling the blocks. The visible symptom was a form whose editor showed renamed labels while every page rendering it kept the old ones — `Form_Renderer::render_reference()` was rendering `render_legacy()` from that meta, not `do_blocks()`. The lookup recurses into `innerBlocks` now; `compile()` and `walk()` needed no change, as they already walked the whole subtree.
- Default form styles: the builder's inner blocks container now carries `fforms-fields` (`edit-builder.js`), so the editor grid and gaps are the ones `render_shell()` produces; the `.fforms-field-preview` wrapper is gone from `field.js`, leaving `.fforms-label` and `.fforms-control` direct children of `.fforms-field` exactly as `field_markup()` renders them.
- Three spacing steps instead of one: `--fforms-field-gap` 0.5rem → 0.375rem (label ↔ control), `--fforms-form-gap` 1rem → 1.25rem (field ↔ field), and a new `--fforms-submit-gap` (0.5rem) detaching the submit button and a non-empty `.fforms-response` from the field stack. An empty `.fforms-field-error` is hidden, so it no longer reserves a row under every control (repeated in `editor.scss`, where `view.css` is not enqueued).
- Control defaults: border `color-mix(in srgb, currentcolor 35%, transparent)` with a plain `currentColor` declaration in front as the fallback, `border-radius` 0 → 4px, padding 0.65em 0.8em, textarea min-height 8rem → 6.5rem, `currentColor` border on `:hover`/`:focus`; labels get `font-weight: 500`/`line-height: 1.3` and a `legend` with no inline padding. Everything stays in `:where()`, so themes still win without `!important`.
- `assets/fallback-view.css` repeats the same values for a checkout without `build/`, and `docs/specs/theming.md` now lists the actual defaults plus `--fforms-submit-gap`.
- Measured on wp-env (WP 7.1): label↔control 6 < field↔field 20 < field↔button 28 in both the editor and the front end, identical for a legacy `_fforms_schema` form via shortcode; `:root { --fforms-form-gap: 3rem }` moves it to 48 without `!important`; `theme.json` `styles.blocks.fforms/form` (background, padding) still wins; TT1 (classic) keeps its own control and button styles. `specs/new-form-editor.spec.js` asserts the three steps and the tree in the editor, and the new `specs/default-form-styles.spec.js` does the same on a published page; both save a screenshot to `artifacts/`, which is now git-ignored.
- Removed the "Form type" select (`contact`/`lead`) from the form editor sidebar: form types are the `fform_type` taxonomy now, and a form is itself a type through the term created on publish. The `_fforms_type` meta is no longer registered, written by the activation seeds, or exposed — the `type` key is gone from the `GET /forms` and `GET /forms/{id|key}` responses, where `form_type` (the term slug) has carried the meaningful value all along.
- `Migration\Type_Meta_Migration` drops the leftover `_fforms_type` rows with a single `delete_post_meta_by_key()` on the next admin request, guarded by the `fforms_type_meta_migration_version` option like the other one-time migrations. The key only ever held `contact` or `lead` and belongs to this plugin alone, so nothing is preserved and no post type is narrowed to.
- Added an "Overview" panel at the top of the form editor sidebar: a "View submissions" link and the number of stored submissions. `Post_Types::entries_url_for_form()` now backs both it and the "View submissions" row action — the form's own type term, falling back to the `form_ref` filter while the form has no term yet.
- Verified the `form-block-preview-in-editor` RFC in a running editor and closed its checklist; the RFC is archived as `implemented`. `specs/form-block-preview.spec.js` grew two scenarios — the picker in the block sidebar repoints the preview (told apart by the `formId` in `data-wp-context`), and a reference to a form that no longer exists shows "Select a published form in the block settings." while keeping the picker — and its first scenario now also asserts that "Edit form" is absent at `ref = 0` and carries `target="_blank"` once a form is picked.
- Two assertions in that spec were wrong rather than the code: the block was addressed by `.wp-block-fforms-form`, which matches twice once the preview renders (the editor's wrapper and the rendered form's own), so it is `[data-type="fforms/form"]` now; and the honeypot was checked with `not.toBeVisible()`, which never holds for an input inside a clipped container — the container is measured instead (`position: absolute`, box ≤ 1 px), which is what the repeated `view.css` rules in `editor.scss` actually do.
- Checked block supports by hand alongside the spec: a reference block with `padding: 32px` and a `3px` border produces exactly one box carrying them in the canvas and one on the front end, and the two screenshots match — no doubling.
- `specs/admin-menu-links.spec.js` looked for "Settings" across the whole `#adminmenu`, which also matches WordPress's own Settings menu; scoped to `#toplevel_page_fforms`. `specs/form-publication.spec.js` clicked the "Share via link" panel header unconditionally, so it closed the panel whenever the editor had remembered it open from an earlier run — it now expands only when `aria-expanded` is not already `true`. Both were failing or flaky before. Full suite: 7 of 7, twice in a row.
- The "Form settings" panel of a reference block gained an "Edit form" link to `post.php?post=<ref>&action=edit`, shown only once a form is picked and opened in a new tab so the unsaved page survives. The preview still does not follow edits made there until the page editor is reloaded.
- Removed the `showTitle` and `submitLabel` attributes of `fforms/form` and their two controls ("Show the title", "Button text (legacy)"). Neither did anything: the submit label is read from the `label` attribute of `fforms/submit`, and `showTitle` was only read in `render_shell()`, which a reference block never reaches. Gone from `block.json`, `Block::form_attributes()`, `assets/fallback-editor.js`, the SSR whitelist (now just `{ ref }`) and the `$title` branch of `render_shell()`. Content that still carries the keys renders byte-for-byte as before — the block is dynamic, `save` is empty, and the parser ignores unknown attribute keys.
- The `fforms/form` block on a page now previews the form it references instead of a placeholder: `edit.js` is a switcher between the new `edit-builder.js` (inner blocks inside the `fform` CPT) and `edit-reference.js` (picker plus preview on a page), and `ref > 0` renders through `ServerSideRender` against `/wp/v2/block-renderer/fforms/form`.
- Only `ref`, `showTitle` and `submitLabel` reach that route: passing block supports would apply the padding, background and border a second time on top of the editor wrapper. An empty or failed response falls back to the "FForms" placeholder with the picker.
- The reference branch no longer calls `useInnerBlocksProps`, so a page stores just `<!-- wp:fforms/form {"ref":123} /-->` and the default template stops leaking field blocks into `post_content`.
- `editor.scss`: `pointer-events: none` on `.fforms-block-preview` (the preview is not interactive — a click selects the block) and a copy of the honeypot rules, which live in `view.css` and are not enqueued in the editor.
- Added `@wordpress/server-side-render` (`wp-6.5`); the build extracts it to the core `wp-server-side-render` handle. New e2e spec `specs/form-block-preview.spec.js`; `docs/specs/base.md` §7 documents the two editor shapes.
- Implemented the `two-modes-headless-and-builder` RFC. `POST /main` dropped its schema: every non-reserved top-level key is stored in `_fforms_data` as sent (≤ 50 fields, ≤ 2,000 characters, non-scalars as JSON), `formType` only classifies and no longer addresses a CPT or code form, a submission without one gets the type `main`, and an entirely empty payload is 422 `fforms_empty_submission`. `_fforms_custom` is no longer written and stays readable for older entries.
- The form mode is gone: `_fforms_mode`, `Post_Types::form_mode()`/`sanitize_form_mode()`, the headless editor branch and every render/shortcode/`ref`-picker check of it were removed, as were the `fforms/headless-schema` block and its compiler support. Any published form now renders through the block and the shortcode.
- The share link became a toggle plus a secret token: `_fforms_share_link` and `_fforms_share_token` (16 hex from `random_bytes()`, issued on first publish), the rewrite is `^forms/([a-f0-9]{16})/?$`, `/forms/{id}/` is gone, the page answers `noindex, nofollow` in both `wp_robots` and `X-Robots-Tag`, and `POST /fforms/v1/forms/{id}/share-token` reissues the link.
- `Mode_Migration` revision 2 converts existing forms: `public` → share link plus a token, `headless` → the same fields in an `fforms/form` block with a submit button, and `_fforms_mode`/`_fforms_public` are deleted everywhere.
- The form sidebar replaced the "Form mode" select with a "Publication" panel: shortcode, the "Share via link" toggle, and under it the URL, the iframe and js-script snippets and a "Reissue link" button.
- REST reads return `share_link` (bool) instead of `mode` (string) — a breaking change for `GET /forms` and `GET /forms/{id|key}`; the dashboard's questions and answers now describe the two modes.
- `docs/specs/base.md`, `docs/specs/api-route-headless-cms-mode.md` and `README.md` rewritten around the two entry points; `specs/form-creation-mode.spec.js` replaced by `specs/form-publication.spec.js`.
- Fixed a bug that predates the RFC and surfaced while checking it: `update_metadata()` unslashes what it stores, so the backslashes JSON uses to escape a quote were stripped and any submitted value containing `"` left `_fforms_data` unparseable. JSON meta (`_fforms_data`, `_fforms_meta`, `_fforms_schema`) is now written through `wp_slash()`. Entries saved before this keep the corrupted value; nothing migrates them.

## 2026-09-12
- docs: RFC `two-modes-headless-and-builder` — the plugin collapses to two modes (schema-free headless route `/main`, builder form published via block, shortcode, iframe, js snippet and an optional token share link); marks the three mode/embed RFCs as superseded.

- The overview page heading "FAQ" renamed to "Questions and answers" (Russian: «Вопросы и ответы»); the `fforms-faq` markup, anchors, and questions are unchanged.
- Class files renamed to match their class names: `includes/class-notifications.php` -> `includes/Notifications.php`, `includes/class-rest-controller.php` -> `includes/REST_Controller.php`, `includes/Schema/class-schema-compiler.php` -> `includes/Schema/Schema_Compiler.php`, and so on for all 23 files under `includes/`. The `class-*.php` prefix is gone; the `require_once` list in `fforms.php` and the source references in `languages/` were updated, and the rule is recorded in `AGENTS.md`. No namespaces, class names, hooks, or data changed.
- The interface switched to English as its source language: every `__()`/`_e()`/`_n()` string in PHP and JS is written in English, and Russian became a translation shipped in `languages/` — `fforms-ru_RU.po`/`.mo` for PHP plus per-script `fforms-ru_RU-<md5>.json` catalogs for the block editor. Added `Domain Path: /languages` and `wp_set_script_translations()` for the block editor scripts and the form sidebar, which `register_block_type()` otherwise points at `wp-content/languages/plugins` only.
- `fform_entry` renamed in the interface: the mixed "Ответы"/"Заявки" became "Submissions"/"Submission" in English and «Записи»/«Запись» in Russian, consistently across the CPT labels, the entry list columns, the "View submissions" row action, notification emails, CSV export, and the overview cards. Slugs, meta keys, routes, and data are untouched.
- Added `tools/i18n.py` and the `make i18n` target: it rebuilds `languages/fforms.pot`, merges new msgids into `fforms-ru_RU.po` keeping existing translations, compiles the `.mo`, and regenerates the script JSON catalogs (wp-cli's `i18n` commands are unavailable in this environment). Interface language rules recorded in `AGENTS.md`.
- The e2e specs in `specs/` now match English interface strings ("Settings", "CSV export", "Form mode").
- `LOG.md` translated to English; the "Documentation language" rule in `AGENTS.md` now covers `docs/specs/*`, `AGENTS.md`, and `LOG.md`, while `docs/rfc/*`, `ROADMAP.md`, and `README.md` keep their current language.
- `AGENTS.md` translated to English; the "Documentation language" rule extended — `docs/specs/*` and `AGENTS.md` itself in English, `docs/rfc/*`, `LOG.md`, `ROADMAP.md`, and `README.md` keep their language.
- Fixed the paths in "Sources of truth" that pointed at documents moved to the archive: `docs/rfc/archive/gutenberg-form-builder.md`, `docs/rfc/archive/mvp/rfc.md`, `docs/rfc/archive/mvp/сf7.md` — the links previously pointed into `docs/rfc/` and did not resolve.
- `docs/specs/base.md` and `docs/specs/api-route-headless-cms-mode.md` translated to English; identifiers (meta keys, hooks, filters, slugs, error codes, routes) left exactly as in the code, admin UI elements named descriptively in English.
- Added the "Documentation language" rule to `AGENTS.md`: `docs/specs/*` is written in English, the rest of the documentation (`docs/rfc/*`, `LOG.md`, `ROADMAP.md`, `README.md`) keeps its language.
- Fixed an outdated claim in `base.md` §9: the plugin does have its own CORS policy (an exact-match allowlist for `fforms/v1`), so the section now points at `api-route-headless-cms-mode.md` §6.
- The "Accepting submissions over the API" block on the overview page moved into "FAQ" as the first question, "How do I start accepting messages over the REST API?" (expanded by default); the endpoint, the settings state, the type list, and the four copyable examples now live in the answer.
- The `fform_type` taxonomy renamed in the interface: "Submission types" → "Form types", "Submission type" → "Form type" — taxonomy labels, the menu item, the entry list column and filter, the "Additional" block, settings, export, the notification email, and the REST error texts; slugs, error codes, and data were left untouched.
- Implemented the `headless-main-form` RFC: the built-in main form (`Registry\Main_Form`, `key: main`, `source: builtin`) is served by `GET /forms(/main)(/schema)`, and the `main` key is reserved for it in `fforms_add_api_route()`.
- Added the `POST /fforms/v1/main` route with a flat camelCase payload: the `formType`/`formId`/`form_type`/`form_id` aliases (a conflict returns 400), the `_hp` honeypot, and the 422 `fforms_empty_submission` rule against empty submissions; the strict `/submit` contract is unchanged.
- The shared submission pipeline extracted from `REST_Controller::submit()` into a private `process()`; both routes now use the same rate limit, validation, entry creation, type assignment, and emails.
- Added the `fform_type` taxonomy: normalization and upsert by slug, an autocreation limit (the `fforms_max_form_types` filter, 50 by default) that keeps the incoming value in `_fforms_form_type_raw`, and a strict mode in the settings.
- The form and its type are linked both ways: publishing a CPT form creates a term with `slug = post_name` and the `_fforms_form_id` meta, renaming the form changes only `name`, and deleting the form keeps the term. The term slug became the third resolution path in `Form_Locator` — a stable string key for a form instead of a post ID.
- Off-schema data is stored separately from the validated data: the `_fforms_custom` (20 scalar keys), `_fforms_meta` (depth 3, 8 KiB), `_fforms_ref`, and `_fforms_user_id` meta keys; unknown top-level keys go to `customFields` rather than being dropped.
- Admin: a submission type column and filter in the entry list, an "Additional" block on the entry screen, a "Submission types" screen in the FForms menu, and navigation between a form and its entries both ways.
- `GET /entries` gained a `form_type` filter; the CSV gained the `form_type`, `ref`, `user_id`, `custom_fields`, and `meta` columns plus an export filter by type; the notification email gained a block with the type and the off-schema data.
- Settings extended with the allowed origins of the main form, the recipients of its notifications, and the strict type mode; the `/main` CORS branch uses that list regardless of which form the payload addressed.
- The overview moved to `admin.php?page=fforms-dashboard` with a redirect from the old address; added an "Accepting submissions over the API" block with the real route URL, the settings state, the type list, and four request examples.
- Attachments split out of `headless-main-form` into a separate `main-form-attachments` RFC: on `/main` they are rejected with 400 (`fforms_attachments_require_multipart` for JSON, `fforms_attachments_disabled` for multipart) and nothing is written to disk.
- The taxonomy is registered with `show_in_menu => false` plus one explicit menu item: core otherwise adds the screen separately for every associated CPT, and "Submission types" appeared twice.
- The taxonomy column is preserved in `Post_Types::entry_columns()`: the filter rebuilt the column set entirely and dropped the core-generated `taxonomy-fform_type`.
- The base and headless specifications extended with the main form, the `fform_type` taxonomy, the form resolution order, the new entry meta keys, the `/main` route, and its CORS handling.
- Recorded a verification limitation: `npm run lint:js` and `npm run lint:css` fail with the same error count on a clean tree (the ignore paths do not skip `node_modules`/`build`); `php -l` and `npm run build` pass.
- RFC `headless-main-form`: a built-in zero-config main form (`key: main`), the lenient `POST /fforms/v1/main` route with a flat payload, submission type as the `fform_type` taxonomy with a two-way form ↔ term link, a column and filter in the entry list, off-schema data, attachments, and moving the overview to `admin.php?page=fforms-dashboard` with request examples — the draft was brought up to the full RFC template.
- The bare embed document resets `height`/`min-height`/`overflow` on `html` and `body`: theme styles turned body into a scroll container inside the frame, so the document reported the frame's height instead of its own and the form was clipped with an inner scrollbar.
- Fixed the form being clipped inside a frame: the height is measured from the content rather than from `documentElement`, re-sent until the frame matches it, and applied as an inline style over the theme CSS; `scrolling="no"` was removed so a measurement error cannot silently cut off content.
- The public form gained a "bare" mode at `?fforms_embed=1` — a document without header, footer, or admin bar, meant for embedding in a frame; the iframe and js-script snippets now point at it, while the regular `/forms/{id}` link remains a full page for sharing.
- The exact frame URL is passed in `data-fforms-src` so the script does not have to guess the trailing slash in the permalink and catch an extra 301.
- The base specification extended with section 7.2 on embedding a form externally through an iframe and a js-script.
- All insertion points collected in the form sidebar: the shortcode, the public link, the iframe, and the js-script; the iframe and js-script are shown only for a published form in "Share via URL" mode.
- The public form page inside a frame reports its height to the parent through `postMessage` (`assets/public-form-frame.js`); outside a frame the reporter does not activate.
- Added `assets/embed.js` — embedding a form on a third-party site: the script inserts an iframe of the public form page and syncs its height through `postMessage`.
- The base specification extended with section 7.1 on the `[fform id=123]` shortcode, the unavailable-form policy, and asset enqueuing.
- Debugged the end-to-end insertion scenario: the block and the shortcode on a regular page produce identical markup, unique DOM ids, 201 on submit, 422 with a field error, and a fake success on the honeypot.
- Recorded that `/services/wwdb` is rendered by the theme's `code-driven` template bypassing `the_content()`, so the scenario was run on a regular page; the original page content was restored.
- The unavailable-form message became configurable: `Form_Renderer::render_form()` accepts `$unavailable_notice`, and the shared "visitor sees nothing, editor sees a message" policy moved into `editor_notice()`.
- Form content now expands shortcodes inside the recursion guard window, so `[fform id=X]` inside form X returns a circular-reference message instead of literal text.
- Added a field to the form sidebar with the ready-to-copy `[fform id=<ID>]` shortcode for a published form in `block`/`public` mode.
- Added the `[fform id=123]` shortcode (`includes/class-shortcode.php`) as a thin wrapper over `Form_Renderer::render_form()` with early and late enqueuing of the form assets.
- Removed the dead legacy assets `assets/block-editor.js`, `assets/view.js`, and `assets/view.css`, which were not enqueued from any PHP file.
- Added the `docs/rfc/form-embed-block-and-shortcode.md` RFC: inserting a form through the block and the `[fform id=123]` shortcode, a debugging scenario on a live page, and removal of the dead legacy assets.

## 2026-08-30

- Removed the test PHP fixture and the config flag; the Headless API E2E now creates and publishes a form purely through wp-admin.
- FForms blocks moved into a separate editor category; fields are hidden outside the form CPT, and the Headless API accepts field blocks only and rejects a different structure server-side.
- Allowed REST saving of protected form meta for users with `edit_post`, fixing the `_fforms_type` publishing error in Gutenberg.
- The root `fforms/headless-schema` became the server-side invariant of headless mode, protecting REST and the embedding ban when the mode meta value is stale.
- The Headless API is now edited through a locked `fforms/headless-schema` Gutenberg block with field blocks; the JSON textarea was removed, and `_fforms_schema` is compiled as a derived REST cache.
- Marked the verified mode-conversion criteria in the RFC.
- The E2E scenario opens the collapsed Headless API panel before checking the serialized schema.
- Merged the form creation and mode switching E2E scenarios so they share a single authenticated wp-env context.
- Added a safe fallback to the starter schema when converting invalid headless JSON back into blocks.
- Switching the mode now updates the block-editor store directly, preventing Gutenberg from re-adding the form from the template.
- Prevented the Gutenberg template from being re-inserted when switching to Headless API.
- Updated the E2E tests and the base specification for switching the mode in the sidebar without a selection screen.
- Added a mode switcher to the sidebar with conversion between Block editor and the Headless API JSON schema.
- Removed the special `post-new.php` flow; the schema repository now explicitly uses JSON for headless mode.
- The RFC was corrected after manual testing: the mode is chosen in the sidebar, and new forms are created as regular Block editor forms.
- Marked the completed functional criteria of the RFC and documented the separate global JS lint limitation.
- Formatted the Headless API settings panel according to the ESLint/Prettier rules.
- Recorded a limitation of the full `lint:js`: checking the changed JS files passes, while a global project sweep needs the ignore paths configured separately.
- The Block editor is again created with the canonical starter block set when a draft is explicitly created after choosing the mode.
- Brought the new mode-selection E2E spec in line with the JavaScript formatting rules.
- Clarified the lint configuration diagnostics for zsh so a missing `.eslintrc` does not cause a false failure.
- Recorded and fixed the E2E flakiness caused by a run started before the mode card selectors were updated.
- Updated the form creation E2E checks: the existing smoke scenario explicitly picks Block editor, and scenarios for mode selection and the headless editor were added.
- Updated the base and headless specifications for the immutable CPT form mode and the `mode` field in read REST responses.
- Recorded that the runtime check was unavailable until the stopped wp-env CLI container was started.
- Started implementing the form mode selection RFC: added the mode meta, mode selection on creation, the headless JSON editor, the REST field, and protection against Gutenberg embedding.
- Reverted the `.env` integration with wp-env: the debug constants are defined in `.wp-env.json` again, and `.env` stays for tests and other tooling.
- Wired up the build of the shared, editor, and runtime styles of the form block so the Gutenberg preview uses the vertical layout and the state styles.
- Added a Playwright smoke test for the new form template: CSS loading, vertical label/control layout, and an enabled submit button.
- Marked the passed check for adding a new form in Gutenberg in the RFC.
- Ignored the temporary Playwright artifacts created when e2e tests fail.
- Added a note that recursive deletion of temporary Playwright artifacts is forbidden in the current environment.
- The `test:e2e` command now calls Playwright directly and uses the configuration of the local WordPress site.
- Explicitly added the Playwright dev dependency required by the e2e configuration.
- Recorded that the SCSS check runs through `wp-scripts`, because standalone Stylelint has no project configuration.
- Recorded the existing `lint:pkg` errors in the `package.json` release metadata as a separate task.
- Moved the submission notification toggle into the settings of a specific form; the global setting now only activates the notification and autoreply settings.
- Updated the base specification and the roadmap for per-form notifications being off by default.
- Recorded that the runtime check was unavailable: the local wp-env container was stopped.

## 2026-08-28

- Added LOG.md and the rule to keep it after every change (AGENTS.md).
