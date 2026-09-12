# headless form - main zero config form

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