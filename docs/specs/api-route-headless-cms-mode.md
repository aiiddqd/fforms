---
status: current
updated: 2026-08-30
---

# FForms: headless-режим (code-формы)

Дополняет [`base.md`](base.md): описывает программную регистрацию форм в коде и связанные изменения REST-контракта, введённые RFC `use-fforms-as-headlesscms-backend`. Базовый сценарий с CPT-формами (`base.md`) не меняется — code-формы работают как второй, полностью равноправный способ адресовать форму.

## 1. Назначение

Headless-фронтенд (например, AstroJS) не может полагаться на post ID как на стабильный идентификатор формы между окружениями. Code-формы решают это: форма описывается в PHP темы/плагина (название, ключ, поля) без записи в БД, и становится доступна через `fforms/v1` по строковому ключу.

## 2. Регистрация

```php
add_action( 'fforms_register_forms', function () {
	fforms_add_api_route( 'contact_astro', array(
		'title'           => 'Контакт (Astro)',
		'fields'          => array(
			array( 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ),
			array( 'name' => 'message', 'label' => 'Сообщение', 'type' => 'textarea', 'required' => true ),
		),
		'origins'         => array( 'https://example.com' ),
		'success_message' => 'Спасибо!',
		'notifications'   => array( 'enabled' => true, 'to' => 'sales@example.com', 'subject' => '' ),
		'autoreply'       => array( 'enabled' => false, 'email_field' => 'email', 'subject' => '', 'message' => '' ),
	) );
} );
```

- Действие `fforms_register_forms` вызывается на `init`, приоритет 5 — до `rest_api_init`.
- Обязательны `title` и непустой валидный `fields`; `fields` проходит через тот же `Schema::normalize()`, что и CPT-формы (лимиты: 50 полей, 100 опций, `max_length`). В отличие от CPT-редактора, пустая или полностью невалидная схема — `WP_Error`, а не подстановка `Schema::defaults()`.
- Ключ: `sanitize_key`, паттерн `^[a-z0-9_]{1,32}$`. Повторная регистрация ключа — `WP_Error`; первая регистрация побеждает. Реестр (`FForms\Registry\Code_Forms`) живёт только в памяти запроса — источник правды остаётся код.
- `fforms_add_api_route()` возвращает `true` либо `WP_Error`; ошибка регистрации не прерывает загрузку сайта.

## 3. Единая адресация формы

`FForms\Form_Ref` — value object (`post_id`, `key`, `title`, `schema`, `success_message`, `origins`, `notifications`, `source` = `post`|`code`). `FForms\Form_Locator::resolve( int|string $ref ): Form_Ref|WP_Error` возвращает его для опубликованной CPT-формы (по ID) или зарегистрированной code-формы (по ключу). Submit, read-маршруты, `Notifications` и админ-колонки работают только через `Form_Ref`.

CPT-формы всегда резолвятся с `origins = []` — per-form CORS настраивается только для code-форм (см. §6).

## 4. REST-контракт (обновлено)

| Метод и маршрут | Доступ | Назначение |
| --- | --- | --- |
| `POST /submit` | публичный | `form_id` ИЛИ `form_key` — ровно один обязателен. |
| `GET /forms` | публичный | CPT- и code-формы вместе; у каждой есть `key` (у CPT — `null`), `source` и `mode` (`block` или `headless`). |
| `GET /forms/{id}` | публичный | Как раньше, для CPT-формы. |
| `GET /forms/{id}/schema` | публичный | Как раньше. |
| `GET /forms/{key}` | публичный | Code-форма по ключу; регистрируется после числового маршрута. |
| `GET /forms/{key}/schema` | публичный | Схема code-формы. |
| `GET /entries` | `manage_options` | Фильтры `form_id` и `form_key` (можно оба, но обычно один). |
| `POST /entries/{id}/status` | `manage_options` | Без изменений. |

Отсутствие и `form_id`, и `form_key` в submit — 400 `fforms_form_ref_required`. Несуществующий `form_id`/`form_key` — 404 `fforms_form_not_found`. Дальше submit не меняется: honeypot, rate limit, валидация, entry, письма, `fforms_entry_created` — те же шаги, что в `base.md` §6, просто оперируют `Form_Ref` вместо голого `form_id`. Rate limit считается по паре «ссылка на форму (`post:{id}` либо `code:{key}`) + IP», так что код-форма с ключом-цифрой не делит транзиент с CPT-формой того же ID.

`fforms_entry_created` теперь получает `Form_Ref` вторым аргументом вместо `int $form_id`.

## 5. Entries, письма, админка

- Entry code-формы: `_fforms_form_id = 0`, `_fforms_form_key = <ключ>`. Entry CPT-формы не меняется (`_fforms_form_key` пустая строка).
- Список ответов, колонка «Форма», CSV-экспорт и селект экспорта резолвят название через `Form_Locator`, а не `get_the_title()`. Если ключ entry больше не зарегистрирован в коде, колонка/CSV показывают сам ключ — данные не теряются.
- CSV получает колонку `form_key`; параметр экспорта — `form_ref` (`post:{id}` или `code:{key}`, старый `form_id` ещё принимается для обратной совместимости).
- `Notifications::send( Form_Ref $form, int $entry_id, array $data )` берёт настройки писем (`notifications`, `autoreply`) из `Form_Ref` вместо `get_post_meta()`. Глобальный тумблер `Settings::get()['notifications']` остаётся общим предохранителем для обоих источников форм.

## 6. CORS

CORS-заголовки плагин отдаёт только для namespace `fforms/v1`; остальные REST-маршруты сайта используют штатное поведение ядра WordPress без изменений.

- `Access-Control-Allow-Origin` появляется только если заголовок `Origin` запроса точно совпадает (схема + хост + порт) с одним из `origins` формы. Совпадение проверяется у конкретной формы для реального запроса (submit по `form_id`/`form_key`, чтение по `{id}`/`{key}`); для preflight `OPTIONS` (у которого ещё нет тела с `form_id`/`form_key`) допускается origin, разрешённый *любой* зарегистрированной code-формой.
- `Access-Control-Allow-Credentials` не отправляется никогда, wildcard-origin не поддерживается.
- Preflight `OPTIONS` на `/submit` возвращает 204 с `Access-Control-Allow-Methods: GET, POST, OPTIONS`, `Access-Control-Allow-Headers: Content-Type`, `Access-Control-Max-Age: 600`.
- Итоговый список origins расширяется фильтром `fforms_allowed_origins( array $origins, ?Form_Ref $form )`.
- Форма (CPT или code) без настроенных `origins` ведёт себя как раньше — заголовки не отправляются, CORS остаётся заботой сайта.

## 7. Расширяемость (дополнение к base.md §9)

- `fforms_add_api_route( string $key, array $args ): true|WP_Error` — публичная функция регистрации.
- `fforms_register_forms` — action для регистрации code-форм, `init` @5.
- `fforms_allowed_origins` — filter, расширяет allowlist origins для CORS.
- `fforms_rate_limit` / `fforms_rate_window` — второй параметр теперь строковый rate-ref (`post:{id}`/`code:{key}`) вместо `int $form_id`.

## 8. Вне объёма (без изменений от RFC)

Редактирование code-форм в админке, рендер code-формы блоком `fforms/form`, программное удаление зарегистрированных форм, per-form токены/подписи запросов, версия `v2` REST API.
