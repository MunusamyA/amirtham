<?php
/**
 * Quick Add Seller Type modal for Product Form.
 * Uses shared components.css classes; no page-specific CSS.
 * The POST is handled by api/seller-types.php with normal role permissions.
 */
?>
<div
    class="modal-backdrop app-modal-host"
    id="quickSellerTypeDialog"
    aria-hidden="true"
>
    <div
        class="app-modal-dialog"
        data-size="sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="quickSellerTypeTitle"
        aria-describedby="quickSellerTypeDescription"
        tabindex="-1"
    >
        <form id="quickSellerTypeForm" class="app-modal-form" novalidate>
            <div class="modal-header">
                <div class="modal-header-copy">
                    <h2 id="quickSellerTypeTitle">Quick Add Seller Type</h2>
                    <p id="quickSellerTypeDescription">
                        Create a Seller Type without leaving Product Form.
                    </p>
                </div>
                <button
                    class="modal-close"
                    id="quickSellerTypeClose"
                    type="button"
                    title="Close"
                    aria-label="Close Quick Add Seller Type"
                >
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="field col-12">
                        <label class="required" for="quickSellerTypeName">Seller Type Name</label>
                        <input
                            id="quickSellerTypeName"
                            name="seller_type_name"
                            type="text"
                            maxlength="120"
                            autocomplete="off"
                            required
                            data-required-message="Seller Type Name is required."
                            placeholder="Example: Retail / Wholesale"
                        >
                    </div>
                </div>
                <div class="form-row">
                    <div class="field col-6">
                        <label for="quickSellerTypeSort">Sort Order</label>
                        <input
                            id="quickSellerTypeSort"
                            name="sort_order"
                            type="number"
                            min="0"
                            step="1"
                            value="0"
                            inputmode="numeric"
                        >
                    </div>
                    <div class="field col-6">
                        <label>Status</label>
                        <input type="hidden" name="status" value="1">
                        <div><span class="badge success">Active</span></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="buttons">
                    <button class="btn gray" id="cancelQuickSellerType" type="button">Cancel</button>
                    <button class="btn btn-primary" id="saveQuickSellerType" type="submit">
                        <i data-lucide="save"></i> Save Seller Type
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
