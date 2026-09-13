# FForms

Современный WordPress-плагин для contact/lead-форм с REST-first архитектурой.

## Use cases
- contact forms
- lead forms
- public forms, quizzes, surveys and brief forms
- headless cms and email smtp for form submissions from JAMStack sites like AstroJS

## Два режима

У плагина ровно две точки входа.

**Headless API** — один роут `POST /wp-json/fforms/v1/main` без схемы. Форму создавать не нужно: какие поля передали, такие и записали, а `formType` классифицирует заявку, чтобы записи можно было фильтровать. Без `formType` заявка получает тип `main`.

```bash
curl -X POST https://example.com/wp-json/fforms/v1/main \
  -H "Content-Type: application/json" \
  -d '{ "formType": "consultation", "email": "anna@example.com", "company": "Acme" }'
```

**Конструктор** — форма собирается блоками в Gutenberg и после публикации доступна сразу всеми способами: блок **Form by FForms**, шорткод `[fform id=123]`, а если включить «Share via link» в панели «Publication» — ещё и секретная ссылка `/forms/<токен>/` (без индексации), iframe- и js-сниппеты. Ссылку можно перевыпустить: старая перестаёт работать сразу.

## Быстрый старт

1. Активируйте плагин и откройте **FForms → Добавить форму**.
2. Соберите поля блоками и опубликуйте форму.
3. Вставьте её блоком или шорткодом; для внешнего сайта включите «Share via link» и скопируйте нужный сниппет.
4. Записи доступны в **FForms → Записи**, экспорт — в **FForms → Экспорт CSV**.

Пример submit из формы конструктора:

```json
{
  "form_id": 123,
  "fields": {
    "name": "Анна",
    "email": "anna@example.com",
    "message": "Перезвоните мне"
  },
  "website": "",
  "source": "https://example.com/contact"
}
```

Встроенный SMTP выключен по умолчанию. Не включайте его одновременно с другим SMTP-плагином.

## Локальная разработка (wp-env)

Требуется Docker и Node 20+.

```bash
make install
make start        # http://localhost:8890 — admin/password
make status       # проверить, что всё поднялось
```

Плагин монтируется в контейнер из этой папки (`"plugins": ["."]` в [.wp-env.json](.wp-env.json)),
правки в PHP/JS видны сразу без перезапуска. Ядро WordPress качается в контейнер
(`"core": null` — последний стабильный).

Полный список целей — `make help`. Те же операции есть и как npm-скрипты:

| make | npm | Действие |
| --- | --- | --- |
| `make start` | `npm run env:start` | поднять окружение |
| `make stop` | `npm run env:stop` | остановить |
| `make restart` | — | остановить и поднять заново |
| `make update` | `npm run env:restart` | обновить ядро WordPress (`--update`) |
| `make reset` | `npm run env:reset` | сбросить БД и переустановить WP |
| `make destroy` | `npm run env:destroy` | удалить контейнеры и тома |
| `make xdebug` | — | поднять с включённым Xdebug |
| `make logs` | `npm run env:logs` | логи PHP и Docker |
| `make tail` | — | следить за `wp-content/debug.log` |
| `make cli CMD="plugin list"` | `npm run wp -- plugin list` | WP-CLI |
| `make bash` | — | шелл внутри контейнера |
| `make status` | — | версии WP/PHP, состояние плагина и блока |

Порт 8890 выбран, чтобы не конфликтовать с окружением монорепо `wpcraft` на 8888.
Тестовое окружение отключено (`"testsEnvironment": false`) — включите, когда появятся PHPUnit-тесты.
Локальные переопределения — в `.wp-env.override.json` (в git не попадает).

`WP_DEBUG` и `SCRIPT_DEBUG` включены, PHP-ошибки пишутся в `wp-content/debug.log` внутри контейнера:

```bash
make tail
```
