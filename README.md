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
npm install
npm run env:start        # http://localhost:8890 — admin/password
```

Плагин монтируется в контейнер из этой папки (`"plugins": ["."]` в [.wp-env.json](.wp-env.json)),
правки в PHP/JS видны сразу без перезапуска. Ядро WordPress качается в контейнер (`"core": null` — последний стабильный).

| Команда | Действие |
| --- | --- |
| `npm run env:start` | поднять окружение |
| `npm run env:stop` | остановить |
| `npm run env:restart` | пересобрать с обновлением ядра (`--update`) |
| `npm run env:reset` | сбросить БД |
| `npm run env:destroy` | удалить контейнеры и тома |
| `npm run env:logs` | логи контейнера WordPress |
| `npm run wp -- <args>` | WP-CLI, напр. `npm run wp -- plugin list` |

Порт 8890 выбран, чтобы не конфликтовать с окружением монорепо `wpcraft` на 8888.
Тестовое окружение отключено (`"testsEnvironment": false`) — включите, когда появятся PHPUnit-тесты.
Локальные переопределения — в `.wp-env.override.json` (в git не попадает).

`WP_DEBUG` и `SCRIPT_DEBUG` включены, PHP-ошибки пишутся в `wp-content/debug.log` внутри контейнера:

```bash
npx wp-env run cli sh -c 'tail -f /var/www/html/wp-content/debug.log'
```
