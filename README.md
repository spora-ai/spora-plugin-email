# spora-plugin-email

Spora plugin: email. The full reference (install, configuration,
per-tool parameters, development) has moved to the docs site:

**[docs.spora-ai.com/develop/plugins/reference/email](https://docs.spora-ai.com/develop/plugins/reference/email)**

See the docs site for the canonical reference. The README previously
in this repo has been migrated there.

## Translations

This plugin ships a German catalogue at [`locales/de.json`](locales/de.json),
declared through `translationsPaths()` in `src/EmailPlugin.php`. The host
merges it under core's own catalogue, so a German install reads the plugin's
operator copy in German.

The **key is the English source string**, byte for byte — editing the English
in the source changes the key, so the old entry stops matching and the new
English has no entry. The gate is this repository's own
`tests/Unit/LocaleCatalogTest.php`: it fails on an empty translation, on a
translation identical to its key, and on a key the source no longer produces.

**Translated**

- the tool's display name — `Email`
- the thirteen `#[ToolSetting]` descriptions
- the fourteen descriptions the tool returns from `describeAction()` — six
  literal ones and eight `sprintf()` templates
- the `plugin.json` description

**Never translated** — these strings are read by the model, so the operator
sees exactly what the model sees:

- `#[Tool].description`
- `#[ToolOperation].description`
- `#[ToolParameter].description`
- every `#[ToolSetting].label` and its option labels, including the labels of
  settings that carry `exposeToLlm: true` — `smtp_from` and
  `smtp_allowed_recipients` are two of them

An approval prompt is translatable only in the `%s` form. The host looks the
stored, already-rendered sentence up and matches it against the catalogue's
`%s` / `%d` templates, so the key is the `sprintf()` template —
`"Sending email to %s with subject: '%s'"` — and never the rendered text. A
`{$subject}` interpolation has no template to match and never resolves.

See the [plugin author guide → Translations](https://docs.spora-ai.com/develop/plugins/author-guide/translations).
