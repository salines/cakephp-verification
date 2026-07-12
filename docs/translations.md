# Translations

All user-facing messages use the `verification` translation domain
(`__d('verification', ...)`). The plugin ships `.po` files for 12 locales:

`ar`, `de`, `es`, `fr`, `hr`, `it`, `ja`, `ko`, `pl`, `ru`, `uk`, `zh_CN`

They live in `resources/locales/<locale>/verification.po` inside the plugin
and are loaded automatically — the plugin registers a loader for the
`verification` domain in its bootstrap, so no app configuration is needed.
For any other locale (or when a string is missing) the English source text
is used.

## Selecting the locale

Set the request locale as usual, e.g.:

```php
\Cake\I18n\I18n::setLocale('hr');
```

or via the `App.defaultLocale` configuration / `LocaleSelectorMiddleware`.

## Overriding translations in your app

Create the file in your application:

```
resources/locales/<locale>/verification.po
```

When this file exists, it **replaces the plugin's file for that locale
entirely** (standard CakePHP behavior for translation overrides). It must
therefore contain every string you want translated, not just the ones you
change — copy the plugin's file as a starting point and edit it:

```bash
cp vendor/salines/cakephp-verification/resources/locales/hr/verification.po \
   resources/locales/hr/verification.po
```

## Adding a new language

Copy any shipped `verification.po` into
`resources/locales/<your-locale>/verification.po` in your app and translate
the `msgstr` entries. Contributions of new locales to the plugin are welcome.
