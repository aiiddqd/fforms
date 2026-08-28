---
title: "Нативная стилизация и иконки Gutenberg-блоков FForms"
status: implementing
created: 2026-08-28
updated: 2026-08-28
---

# RFC: Нативная стилизация и иконки Gutenberg-блоков FForms

## Вводные

Конструктор уже выводит рабочую форму, но визуально выглядит как набор браузерных контролов: дочерние блоки не имеют собственных иконок, кнопка в editor-preview показана как disabled, а CSS частично задаёт дизайн жёсткими значениями. Рекомендуется сделать FForms участником стандартного style engine WordPress: Block Supports и Global Styles управляют дизайном, семантические классы WordPress связывают форму с `theme.json`, а CSS плагина оставляет только структуру, безопасные fallback и состояния.

Этот RFC дополняет `docs/rfc/gutenberg-form-builder.md` и не меняет его модель данных, REST-контракт или серверную валидацию.

## Назначение и цели

- Добиться близкого внешнего вида формы в iframe-редакторе и на фронтенде.
- Наследовать палитру, типографику, интервалы, границы и кнопки активной темы без отдельного визуального конструктора.
- Дать блокам стандартные Inspector/Global Styles controls в пределах возможностей WordPress 6.5.
- Сделать поля различимыми в Inserter, toolbar и List View.
- Сохранить нейтральный и доступный вид на WordPress 6.5–6.8 и в классических темах.
- Не увеличивать frontend footprint и не загружать assets на страницах без формы.

Заинтересованные стороны: редакторы форм, авторы тем, владельцы сайтов, разработчики FForms и ревьюеры Gutenberg UX/доступности.

Вне объёма: отдельная дизайн-система, theme-specific пресеты, декоративные иконки внутри публичной формы, responsive design controls, условная логика и изменение submit API.

## Текущее состояние

- `fforms/form` имеет только `align`; поля и `fforms/submit` не объявляют визуальные Block Supports.
- У полей и submit отсутствует `icon`, поэтому Gutenberg показывает общий fallback-значок.
- Динамические поля и submit не получают `get_block_wrapper_attributes()`: style engine не может надёжно применить к ним классы и inline styles.
- Общий `style.scss` задаёт размеры и цвета напрямую; `editor.scss` всегда рисует пунктирную рамку; `view.scss` содержит только loading state.
- Editor-preview использует disabled-контролы, поэтому обычная кнопка выглядит неактивной. Preview `select` не совпадает с frontend-разметкой.

## Решение

### 1. Контракт каскада

Порядок ответственности:

1. Семантический HTML и browser defaults обеспечивают работоспособность.
2. Global Styles темы задают базовые стили страницы и элементов.
3. Block Supports задают стили конкретного экземпляра блока.
4. CSS FForms задаёт layout, состояние, доступность и fallback для старых/классических тем.

Стили плагина используют низкую специфичность через `:where()` и не фиксируют font family, основной text/background color или theme-specific размеры. Контролы получают `font: inherit` и `color: inherit`. Нельзя предполагать наличие конкретного preset slug; допустимы `var(--wp--preset--…, <fallback>)`, `currentColor`, относительные единицы и документированные `--fforms-*` variables.

Существующие `.fforms-*` классы сохраняются для обратной совместимости. Новые публичные hooks и variables перечисляются в отдельном `docs/theming.md` и после релиза считаются стабильными.

### 2. WordPress elements и прогрессивное улучшение

- Submit остаётся настоящим `<button type="submit">`, получает `wp-element-button` и тем самым наследует `styles.elements.button`, включая поддержанные темой hover/focus/active states.
- Нативные `input`, `textarea` и `select` сохраняются без кастомной подмены. На WordPress 6.9+ они автоматически наследуют `styles.elements.textInput` и `styles.elements.select`, в том числе стили сторонних блоков.
- На WordPress 6.5–6.8 и в классических темах общий CSS обеспечивает только usable fallback: наследуемый шрифт, ширину, padding, видимую границу и `:focus-visible`. Он не должен выигрывать у Global Styles 6.9+.
- Checkbox/radio остаются нативными; допускается `accent-color: currentColor`. Цвет никогда не является единственным признаком required/error/success.

### 3. Block Supports и wrapper attributes

Точный metadata синтаксис проверяется по schema минимального WordPress 6.5; supports, появившиеся позже, не включаются без feature detection.

| Блок | Поддержка первой версии |
| --- | --- |
| `fforms/form` | текущие `align`; text/background/gradient color; margin/padding; font size/line height; доступные в WP 6.5 border и shadow controls |
| видимые `fforms/field-*` | text color; font size/line height; vertical margin и padding без собственной палитры по умолчанию |
| `fforms/submit` | набор, близкий к `core/button` на WP 6.5: text/background/gradient color, font size/line height, horizontal/vertical padding, border и shadow |
| `fforms/field-hidden` | без визуальных supports; в редакторе — понятный непубличный placeholder |

Каждый видимый динамический блок обязан вывести ровно один root с классом `.wp-block-<namespace>-<name>` и результатом `get_block_wrapper_attributes()`. Editor использует `useBlockProps()` на эквивалентном root. Если стиль должен применяться к внутреннему control, `selectors` в `block.json` направляет feature/subfeature selector; ручное чтение сериализованного `style` в React или PHP запрещено.

Для submit root/selector нацеливается на реальную кнопку, сохраняя классы `fforms-submit` и `wp-element-button`. Для reference-вставки дизайн сохранённой формы остаётся централизованным, а placement attributes (`align`, внешние margins) принадлежат вставке; итоговый HTML не должен создавать две визуальные рамки одного `fforms/form`.

### 4. Иконки и editor UX

В каждом `block.json` задаются `icon`, краткое `description`, `keywords` и осмысленный `example`. Первая версия использует монохромные Dashicon slugs, существующие в WordPress 6.5: общий знак формы для контейнера и семантически различные знаки для текста, email, телефона, URL, выбора, checkbox, hidden и submit. Цветные branded backgrounds и собственный icon font не добавляются. Если Dashicons окажется недостаточно, переход на общий набор SVG 24×24 из WordPress packages оформляется отдельным изменением; строковый icon в metadata остаётся fallback.

На фронтенд editor-иконки не выводятся. Editor-preview повторяет классы и геометрию frontend-контролов. Обычные preview-кнопка и поля не маркируются `disabled`: взаимодействие блокируется средствами редактора, а disabled/loading вид показывается только как явное состояние. `select`, radio, checkbox и hidden получают preview своего реального типа.

### 5. Разделение CSS

- `style.scss`: общая editor/frontend структура, наследование, размеры контролов и низкоспецифичный fallback.
- `editor.scss`: только editor affordances — outline выбранного блока, placeholder hidden-поля, validation notices; постоянная пунктирная рамка удаляется.
- `view.scss`: только runtime states — loading/disabled, field errors, response и визуальное скрытие honeypot.

Error/success переменные имеют контрастные defaults, но переопределяются темой. Focus outline не удаляется. Ошибка также выставляет `aria-invalid`, связывается через `aria-describedby`, содержит текст и переводит фокус на первый неверный control согласно действующему RFC конструктора.

## Совместимость и документация для тем

`docs/theming.md` содержит проверяемый пример `theme.json` для:

- `styles.blocks.fforms/form`;
- `styles.blocks.fforms/field-text` как образца поля;
- `styles.elements.button` для submit;
- `styles.elements.textInput` и `select` с пометкой «WordPress 6.9+»;
- CSS fallback для label, checkbox/radio и semantic state variables.

Legacy-формы из `_fforms_schema` получают те же control classes, element integration и state CSS, хотя instance-level Block Supports для отсутствующих блоков к ним неприменимы. Публичный REST payload, имена полей, label/control association и уникальные DOM ID не меняются.

## Критерии приёмки

- [x] Все 12 типов блоков имеют различимые, осмысленные иконки; в List View/Inserter нет общего fallback-значка.
- [x] `npm run build`, `npm run lint:js` и `npm run lint:css` проходят; `build/` получен только из `src/`.
- [x] Form, visible field и submit выводят block wrapper classes/attributes; выбранные Block Supports одинаково работают в editor и frontend.
- [ ] Тестовая block theme через `theme.json` меняет form container и button на WP 6.5, а `textInput`/`select` — на WP 7.1 без CSS FForms с повышенной специфичностью.
- [ ] На WP 6.5 форма остаётся читаемой и usable без `textInput`/`select` element styles; проверена также одна классическая тема.
- [x] Submit наследует `wp-element-button`; editor-preview не выглядит disabled в обычном состоянии.
- [x] Text, textarea, select, radio, checkbox и hidden имеют соответствующий типу preview и сохраняют frontend-семантику.
- [ ] Две reference-вставки наследуют централизованный дизайн формы, но сохраняют собственное выравнивание без двойного wrapper-дизайна.
- [ ] Keyboard/focus-visible, contrast, error/loading/success и label association проходят ручную проверку; цвет не является единственным сигналом.
- [ ] На странице без формы CSS/JS FForms не загружаются; legacy-render продолжает работать.
- [x] `docs/theming.md` содержит рабочий пример и список стабильных selectors/variables.

## Дорожная карта

1. Добавить metadata/icons/examples и зафиксировать matrix supports по WP 6.5 schema.
2. Провести wrapper/selector refactor PHP и editor-preview без изменения REST/schema.
3. Переразложить SCSS, добавить `wp-element-button` и progressive form-element fallback.
4. Добавить theming fixture/documentation и автоматические проверки editor/frontend classes.
5. Выполнить visual/accessibility matrix: WP 6.5 и 7.1, block и classic themes, legacy и block-based forms.

## Риски и открытые вопросы

- Border support в минимальной версии использует metadata-ключи, которыми пользуются Core blocks, но часть API исторически называлась experimental; перед реализацией нужен schema/runtime smoke на чистом WP 6.5.
- Global Styles пока не покрывают label, checkbox/radio и все focus states одинаково во всех поддерживаемых версиях, поэтому небольшой CSS FForms остаётся обязательным.
- Свобода стилизации каждого поля может перегрузить Inspector. После UX-проверки допустимо оставить полям только spacing/typography, сохранив расширенное оформление на уровне формы и темы.

## Итого и рекомендации

Рекомендуется сначала исправить интеграцию, а не рисовать собственную тему формы: wrapper attributes, Block Supports, `wp-element-button`, form elements WordPress 6.9+ и низкоспецифичный fallback. Это устранит вид со скриншота, сохранит лёгкость ядра и даст темам стандартный, документированный контракт.

## Проверенные источники

- [Metadata in block.json](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) — icons, styles/assets, selectors и metadata как источник регистрации.
- [Block Supports](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/) и [Selectors API](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-selectors/) — standard controls, wrapper attributes и применение styles к внутренним элементам.
- [Global Settings & Styles](https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/) и [Applying Styles](https://developer.wordpress.org/themes/global-settings-and-styles/styles/applying-styles/) — block/element styles и контракт `.wp-element-button`.
- [How WordPress 6.9 gives forms a theme.json makeover](https://developer.wordpress.org/news/2025/11/how-wordpress-6-9-gives-forms-a-theme-json-makeover/) — элементы `textInput`/`select`, ограничения form/focus styling.
- [Core Button reference](https://developer.wordpress.org/block-editor/reference-guides/core-blocks/core-blocks-design/core-block-button/) — supports/selectors стандартной кнопки как ориентир, но не как замена submit-контракта FForms.
- [How to extend a WordPress block](https://developer.wordpress.org/news/2024/08/how-to-extend-a-wordpress-block/) — рекомендация предпочитать Block Supports собственным controls.
