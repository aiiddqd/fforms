---
title: "Конструктор форм FForms на Gutenberg"
status: draft
created: 2026-08-26
updated: 2026-08-26
---

# RFC: Конструктор форм FForms на Gutenberg

## Вводные

Сейчас `fform` хранит поля в редактируемой вручную мета-записи `_fforms_schema`, а блок `fforms/form` только выбирает готовую форму. Нужно сделать Gutenberg основным конструктором: редактор собирает форму из вложенных блоков, а сервер получает из того же дерева схему для рендера и валидации.

Ориентир — Jetpack Forms. Файл `docs/rfc/jetpack/extensions/blocks/contact-form/contact-form.php` лишь подключает блок; основная схема находится в `jetpack_vendor/automattic/jetpack-forms`: `jetpack/contact-form` служит динамическим контейнером, поля являются дочерними блоками, отдельный CPT хранит переиспользуемые формы в `post_content`, а атрибут `ref` связывает вставку на странице с формой.

## Назначение и цели

- Создавать и изменять форму визуально, без JSON.
- Хранить структуру и оформление в нативной Gutenberg-разметке.
- Иметь один канонический источник данных: `post_content` записи `fform`.
- Сохранить серверную валидацию, REST API, entries и уведомления текущего MVP.
- Позволить одной форме использоваться на нескольких страницах и обновляться централизованно.
- Наследовать `theme.json` и Global Styles вместо отдельного визуального конструктора.

Заинтересованные стороны: редакторы сайта, разработчики тем/интеграций, администраторы, работающие с заявками, и команда, ревьюящая PHP, Gutenberg UX, доступность и безопасность.

## Техническая база на август 2026

Сохраняем заявленный минимум WordPress 6.5 и PHP 8.0, проверяем также актуальный WordPress 7.1. Все блоки используют `apiVersion: 3` и обязаны корректно работать в iframe-редакторе: editor-стили и скрипты объявляются в metadata, код не обращается к DOM родительского окна.

Для сборки выбираем стабильный `@wordpress/scripts`. Новый `@wordpress/build` пока не берём: официальный обзор 2026 года отмечает незакрытые сценарии block-плагинов и будущую интеграцию его движка в `wp-scripts` без смены привычных команд.

### Пакеты и установка

В проекте уже используется npm и `package-lock.json`, поэтому package manager не меняем. Node фиксируем в `.node-version` на актуальной LTS-линии 22. Обязательные зависимости:

```bash
# Сборка, lint, format, unit/e2e runner и release ZIP.
npm install --save-dev @wordpress/scripts@latest

# Публичные WordPress API, непосредственно импортируемые исходниками.
# Тег соответствует минимально поддерживаемому WordPress.
npm install --save \
  @wordpress/blocks@wp-6.5 \
  @wordpress/block-editor@wp-6.5 \
  @wordpress/components@wp-6.5 \
  @wordpress/core-data@wp-6.5 \
  @wordpress/data@wp-6.5 \
  @wordpress/element@wp-6.5 \
  @wordpress/i18n@wp-6.5 \
  @wordpress/interactivity@wp-6.5

# Добавить, когда появятся браузерные тесты с helper API WordPress.
npm install --save-dev @wordpress/e2e-test-utils-playwright@wp-6.5
```

`@wordpress/create-block` — одноразовый scaffold, а не зависимость проекта. Для сверки структуры можно выполнить в отдельной временной папке `npx @wordpress/create-block@latest example --namespace=fforms --variant=dynamic`; переносить созданный второй plugin bootstrap в FForms нельзя. Существующий `@wordpress/env` остаётся для воспроизводимых smoke/E2E и CI; локально допустим lerd. Webpack, Babel, React, ESLint, Prettier, Jest и Playwright вручную не устанавливаются, пока их предоставляет `@wordpress/scripts`.

`package.json` получает команды:

```json
{
  "scripts": {
    "start": "wp-scripts start --blocks-manifest --experimental-modules",
    "build": "wp-scripts build --blocks-manifest --experimental-modules",
    "format": "wp-scripts format",
    "lint:js": "wp-scripts lint-js",
    "lint:css": "wp-scripts lint-style",
    "lint:pkg": "wp-scripts lint-pkg-json",
    "test:unit": "wp-scripts test-unit-js",
    "test:e2e": "wp-scripts test-e2e",
    "plugin-zip": "wp-scripts plugin-zip"
  }
}
```

`package-lock.json` обязателен в Git; CI и чистая установка используют `npm ci`. Версии обновляются отдельным PR после чтения changelog, а не неограниченными диапазонами во время release build.

### Структура каталогов

Каждый блок изолирован в своей папке, а общая предметная логика не смешивается с React-компонентами:

```text
src/
├── blocks/
│   ├── form/
│   │   ├── block.json
│   │   ├── index.js
│   │   ├── edit.js
│   │   ├── save.js
│   │   ├── render.php
│   │   ├── editor.scss
│   │   ├── style.scss
│   │   └── view.js
│   ├── field-text/       # тот же минимальный набор файлов
│   ├── field-email/
│   └── submit/
├── components/           # общие editor-компоненты полей
├── shared/               # чистые JS helpers/constants
└── variations.js
includes/
├── Blocks/               # регистрация и PHP render services
├── Schema/               # compiler/validator/repository
└── Migration/            # legacy JSON -> blocks
build/                    # только результат wp-scripts, не редактировать
specs/                    # Playwright E2E
tests/js/                 # Jest unit tests
```

`src` и lockfile хранятся в Git. `build` создаётся автоматически; его можно не коммитить при наличии надёжного release pipeline, но он обязательно входит в устанавливаемый ZIP, потому что WordPress не собирает исходники на production.

## Решение

### Модель данных и блоков

`fform` получает поддержку `editor` и `revisions`. Его `post_content` содержит ровно один корневой динамический блок `fforms/form` с вложенными полями и кнопкой. Шаблон нового CPT:

```text
fforms/form
├── fforms/field-text       name="name"
├── fforms/field-email      name="email"
├── fforms/field-textarea   name="message"
└── fforms/submit
```

На обычной странице тот же `fforms/form` вставляется как ссылка с атрибутом `ref` (ID записи `fform`) и без собственных полей. Рендерер загружает опубликованный `fform`, разбирает его `post_content` и защищается от циклических ссылок. Текущий `formId` читается как устаревший alias `ref`, чтобы существующие страницы продолжили работать.

Первая версия регистрирует блоки:

- `fforms/form` — контейнер и ссылка на сохранённую форму;
- `fforms/field-text`, `field-textarea`, `field-email`, `field-tel`, `field-url`, `field-number`, `field-select`, `field-radio`, `field-checkbox`, `field-hidden`;
- `fforms/submit` — настоящий `<button type="submit">`, без подмены поведения `core/button`.

Общие атрибуты поля: стабильный `fieldId`, уникальный `name`, `label`, `required`, `placeholder`, `maxLength`; для вариантов — массив `{ value, label }`. `clientId` редактора нельзя использовать как постоянный ID. Поля разрешены только внутри `fforms/form`; контейнер разрешает поля, `fforms/submit` и ограниченный набор layout/content-блоков (`core/paragraph`, `heading`, `group`, `columns`, `column`, `spacer`, `separator`). Внутри формы должна быть ровно одна кнопка отправки.

Каждый `block.json` — единственный источник имени, атрибутов, supports, styles/scripts и `render`. Редактируемые значения получают `role: "content"`, чтобы работать в content-only и pattern override режимах WordPress 7.x. Для полей задаётся `ancestor: [ "fforms/form" ]`, а постоянный набор прямых детей контейнера — через `allowedBlocks` metadata. `supports.visibility` у отдельных полей отключается: скрытое только на одном viewport обязательное поле создаёт неотправляемую форму; скрывать можно контейнер целиком.

### Каноническая схема

Новый `Schema_Compiler` рекурсивно обходит `parse_blocks( $form->post_content )`, находит `fforms/field-*`, нормализует атрибуты и возвращает контракт текущего `Schema::validate_submission()`. Порядок блоков задаёт порядок полей.

`_fforms_schema` больше не редактируется человеком. Его можно оставить как производный кэш для совместимости, вместе с `_fforms_schema_hash`; при несовпадении хэша схема компилируется заново. REST endpoints `/forms/{id}` и `/forms/{id}/schema`, submit, письма и CSV всегда получают актуальную скомпилированную схему через единый метод репозитория, а не читают мета-поле напрямую.

Публикация блокируется, если отсутствует форма/кнопка, `name` пусты или повторяются, тип/опции некорректны. Submit повторно компилирует либо проверяет кэш и никогда не доверяет атрибутам, присланным клиентом.

### Редактор и фронтенд

`Edit` корневого блока использует `useBlockProps` и `useInnerBlocksProps`; поля показывают близкий к фронтенду preview и редактируются в canvas/Inspector Controls. Поскольку динамический контейнер содержит InnerBlocks, его `save.js` обязан сохранять `<InnerBlocks.Content />`; PHP `render.php` получает уже обработанный `$content`. У динамических листовых полей `save` возвращает `null`. PHP-шаблоны остаются тонкими и делегируют компиляцию/валидацию классам из `includes/`.

Для новых форм доступны вариации/паттерны «Контактная форма» и «Заявка». `style.scss` содержит общие editor/frontend правила, `editor.scss` — только canvas UI, а frontend-only CSS объявляется через `viewStyle`. Wrapper всегда использует `useBlockProps` в JS и `get_block_wrapper_attributes()` в PHP.

Динамический PHP-рендер сохраняет семантику `label`/`fieldset`/`legend`, уникальные DOM ID для каждой вставки и `aria-live` для результата. Базовые стили отвечают за layout, focus/error/disabled/loading; цвета, типографика, интервалы и границы идут через Block Supports и CSS-переменные `--wp--preset--*`. Assets подключаются только при фактическом рендере формы.

Текущий REST submit сохраняется, но новый frontend-код переносится из глобального `assets/view.js` в `src/blocks/form/view.js` и подключается как `viewScriptModule`. Для loading/success/error и отправки без перезагрузки используем стандартный Interactivity API (`supports.interactivity`, `data-wp-*` directives), не собственную систему гидрации. Сборке Script Modules пока нужен `--experimental-modules`. Клиент отправляет только `form_id`, `fields`, honeypot и `source`; сервер возвращает ошибки по `name`, а UI связывает их с контролами и переводит focus на первую ошибку.

Сборка с `--blocks-manifest` создаёт `build/blocks-manifest.php`. На WordPress 6.8+ все блоки регистрируются одним вызовом:

```php
wp_register_block_types_from_metadata_collection(
	FFORMS_DIR . 'build',
	FFORMS_DIR . 'build/blocks-manifest.php'
);
```

Для заявленного WordPress 6.5–6.7 используется официальный conditional fallback: загрузить manifest, при наличии вызвать `wp_register_block_metadata_collection()`, затем зарегистрировать пути из manifest через `register_block_type()`. Ручной список блоков и ручная регистрация их asset handles не поддерживаются.

### Совместимость и миграция

Формы с пустым `post_content` и существующей `_fforms_schema` продолжают рендериться legacy-путём. В редакторе им показывается действие «Преобразовать в блоки»: JSON преобразуется в дерево блоков, но мета не удаляется. После сохранения блоки становятся источником данных. Массовая автоматическая перезапись при обновлении плагина не выполняется.

Вне первой версии: inline-формы без CPT, multi-step, условная логика, загрузка файлов, survey/content mapping и произвольный HTML. Для них потребуются отдельные RFC после стабилизации базового конструктора.

## Критерии приёмки

- [ ] Новый `fform` открывается в block editor с готовым шаблоном «Имя / Email / Сообщение / Отправить».
- [ ] Поля можно добавлять, удалять, переставлять и настраивать без редактирования JSON.
- [ ] На странице блок-ссылка выбирает опубликованный `fform`; изменение формы обновляет все её вставки.
- [ ] `post_content` является каноническим источником, а REST schema совпадает с видимыми полями и их порядком.
- [ ] Сервер отклоняет дубли `name`, неверные варианты и значения, которых нет в схеме.
- [ ] Существующие блоки с `formId` и legacy-формы с `_fforms_schema` продолжают работать.
- [ ] Конвертация legacy-схемы не удаляет исходные данные и сохраняет все поддержанные атрибуты.
- [ ] Две вставки одной формы на странице имеют уникальные DOM ID и отправляются независимо.
- [ ] Клавиатура, label/control association, required/error states и focus после ошибки проходят ручную проверку доступности.
- [ ] Frontend assets не загружаются на страницах без формы; PHP/JS lint и автоматические тесты компилятора, REST submit и миграции проходят.
- [ ] `npm ci && npm run build` создаёт отдельные каталоги блоков и `build/blocks-manifest.php` без ручного webpack-конфига.
- [ ] Блоки регистрируются из metadata collection на WP 6.8+ и через проверенный fallback на WP 6.5–6.7.
- [ ] E2E проходит на минимальном WordPress 6.5 и актуальном WordPress 7.1, включая iframe editor.

## Дорожная карта

1. Зафиксировать Node 22, установить пакеты, обновить npm scripts и создать `src/blocks`/`build` pipeline с manifest.
2. Добавить `block.json`, поддержку редактора и revisions у CPT; зарегистрировать контейнер, поля и submit.
3. Реализовать `Schema_Compiler`, правила целостности и единый доступ к схеме; покрыть unit-тестами.
4. Перевести PHP render, REST, уведомления и экспорт на скомпилированную схему.
5. Сделать Gutenberg UI, шаблоны contact/lead и ограничение разрешённых блоков для `fform`.
6. Перенести frontend submit на Interactivity API; добавить legacy fallback, JSON → blocks и `formId` → `ref`.
7. Проверить editor/frontend parity, доступность, темы, WP 6.5/7.1, повторные вставки и полный submit flow.

## Риски и открытые вопросы

- Нужен продуктовый выбор capability: достаточно ли `edit_posts` для управления формами или оставить доступ только администраторам.
- При повышении минимальной версии WordPress до 6.8 conditional fallback можно удалить отдельным изменением.
- Полная свобода core-блоков усложняет валидную `<form>`-разметку; whitelist расширяется только тестируемыми блоками.
- Кэш схемы обязан инвалидироваться при REST-обновлении, ревизии и программном сохранении, а не только в стандартном UI.

## Итого и рекомендации

Рекомендуется повторить у Jetpack не накопившийся legacy-конвейер shortcodes, а актуальную модель: динамический form-container, нативные дочерние блоки и переиспользуемая форма по `ref`. Для FForms переход должен быть поэтапным: сначала визуальное редактирование существующего CPT и совместимый compiler, затем — дополнительные типы полей и сложное поведение.

## Проверенные источники

- [Metadata in block.json](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) — каноническая metadata и условная загрузка assets.
- [File structure of a block](https://developer.wordpress.org/block-editor/getting-started/fundamentals/file-structure-of-a-block/) — назначение `src`, `build`, editor/style/view/render файлов.
- [Registration of a block](https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/) и [регистрация metadata collection в WordPress 6.8](https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/).
- [`@wordpress/scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) — build manifest, lint, Jest, Playwright и release ZIP.
- [Nested Blocks: InnerBlocks](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/nested-blocks-inner-blocks/) и [static/dynamic rendering](https://developer.wordpress.org/block-editor/getting-started/fundamentals/static-dynamic-rendering/).
- [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) — `viewScriptModule`, directives и стандартная frontend-интерактивность.
- [Block Supports](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/) и [Block API versions](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-api-versions/) — wrapper API и iframe editor.
- [`@wordpress/build`: состояние в 2026 году](https://developer.wordpress.org/news/2026/04/wordpress-build-the-next-generation-of-wordpress-plugin-build-tooling/) — почему пока остаёмся на `wp-scripts`.
