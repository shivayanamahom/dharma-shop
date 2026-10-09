/** Update the native Joomla modal URL with sibling selections; no replacement dialog or field markup. */
(() => {
  'use strict';
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-button-action="select"][data-modal-config]');
    const field = button?.closest('[data-dharmashop-product-picker="unique"]');
    if (!field) return;
    const subform = field.closest('joomla-field-subform');
    if (!subform) return;
    const selected = new Set();
    subform.querySelectorAll('[data-dharmashop-product-picker="unique"] .js-input-value').forEach((input) => {
      if (input.closest('joomla-field-subform') !== subform || input.closest('.subform-repeatable-template-section')) return;
      const id = Number(input.value);
      if (Number.isSafeInteger(id) && id > 0) selected.add(id);
    });
    // In the product editor, never offer the product being edited as its own recommendation.
    const form = field.closest('form');
    if (form?.id === 'product-form') {
      const id = Number(form.querySelector('[name="jform[id]"]')?.value);
      if (Number.isSafeInteger(id) && id > 0) selected.add(id);
    }
    const config = JSON.parse(button.dataset.modalConfig);
    const url = new URL(config.src, window.location.origin);
    url.searchParams.set('picker_exclude', [...selected].slice(0, 101).join(','));
    // Start each newly opened picker at page one after the exclusion set changes.
    url.searchParams.set('limitstart', '0');
    config.src = url.toString();
    button.dataset.modalConfig = JSON.stringify(config);
  }, true); // Runs before Joomla reads data-modal-config in its bubbling click handler.
})();
