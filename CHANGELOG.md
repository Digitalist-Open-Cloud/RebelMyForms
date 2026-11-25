# Changelog

## Unreleased

### Added

- The forms of a website are tracked through the JavaScript tracker: when they are seen, started and submitted, and what happens in each field. What visitors type is not collected.
- The report "Forms", with the fields of each form: use, time, changes, returns, fields left empty and drop-offs.
- The reports "Forms over time" and "Forms in numbers".
- The report "Forms by page".
- How often the browser refused a field, and how often a form was submitted more than once in a page view.
- The visit log shows the forms a visitor started.
- A setting for each website to turn form tracking off.
- A documentation page in Matomo, under Administration.
- The fields of a form are listed in the order they have in the form.
- `RebelMyForms.success()` for the website to tell that a form went through.
- An element with `data-rebel-form` is tracked as a form.
- The report "Forms by device type".
- How long visitors wait before they start a form, and how long they hesitate in a field before they answer.
- The report "Fields of all forms", where a field can be followed over time.
- A form that was started, submitted or went through can be a step of a funnel in RebelFunnels.
- The segment "Form outcome": seen, abandoned, submitted or succeeded.
- The segment "Form", to narrow any report to the visits that saw a form.
- The attributes `data-rebel-form` and `data-rebel-field` to name a form or a field, and `data-rebel-ignore` to leave one out.
