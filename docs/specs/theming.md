# Темизация FForms

FForms использует стандартные Block Supports и Global Styles WordPress. Дизайн
контейнера задавайте на `fforms/form`, отдельных полей — на их типе, а кнопку
отправки — через глобальный элемент `button` или блок `fforms/submit`.

Все CSS-хуки ниже являются публичным контрактом FForms. Они применимы и к
legacy-формам из `_fforms_schema`, кроме instance-level Block Supports, которых
у legacy-записи нет.

## Пример для WordPress 6.5+

Добавьте нужные значения к `styles` в `theme.json` активной темы. Имена preset
(`base`, `contrast`, `40`, `medium`) — только примеры: используйте слуги,
которые определяет ваша тема.

```json
{
  "version": 3,
  "styles": {
    "blocks": {
      "fforms/form": {
        "color": {
          "background": "var:preset|color|base",
          "text": "var:preset|color|contrast"
        },
        "spacing": {
          "padding": {
            "top": "var:preset|spacing|40",
            "right": "var:preset|spacing|40",
            "bottom": "var:preset|spacing|40",
            "left": "var:preset|spacing|40"
          }
        },
        "border": {
          "radius": "8px"
        },
        "typography": {
          "fontSize": "var:preset|font-size|medium"
        }
      },
      "fforms/field-text": {
        "spacing": {
          "margin": {
            "top": "var:preset|spacing|20",
            "bottom": "var:preset|spacing|20"
          }
        },
        "typography": {
          "lineHeight": "1.5"
        }
      },
      "fforms/submit": {
        "border": {
          "radius": "999px"
        }
      }
    },
    "elements": {
      "button": {
        "color": {
          "background": "var:preset|color|contrast",
          "text": "var:preset|color|base"
        },
        "typography": {
          "fontSize": "var:preset|font-size|small"
        }
      }
    }
  }
}
```

FForms adds `wp-element-button` to its actual submit `<button>`, so
`styles.elements.button` applies to every form. A block-specific
`styles.blocks.fforms/submit` rule remains available for an intentional local
override.

## WordPress 6.9+ form controls

WordPress 6.9 adds Global Styles elements for text controls. On a site that
requires WordPress 6.9 or newer, extend the same `styles.elements` object with:

```json
{
  "textInput": {
    "border": {
      "color": "var:preset|color|contrast-2",
      "radius": "4px",
      "width": "1px"
    },
    "spacing": {
      "padding": {
        "top": "0.65rem",
        "right": "0.8rem",
        "bottom": "0.65rem",
        "left": "0.8rem"
      }
    }
  },
  "select": {
    "border": {
      "color": "var:preset|color|contrast-2",
      "radius": "4px",
      "width": "1px"
    }
  }
}
```

Do not add those two keys to a theme that must remain compatible with WordPress
6.5–6.8. FForms keeps native controls and provides a small, low-specificity
fallback on those versions and in classic themes.

## Stable selectors

- `.wp-block-fforms-form` — one saved form container; an inserted form is
  enclosed by `.fforms-reference` so its placement can have a separate
  alignment or outer margin.
- `.fforms-form`, `.fforms-fields` — HTML form and its field stack.
- `.wp-block-fforms-field-*`, `.fforms-field` — a visible block field and its
  legacy equivalent.
- `.fforms-label`, `.fforms-control`, `.fforms-choice`,
  `.fforms-choice-control` — labels and native controls.
- `.wp-block-fforms-submit`, `.fforms-submit.wp-element-button` — the actual
  submit button.
- `.fforms-field-error`, `.fforms-response`, `.fforms-response.is-error` —
  validation and submit states.

## CSS fallback and state variables

Theme CSS can override the variables without replacing FForms structure or
accessibility states. Error and success always include text and ARIA state; do
not use colour as the only signal in further overrides.

```css
:root {
  --fforms-form-gap: 1.25rem;
  --fforms-field-gap: 0.5rem;
  --fforms-control-padding: 0.65em 0.8em;
  --fforms-control-border-width: 1px;
  --fforms-control-border-color: currentColor;
  --fforms-control-border-radius: 4px;
  --fforms-textarea-min-height: 10rem;
  --fforms-choice-gap: 0.5rem;
  --fforms-submit-padding: 0.7em 1.2em;
  --fforms-focus-color: currentColor;
  --fforms-focus-width: 2px;
  --fforms-focus-offset: 2px;
  --fforms-error-color: #b42318;
  --fforms-error-background: #fff0f0;
  --fforms-success-color: #16853a;
  --fforms-success-background: #edfaef;
  --fforms-response-padding: 0.75rem 1rem;
}
```

Keep `:focus-visible` outlines intact. If a theme changes their appearance, it
must retain a clearly visible non-colour-only focus indicator.
