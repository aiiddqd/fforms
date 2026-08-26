# FForms

Лёгкий WordPress-плагин для contact/lead-форм с REST-first архитектурой.

## Быстрый старт

1. Активируйте плагин и откройте **FForms → Добавить форму**.
2. Задайте заголовок, тип и JSON-схему, затем опубликуйте форму.
3. Добавьте динамический блок **FForms** на страницу или отправляйте JSON в `POST /wp-json/fforms/v1/submit`.
4. Ответы доступны в **FForms → Ответы**, экспорт — в **FForms → Экспорт CSV**.

Пример submit:

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
