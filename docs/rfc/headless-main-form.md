---
title: "Главная zero-config форма для headless-приёма заявок"
status: draft
created: 2026-09-12
---

# RFC: headless main form — главная zero-config форма

- [ ] `POST /wp-json/fforms/v1/form` - меняем на `POST /wp-json/fforms/v1/main`

## Вводные

Сегодня, чтобы принять заявку с внешнего фронтенда, нужно сначала завести форму: создать CPT-форму в админке (и узнать её post ID) либо зарегистрировать code-форму через `fforms_add_api_route()`. Для простого «отправь мне заявку с Astro-сайта» это лишний шаг. Предлагается добавить **встроенную главную форму** с фиксированной схемой (`name`, `email`, `phone`, `message`, `attachments`) и отдельный лояльный маршрут `POST /wp-json/fforms/v1/form`, принимающий плоский camelCase-payload. Работает сразу после активации плагина, без единой настройки.

## Назначение и цели

- Дать headless-фронтенду (AstroJS и подобные) рабочий endpoint приёма заявок без предварительного создания формы.
- Не ломать существующий контракт: `POST /submit` с `form_id`/`form_key` и объектом `fields` остаётся ровно таким, каким его используют блок, шорткод, публичная страница и code-формы.
- Принимать «как есть» произвольный контекст запроса (`ref`, `meta`, `customFields`, `userId`), не ослабляя серверную валидацию схемы.
- Принимать файлы во вложениях, не открывая при этом анонимную загрузку в media library.

Заинтересованные стороны: разработчики headless-фронтендов, интеграторы FForms, ревьюеры REST-контракта и безопасности.

## Составляющие и особенности

### 1. Встроенная главная форма

`FForms\Registry\Main_Form` собирает `Form_Ref` в памяти: `post_id = 0`, `key = 'main'`, `source = 'builtin'`, схема из четырёх полей (`name` text, `email` email, `phone` tel, `message` textarea) — все необязательные. Ключ `main` резервируется: `fforms_add_api_route( 'main', … )` возвращает `WP_Error` (`fforms_reserved_key`). Главная форма не создаёт записей в БД, не редактируется в админке и не встраивается блоком.

`Form_Locator::resolve( 'main' )` возвращает этот `Form_Ref`, поэтому существующие маршруты `GET /forms/main` и `GET /forms/main/schema` начинают работать без отдельного кода, а `GET /forms` отдаёт главную форму в общем списке с `source: 'builtin'` и `mode: 'headless'` — фронтенд может сгенерировать разметку по схеме.

Серверное правило против пустых заявок: непустым должно быть хотя бы одно из `email`, `phone`, `message`, иначе 422 `fforms_empty_submission`.

### 2. Маршрут `POST /fforms/v1/form`

Принимает `application/json` и `multipart/form-data`. Параметры (все опциональны, кроме правила из §1):

| Параметр | Тип | Назначение |
| --- | --- | --- |
| `formId` | string\|int | Ключ code-формы или ID CPT-формы. По умолчанию — `main`. Резолвится через `Form_Locator`. |
| `name`, `email`, `phone`, `message` | string | Поля схемы; проходят обычную `Schema::validate_submission()`. |
| `customFields` | object | Поля вне схемы — см. §3. |
| `meta` | object | Произвольный контекст запроса — см. §3. |
| `ref` | string | `utm_source` или `ref` из query string. |
| `userId` | int | Идентификатор пользователя на стороне клиента. |
| `attachments` | file[] | Только в `multipart`, см. §4. |
| `website` | string | Honeypot, контракт не меняется. |
| `source` | string | Как в `/submit`; при отсутствии берётся `Referer`. |

Обработка — тот же пайплайн, что в `base.md` §6: лимит размера тела → резолв формы → honeypot (200 без entry) → rate limit → валидация схемы → entry → письма → `fforms_entry_created`. Общий код выносится из `REST_Controller::submit()` в приватный `process( Form_Ref $form, array $fields, WP_REST_Request $request )`; новый маршрут отличается только маппингом плоского payload в `fields` и обработкой extras/вложений. Ответы те же: 201 с `entry_id`, 422 с ошибками по полям (ключи — camelCase, как пришли), 400/404/413/429/500.

Rate limit считается по паре «`code:main` + IP» — существующий механизм, ключ главной формы не пересекается с CPT-формами.

### 3. Данные вне схемы

Валидация схемы не ослабляется: `_fforms_data` по-прежнему содержит только прошедшие allowlist поля. Всё остальное сохраняется отдельными метами entry:

- `_fforms_custom` — `customFields`: ключи через `sanitize_key`, значения только скаляры (нескалярные отбрасываются), максимум 20 ключей, 2 000 символов на значение.
- `_fforms_meta` — `meta`: JSON, глубина ≤ 3, ≤ 8 KiB после кодирования; при превышении — 422 `fforms_meta_too_large`.
- `_fforms_ref` — `sanitize_text_field`, ≤ 200 символов.
- `_fforms_user_id` — `absint`; значение приходит от клиента и никаких прав не даёт. В админке показывается ссылкой на профиль только если такой пользователь существует.

Отображение: блок «Дополнительно» на экране просмотра entry; в CSV добавляются колонки `ref`, `user_id`, `custom_fields` (JSON), `meta` (JSON), `attachments` (имена файлов). В письмо уведомления extras попадают отдельным блоком после полей формы.

### 4. Вложения

Файлы принимаются только в `multipart/form-data` как `attachments[]`; `attachments` в JSON-теле — 400 `fforms_attachments_require_multipart`.

- Лимиты: ≤ 3 файлов, ≤ 5 MiB каждый; общий лимит тела для этого маршрута — отдельный фильтр `fforms_main_form_max_upload_bytes` (по умолчанию 16 MiB), поскольку `fforms_max_request_bytes` (256 KiB) остаётся лимитом JSON-запросов.
- Тип проверяется `wp_check_filetype_and_ext()` и `finfo` по содержимому, а не по расширению. Allowlist: pdf, png, jpg/jpeg, webp, txt, csv, docx, xlsx. Исполняемые и активные типы (php, phtml, html, js, svg) запрещены всегда, фильтром типов их расширить нельзя.
- Хранение вне media library: `uploads/fforms-private/<Y>/<m>/` с `index.html`, `.htaccess` (deny from all) и случайным именем файла. Публичного URL у файла нет; attachment-записи не создаются.
- Выдача: `GET /fforms/v1/entries/{id}/attachments/{index}` с `manage_options`, отдаёт файл потоком с `Content-Disposition: attachment`. Ссылки на этот маршрут идут в письмо и на экран entry; сами файлы к письму не прикрепляются.
- Файлы записываются на диск **после** успешной валидации и создания entry — невалидные и rate-limited запросы не оставляют мусора. Удаление entry (`before_delete_post`) удаляет его файлы.
- Тумблер в настройках «Разрешить вложения» — **по умолчанию выключен**; при выключенном тумблере `attachments` игнорируются с 400 `fforms_attachments_disabled`. Причина: анонимная загрузка файлов расширяет поверхность атаки и расход диска, а `base.md` §10 требует осознанного решения по хранению. Остальная часть главной формы работает zero-config и без этого тумблера.

### 5. CORS и настройки

Главная форма резолвится с `origins = []`, поэтому по умолчанию CORS-заголовки не отправляются — кросс-доменный запрос требует явного allowlist. Добавляется поле настроек «Разрешённые origins главной формы» (список через запятую) плюс существующий фильтр `fforms_allowed_origins( array $origins, ?Form_Ref $form )`. Preflight `OPTIONS` для нового маршрута работает так же, как для `/submit` (см. `api-route-headless-cms-mode.md` §6). Zero-config относится к созданию формы, не к CORS: для внешнего домена origin указать придётся.

Уведомления главной формы следуют общему правилу плагина — по умолчанию выключены; получатели задаются в настройках (по умолчанию `admin_email` при включении).

### 6. Вне объёма

Редактирование главной формы в админке, её рендер блоком или на публичной странице, per-form токены и подписи запросов, webhooks, `v2` REST API, приём вложений на существующем `/submit`.

## Критерии приемки (checklist)

- [ ] `POST /fforms/v1/form` без `formId` и с одним `message` создаёт entry: 201, `entry_id`, `message`; в админке entry подписан «Главная форма (API)».
- [ ] Запрос без `email`, `phone` и `message` возвращает 422 `fforms_empty_submission` и не создаёт entry.
- [ ] `formId` с ключом code-формы и `formId` с ID опубликованной CPT-формы направляют заявку в эту форму; несуществующий `formId` — 404 `fforms_form_not_found`.
- [ ] `GET /fforms/v1/forms` содержит главную форму (`key: "main"`, `source: "builtin"`); `GET /forms/main/schema` отдаёт четыре поля.
- [ ] `fforms_add_api_route( 'main', … )` возвращает `WP_Error` и не ломает загрузку сайта.
- [ ] Honeypot `website` даёт 200 без entry и писем; rate limit даёт 429; превышение лимита тела — 413.
- [ ] `customFields` с 25 ключами и вложенным объектом сохраняет 20 скалярных ключей в `_fforms_custom`; `_fforms_data` содержит только поля схемы.
- [ ] `meta` глубиной 5 или размером больше 8 KiB возвращает 422 `fforms_meta_too_large`.
- [ ] `ref` и `userId` видны на экране entry и в CSV-колонках `ref`, `user_id`; несуществующий `userId` не рендерится ссылкой.
- [ ] При выключенном тумблере вложений `multipart`-запрос с файлом возвращает 400 `fforms_attachments_disabled`.
- [ ] При включённом тумблере: pdf и png сохраняются, `.php` и `.svg` отклоняются (в том числе при переименовании в `.pdf`), 4-й файл и файл больше 5 MiB отклоняются.
- [ ] Файл недоступен по прямому URL в `uploads/`; `GET /entries/{id}/attachments/{index}` отдаёт его администратору и возвращает 401/403 анонимному запросу.
- [ ] Удаление entry удаляет связанные файлы с диска.
- [ ] Запрос с origin из настроек получает `Access-Control-Allow-Origin`, с чужого — нет; `OPTIONS` возвращает 204.
- [ ] `POST /submit` из блока, шорткода и публичной страницы не изменился; `php -l`, `npm run lint:js`, `npm run lint:css`, `npm run build` проходят.
- [ ] `docs/specs/api-route-headless-cms-mode.md` и `docs/specs/base.md` дополнены описанием главной формы, новых мет entry и вложений; в `LOG.md` есть запись.

## Дорожная карта

1. `Main_Form`, резерв ключа `main`, резолв в `Form_Locator`, главная форма в `GET /forms(/main)(/schema)`.
2. Выделение общего пайплайна `process()` из `REST_Controller::submit()` и новый маршрут `POST /form` с маппингом плоского payload.
3. Extras: меты `_fforms_custom`, `_fforms_meta`, `_fforms_ref`, `_fforms_user_id`, их отображение в админке, письме и CSV.
4. Настройки: origins главной формы, получатели уведомлений, тумблер вложений; CORS-ветка для нового маршрута.
5. Вложения: multipart-ветка, проверка типов, приватное хранилище, защищённая выдача, удаление вместе с entry.
6. Документация (`base.md`, `api-route-headless-cms-mode.md`, пример AstroJS) и ручная проверка чек-листа.

Шаги 1–2 блокируют всё остальное; 3 и 4 независимы между собой; 5 требует 2 и 4.

## Предпосылки

Главная zero config форма для headless-режима, которая автоматически подхватывает опубликованную форму и предоставляет её через REST API без необходимости ручной настройки.

- имеет какой то базовый wp json rest api route
- принимает ряд базовых параметров, таких как 
    - `formId` - key string field
    - `userId` - id user - optional
    - `meta` - optional context for the request - any json field
    - `ref` - reference to the form - optional - utm_source query string or just ref= query string
    - email - optional
    - name - optional
    - message - optional
    - phone - optional
    - attachments - optional - array of file objects
    - customFields - optional - any additional custom fields as a JSON object - key-value pairs

## Итого и рекомендации

Главная форма — это встроенный `Form_Ref` с фиксированной схемой, а не запись в БД и не «подхват» произвольной опубликованной формы: так поведение endpoint'а одинаково на dev/stage/prod и не зависит от того, какие формы завёл редактор. Лояльный payload живёт на отдельном маршруте `POST /form`, поэтому строгий контракт `/submit` остаётся нетронутым, а вся разница сводится к слою маппинга перед общим пайплайном. Данные вне схемы сохраняются, но отдельно от валидированных — граница «что прошло серверную валидацию» не размывается.

## Открытые вопросы

- Вложения заметно увеличивают объём RFC и поверхность атаки. Если на реализации станет тесно — вынести §4 в отдельный RFC `main-form-attachments`, оставив здесь только контракт «attachments игнорируются».
- Тумблер вложений по умолчанию выключен ради безопасности, что формально нарушает «zero config». Подтвердить или включить по умолчанию с более жёсткими лимитами.
- Нужен ли главной форме `store_entries: false` (только письмо, без сохранения) — тот же вопрос остался открытым в `use-fforms-as-headlesscms-backend.md`.
- Нужна ли отдельная админ-страница «Главная форма» с настройками и примером кода для фронтенда, или достаточно секции в общих настройках.
