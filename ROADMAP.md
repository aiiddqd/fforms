# ROADMAP

## Next

- [ ] pr https://github.com/aiiddqd/fforms/pull/6 
    - translate to english

- [ ] render form in pages http://wpc.localhost/wp-admin/post.php?post=75457&action=edit

- [ ] share-link-panel [rfc](docs/rfc/share-link-panel-and-page-layout.md)

- [ ] testing

- [ ] settings - Email отправителя & Имя отправителя - make as optional - add new option like "Custom Email & Name"

- [x] classes nameing Notifications as Notifications.php `./includes/class-notifications.php`

    
- [x] insert form to pages as block with select of available forms
    - плюс шорткод `[fform id=123]` как вторая точка вставки (см. `docs/specs/base.md` §7.1)

- [x] добавить 2 формы по умолчанию - при активации плагина
    - контактная форма и лид форма
    - формы можно отключить

- [x] в коллекции ответов (заявок) - важно иметь возможность фильтрации заявок по форме
    - у формы должа быть ссылка на список ответов-заявок - например, "View Entries" или "Смотреть заявки" - и она должна вести уже на фильтрованный список

- [x] fix styles about basic forms /Users/aa/Projects/ddhq/gits/wpcraft/wp/wp-content/plugins/_fforms/docs/rfc/gutenberg-styles-and-icons.md 

- [x] публичные формы имеют урл и открываются по урл - своя шапка и подвал минимальные и кнопка отправить - сценарий как у как Гугл Формы
- [x] публичные формы - поддержка вставки через iframe
    - плюс js-script со вставкой iframe и автовысотой (см. `docs/specs/base.md` §7.2)
- [ ] у формы должна быть кнопка перехода к записям кототрые сохранены через эту форму
- [x] отправка на почту — опция формы, по умолчанию отключена; для включённой формы можно указать получателей через запятую

- [ ] В отличие от CF7, fforms сохраняет заявки по умолчанию. Это полезная особенность, но она требует понятных настроек: возможность отключить хранение, срок автоматического удаления, минимизация IP и user agent, удаление данных формы и экспорт персональных данных. Локальная обработка без обязательного SaaS должна быть частью позиционирования.

- [ ] 5. Расширяемость вместо включения всех функций в ядро - написать RFC и плагин fforms-addon - который будет включать доп опции он уже с freemius и лимитами и т д - разблок за оплату типа 50-100 долларов в год
    - Следует заранее закрепить публичные hooks и filters для валидации, обработки отправки, уведомлений, антиспама и действий после сохранения entry. REST API и формат схемы должны быть документированы и версионированы. 
    - Интеграции с CRM, Telegram, Slack, Make/n8n, платежами и дополнительными антиспам-сервисами лучше реализовывать как независимые модули или аддоны. Так ядро останется простым, а вокруг него сможет появиться экосистема.

- [ ] проверить работу разных форм [rfc](docs/rfc/gutenberg-form-builder.md)
- [x] add option for form - like public form - like brief
    - если ставим такую галочку у форма - форма начинает рабоать как Gogle Forms или Yandex Forms - просто ссылка типа website.example/forms/234
    - любой у кого есть эта ссылка может ее заполнить и отправить
    
- [x] Gutenberg form builder in console


## Future
- [ ] Gutenberg frontend editor
- [ ] Markdown frontend editor
