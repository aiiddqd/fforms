# FForms — рабочий контекст

## Назначение

FForms — лёгкий WordPress-плагин для контактных и lead-форм, хранения заявок
в WordPress и REST-first отправки. Плагин должен быть удобен как в обычном
Gutenberg-сайте, так и в headless/Jamstack-интеграциях.

Базовая продуктовая позиция: бесплатное, самодостаточное и предсказуемое ядро;
нативные Gutenberg-формы, которые наследуют `theme.json`/Global Styles; заявки
сохраняются локально по умолчанию. Сложные интеграции и платные возможности —
отдельные аддоны, а не разрастание ядра.

## Источники истины

Читайте документы до изменения затронутой области и не смешивайте фактическое
состояние с планом:

1. `docs/specs/base.md` — текущая реализованная и поддерживаемая базовая
   спецификация.
2. `docs/rfc/gutenberg-form-builder.md` — активный целевой переход на
   Gutenberg-конструктор; незакрытые критерии приёмки — работа в плане.
3. `ROADMAP.md` — следующий продуктовый приоритет.
4. `docs/rfc/mvp.md` — история и контракт MVP; его roadmap не переопределяет
   базовую спецификацию.
5. `docs/rfc/сf7.md` — продуктовые принципы, а не техническое ТЗ.

При противоречии не меняйте обратную совместимость молча: уточните решение или
обновите соответствующую спецификацию/RFC вместе с кодом.

## Доменная модель и публичный контракт

- `fform` — непубличный CPT формы; `fform_entry` — приватный CPT отправки.
- REST namespace: `fforms/v1`; text domain и PHP namespace: `fforms` и
  `FForms\` соответственно.
- Публичный submit: `POST /wp-json/fforms/v1/submit`, payload содержит
  `form_id`, `fields`, honeypot `website` и `source`.
- Публично доступны только опубликованные формы/схемы. Entries, экспорт и
  управление статусом требуют `manage_options`.
- Валидация всегда серверная: allowlist полей из схемы, нормализация,
  обязательность и типы. Не доверяйте атрибутам и валидации из браузера.
- Антиспам-контракт нельзя ослаблять: лимит по умолчанию 5 попыток / 60 секунд
  для пары форма+IP; заполненный honeypot возвращает ложный успешный ответ и не
  сохраняет entry/не отправляет письма.
- По умолчанию сохраняются данные, IP, User-Agent и source. Любое изменение
  хранения PII, retention или экспорта требует отдельного продуктового и
  privacy-решения.

## Gutenberg-архитектура

- Форма редактируется как один `fforms/form` с дочерними блоками полей и
  `fforms/submit`. Поддерживаемые поля: text, textarea, email, tel, url,
  number, select, radio, checkbox, hidden.
- Для формы с блоками `post_content` — канонический источник. `_fforms_schema`
  остаётся производным кэшем/legacy-данными; обращаться к схеме следует через
  `FForms\Schema\Schema_Repository`, не читая meta напрямую.
- Обычная страница хранит ссылку на опубликованную форму через `ref`; `formId`
  остаётся legacy alias. Одна форма должна централизованно обновлять все
  вставки.
- Legacy-формы с пустым `post_content` и `_fforms_schema` должны продолжать
  работать. Миграция JSON → blocks не удаляет исходные meta и не выполняется
  массово при обновлении.
- Блоки `apiVersion: 3`, работают в iframe-редакторе. Не обращаться к DOM
  родительского окна. Описывайте block metadata, assets, атрибуты и supports в
  `block.json`; не дублируйте вручную в PHP.
- Рендер остаётся динамическим и семантичным: связанные label/control,
  `fieldset`/`legend` где нужно, `aria-live`, уникальные DOM ID для двух
  вставок одной формы. Frontend assets загружаются только при рендере формы.
- Визуальная настройка использует Block Supports, `theme.json` и CSS variables;
  не вводите отдельную дизайн-систему или жёсткие theme-specific стили.
- Submit UI должен связывать server field errors с контролами и переводить
  фокус на первую ошибку. Изменения формы требуют ручной проверки клавиатуры,
  label association, error/loading/success states.

## Структура кода

```text
fforms.php                         bootstrap и константы
includes/class-*.php               CPT, REST, settings, mail, export
includes/Schema/                   compiler и единый repository схемы
includes/Blocks/                   PHP-рендер и регистрация блоков
includes/Migration/                совместимая миграция legacy JSON
src/blocks/<block>/                block.json, editor, render и styles
assets/                            legacy fallback/admin scripts
build/                             генерируемый результат wp-scripts
docs/                              спецификации, RFC и roadmap
```

Сохраняйте разделение: PHP-шаблоны/рендереры тонкие, предметная логика — в
`includes/Schema` и сервисах, React-код не становится источником серверной
валидации. Не редактируйте `build/` вручную: меняйте `src/`, затем собирайте.

## Совместимость и безопасность

- Поддерживаемый минимум: WordPress 6.5, PHP 8.0; также проверяем актуальный
  WordPress 7.1. Node закреплён на 22 (`.node-version`).
- На WP 6.8+ блоки регистрируются через metadata collection;
  на 6.5–6.7 обязателен проверенный fallback. Не возвращайте ручные списки
  блоков/asset handles как основной путь.
- Админские сохранения, CSV и изменение entry должны иметь nonce и capability
  checks. Публичный submit намеренно без nonce/auth.
- Встроенный SMTP глобально меняет PHPMailer WordPress; не расширяйте его как
  изолированный mailer FForms и предупреждайте о конфликте с SMTP-плагинами.

## Разработка и проверка

Используется npm с lockfile и `@wordpress/scripts`; для чистой установки —
`npm ci`, а не смена package manager. Основные команды:

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

`wp-env` монтирует этот плагин, поэтому изменения PHP/JS видны сразу; для
блоков после изменения `src/` нужна сборка. Перед завершением изменения
минимально выполните релевантные lint/build проверки. Для серверного submit
проверяйте как минимум 422 для неверных данных, 201 для корректных, honeypot,
rate limit и запрет неавторизованного доступа к entries.

## Ближайшие приоритеты

- Public form URL/страница уже есть; далее — embed через iframe и удобная
  навигация от формы к её entries.
- Уведомления по почте должны стать выключенными по умолчанию с явным включением
  и списком получателей.
- Приватность: настройка отключения хранения, retention, минимизация IP/UA,
  удаление данных формы и WordPress privacy tools.
- Экосистема: сначала стабильные hooks/filters и документированный versioned
  REST/schema contract, затем отдельный `fforms-addon` для CRM, webhooks,
  антиспама и коммерческих функций.

Не добавляйте conditional logic, multi-step, uploads, content/survey mapping,
аналитику или произвольный HTML без отдельного RFC после стабилизации
конструктора.
