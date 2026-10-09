/** Native Joomla picker: sibling exclusions and image/price previews, without another dialog or API. */
(() => {
  'use strict';
  const products = new Map();
  const fields = (scope) => {
    const result = [...scope.querySelectorAll('[data-dharmashop-product-field]')];
    if (scope.matches?.('[data-dharmashop-product-field]')) result.unshift(scope);
    return result;
  };
  const update = (field) => {
    const id = Number(field.querySelector('.js-input-value')?.value);
    const product = products.get(id);
    const preview = field.querySelector('[data-picker-preview]');
    if (!preview) return;
    preview.hidden = !product;
    field.querySelector('[data-picker-summary]').hidden = !product;
    const image = field.querySelector('[data-picker-image]');
    let url;
    try {
      if (product?.image) url = new URL(product.image, window.location.origin);
    } catch { /* An invalid image must not leave the previous selection visible. */ }
    const hasImage = !!url && ['http:', 'https:'].includes(url.protocol);
    image.hidden = !hasImage;
    if (hasImage) image.src = url.href;
    else image.removeAttribute('src');
    field.querySelector('[data-picker-placeholder]').hidden = hasImage;
    ['price', 'regular', 'discount'].forEach((key) => {
      const node = field.querySelector(`[data-picker-${key}]`);
      node.textContent = product?.[key] || '';
      node.hidden = !product?.[key];
    });
  };
  const setup = (scope) => fields(scope).forEach((field) => {
    try {
      const product = JSON.parse(field.dataset.pickerProduct || 'null');
      if (product?.id && !products.has(Number(product.id))) products.set(Number(product.id), product);
    } catch { /* Empty repeated-row templates have no product. */ }
    update(field);
  });
  // Registered before opening the native dialog: its value-change event sees these plain strings.
  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin || event.data?.messageType !== 'joomla:content-select'
      || event.data.contentType !== 'com_dharmashop.product') return;
    const fromDialog = [...document.querySelectorAll('joomla-dialog.joomla-dialog-content-select-field iframe')]
      .some((frame) => frame.contentWindow === event.source);
    if (!fromDialog) return;
    try {
      const product = JSON.parse(event.data.pickerProduct || 'null');
      const id = Number(event.data.id);
      if (!Number.isSafeInteger(id) || id < 1 || Number(product?.id) !== id) return;
      products.set(id, product);
      fields(document).forEach((field) => update(field));
    } catch { /* Ignore messages without our optional preview payload. */ }
  });
  document.addEventListener('change', (event) => {
    if (!event.target.matches('.js-input-value')) return;
    const field = event.target.closest('[data-dharmashop-product-field]');
    if (field) update(field);
  });
  document.addEventListener('DOMContentLoaded', () => setup(document));
  document.addEventListener('joomla:updated', (event) => setup(event.target));
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
