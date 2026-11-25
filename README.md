# Rebel My Forms

[![PHPCS check](https://github.com/Digitalist-Open-Cloud/RebelMyForms/actions/workflows/phpcs.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelMyForms/actions/workflows/phpcs.yaml)
[![Semgrep OSS scan](https://github.com/Digitalist-Open-Cloud/RebelMyForms/actions/workflows/semgrep.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelMyForms/actions/workflows/semgrep.yaml)
[![Test plugin with Matomo](https://github.com/Digitalist-Open-Cloud/RebelMyForms/actions/workflows/matomo.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelMyForms/actions/workflows/matomo.yaml)
[![PHPStan check](https://github.com/Digitalist-Open-Cloud/RebelMyForms/actions/workflows/phpstan.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelMyForms/actions/workflows/phpstan.yaml)

A Matomo plugin that shows how visitors use the forms of a website:

- How often each form is seen, started and submitted.
- How long visitors spend in a form.
- For each field: how many use it, how long they stay in it, how often they change it or come
  back to it, how often it is left empty, and how often it is the last field before a visitor
  gives up.

What visitors type is never collected. The plugin sends the names of forms and fields, times
and counts.

## Install

Activate the plugin. It adds itself to the JavaScript tracker, `matomo.js`, so no change to the
tracking code of a website is needed. If Matomo can not write `matomo.js`, run:

```sh
./console custom-matomo-js:update
```

A website that loads the tracker through Matomo Tag Manager is covered too.

## Which forms are tracked

Every `<form>` on a page, up to 20 forms on a page and 50 fields in a form. A form that is
added after the page was loaded is counted when a visitor first uses it.

A form is named by the first of these that it has: the attribute `data-rebel-form`, its `id`,
its `name`, the path of its `action`. A field is named by `data-rebel-field`, its `name`, its
`id` or its type. Give a form a name of your own when the same form has different IDs on
different pages:

```html
<form data-rebel-form="Newsletter signup">
  <input type="email" name="email" data-rebel-field="E-mail">
</form>
```

To leave a form or a field out, add `data-rebel-ignore` to it:

```html
<form id="login" data-rebel-ignore>
```

Hidden fields and buttons are not fields. A form counts as submitted when the browser sends its
`submit` event, also when the website then refuses what was sent.

To stop tracking the forms of a website, turn off **Track forms** under Administration >
Websites > Manage.

## Reports

**Forms** in the reporting menu has these reports for the chosen website and period:

- **Forms over time**: a graph of the form views, starts, submissions, the start rate or the
  submit rate.
- **Forms in numbers**: the same five for the period.
- **Forms**: one row for each form. A form is seen once for each page view it is on, and
  started when a visitor uses one of its fields. The start rate is the share of the views that
  were started, and the submit rate the share of the starts that were submitted. "Submitted
  again" counts the forms that were submitted more than once in a page view, which usually
  means the website refused the first attempt. Click a form to see its fields.
- **Forms by page**: the pages the forms are on.

The fields of a form:

| Column | What it tells |
|---|---|
| Used by | How often a visitor put the focus in the field or changed it |
| Avg. time in field | How long the field had the focus |
| Changes | How often its content was changed. Many more than "Used by" means corrections |
| Returns | How often a visitor came back to the field |
| Left empty | How often it was empty when the form was submitted |
| Refused by browser | How often the browser would not send the form because of the field: required and empty, or not of the right type or pattern |
| Drop-offs | How often it was the last field of a visitor who left without submitting |
| Drop-off rate | Drop-offs as a share of "Used by" |

The reports are built when Matomo processes its reports, and work with segments and row
evolution. The segment "Form" narrows any other report to the visits that saw a form.

The visit log and the visitor profile list the forms a visitor started, with whether they
submitted them or the field they left at.

## Documentation in Matomo

**Form analytics documentation** under Administration > System tells the same as this file, for
the users of Matomo.

## API

For users with view access to the website:

- `RebelMyForms.get`
- `RebelMyForms.getForms`
- `RebelMyForms.getPages`
- `RebelMyForms.getFields`, with `idSubtable` from a row of `getForms`

## Privacy

Form data goes through the tracker of Matomo, so consent, opt-out and the exclusions of the
website apply. It is stored with the visit and deleted together with it.

## Development

```sh
./console tests:run RebelMyForms
./console tests:run-ui --plugin=RebelMyForms
deno test --allow-read tests/js
```
