# HSN Master

HSN is implemented as a small reusable quick-master form.

## Final UI rule

- Small / quick forms use the common modal.
- Large / detailed forms use a separate page.
- A large form may still open a small quick-master modal inside its workflow.

## Reusable files

- `include/modal.php` — one common modal host for the application.
- `assets/js/modal.js` — common modal behavior.
- `include/forms/hsn-form.php` — reusable HSN form markup.
- `assets/js/hsn-form.js` — reusable HSN create/edit/load/save behavior.
- `api/hsn.php` — canonical HSN API.
- `api/hsn-master.php` — backward-compatible alias for older code.
- `hsn-master.php` — HSN list/DataTable only; it uses the reusable form.

## Use from any page

Include the form once on the page:

```php
<?php require __DIR__ . '/include/forms/hsn-form.php'; ?>
```

Create:

```javascript
AppHSNForm.openCreate({
    onSaved: function (hsn) {
        // Add the new HSN to the current select and select it.
    }
});
```

Edit:

```javascript
AppHSNForm.openEdit(hsnId, {
    onSaved: function (hsn) {
        // Refresh the current select/row without leaving the page.
    }
});
```

A successful save returns the full HSN object:

```text
id
hsn_code
description
gst_rate
cgst_rate
sgst_rate
igst_rate
cess_rate
status
```

This is suitable for Purchase/Product pages because the caller can immediately append/select the saved HSN without reloading the page.

## Input policy

HSN and tax values use text inputs with `inputmode` and common validation. Tax fields do not use `type="number"`.
