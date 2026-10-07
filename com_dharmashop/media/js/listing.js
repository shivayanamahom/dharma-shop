/** Dharma Shop: управление списком товаров. Подключается через WebAssetManager. */
(() => {
  'use strict';
  const initialise = () => {
    document.querySelectorAll('[data-dharmashop-listing-controls]').forEach((form) => {
      if (form.dataset.dharmashopListingReady) return;
      form.dataset.dharmashopListingReady = '1';
      form.addEventListener('change', (event) => {
        if (event.target.matches('[name="sort"], [name="product_view"]')) {
          form.requestSubmit();
        }
      });
      const submit = form.querySelector('[data-dharmashop-listing-submit]');
      if (submit) submit.hidden = true;
    });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialise, { once: true });
  } else {
    initialise();
  }
})();
