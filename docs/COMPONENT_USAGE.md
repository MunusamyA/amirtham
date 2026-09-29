# Reusable Component Usage

These functions are stable across projects. Change their appearance in `assets/css/theme.css`, not in the JavaScript engines.

## Global Select / searchable select

```html
<select id="role_id" data-placeholder="Select or type role">
  <option value="">Select role</option>
</select>
<script src="assets/js/global-select.js"></script>
<script>
const roleSelect = GlobalSelect.init(document.getElementById('role_id'), {
  placeholder: 'Select or type role'
});
roleSelect.setOptions([{ value: 1, text: 'Manager' }], 1);
</script>
```

## File / image upload

```html
<input id="media" name="media_files[]" type="file" multiple>
<script src="assets/js/file-upload.js"></script>
<script>
FileUpload.init(document.getElementById('media'), {
  multiple: true,
  maxFiles: 5,
  allowedTypes: ['jpg','jpeg','png','webp']
});
</script>
```

## Validation and regex

```html
<input name="mobile" data-validation="mobile" required>
<input name="code" data-regex="^[A-Z]{3}[0-9]{4}$" data-regex-message="Use ABC1234 format.">
```

Reusable named pattern:

```js
Validation.addPattern('employee_code', /^[A-Z]{3}[0-9]{4}$/, 'Use ABC1234 format.');
```

Then:

```html
<input data-validation="employee_code">
```

## DataTable

`AppDataTable.init(...)` keeps DataTables as the data/export engine while the Starter Kit renders its own search, page length, loader, info and compact pagination. The UI can be redesigned by styling `.app-datatable`, `.app-table-*`, and `.app-table-page-*`.

## Action icons

```js
App.createIconAction({ href: 'form.php?id=1', icon: 'pencil', label: 'Edit' });
App.createIconAction({ icon: 'circle-off', label: 'Deactivate', tone: 'danger' });
```

Supported semantic tones include default/primary, success, warning and danger. The selected theme owns the actual colors.

## Toast

```js
showToast('Saved successfully.', { type: 'success', duration: 3 });
showToast('Information notice', { type: 'info', duration: 3 });
```

## Light / Dark

`appearance.js` switches the `data-appearance` state. `theme.css` owns Light/Dark values, while `theme.js` applies optional semantic color overrides from Theme Settings.

## Common modal

Every protected page receives one shared modal host from `include/modal.php`, loaded by `include/topbar.php`.
Use `AppModal.open(element, { size: 'sm|md|lg|xl', focusSelector: '...' })` and `AppModal.close()`.
Do not create page-specific modal backdrops. Place the page's form/content in the DOM with class `hidden`; `AppModal` moves it into the shared host while open and restores it on close.

Example:

```js
AppModal.open(document.getElementById('exampleForm'), {
    size: 'lg',
    focusSelector: '[name="name"]'
});
```

For decimal fields, prefer `type="text" inputmode="decimal" data-validation="decimal"` with the shared Validation rules rather than HTML `type="number"`.

## Quick form vs full page standard

Use the common modal only for small/quick forms. Use a separate page for forms with many fields, sections, tabs, item grids, calculations, uploads, approvals or long workflows.

A large page may still launch a small quick-master component. Example: Purchase remains a full page, but **Add HSN** opens the reusable HSN modal.

### Reusable HSN quick form

```php
<?php require __DIR__ . '/../include/forms/hsn-form.php'; ?>
```

```javascript
AppHSNForm.openCreate({
    onSaved: function (hsn) {
        // Insert/select hsn.id in the current field.
    }
});
```

Do not copy the HSN form HTML or HSN save JavaScript into Purchase/Product pages.
