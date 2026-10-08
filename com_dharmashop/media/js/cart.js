/**
 * Dharma Shop: добавление в корзину из карточек и страницы товара. Подключается, только если «Режим каталога» выключен.
 * Кнопка — любой элемент с data-dharmashop-add-to-cart="<id товара>" (data-variant-id — вариант, по умолчанию вариант товара по умолчанию);
 * количество берётся из ближайшего виджета [data-dharmashop-qty] (иначе 1). Запрос — POST с токеном на cart.add (JSON).
 * После ответа шлётся событие `dharmashop:cart-updated` (detail: count, qty, subtotal, subtotalText, url), а элементы
 * [data-dharmashop-cart-count] и [data-dharmashop-cart-total] (модуль корзины) обновляются.
 */
(() => {
  'use strict';

  const options = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_dharmashop.cart')) || {};
  const text = (key, fallback) => (window.Joomla && Joomla.Text && Joomla.Text._(key)) || fallback;

  const updateIndicators = (cart) => {
    document.querySelectorAll('[data-dharmashop-cart-count]').forEach((element) => {
      element.textContent = String(cart.count);
      element.classList.toggle('d-none', cart.count === 0 && element.hasAttribute('data-hide-empty'));
    });
    document.querySelectorAll('[data-dharmashop-cart-total]').forEach((element) => {
      element.textContent = cart.subtotalText;
    });
    document.dispatchEvent(new CustomEvent('dharmashop:cart-updated', { detail: cart }));
  };

  // Сообщение об ошибке под кнопкой на несколько секунд (без зависимости от стилей шаблона: классы Bootstrap).
  const showError = (button, message) => {
    const host = button.closest('.dharmaCardActions') || button.parentElement;
    const note = document.createElement('div');
    note.className = 'small text-danger w-100 text-end mt-1';
    note.setAttribute('role', 'alert');
    note.textContent = message;
    host.appendChild(note);
    window.setTimeout(() => note.remove(), 4000);
  };

  const flash = (button) => {
    const label = button.querySelector('span:not(.visually-hidden)');
    if (!label) return;
    const original = label.textContent;
    label.textContent = text('COM_DHARMASHOP_CART_ADDED_SHORT', '✓');
    button.classList.add('active');
    window.setTimeout(() => {
      label.textContent = original;
      button.classList.remove('active');
    }, 1800);
  };

  const add = (button) => {
    if (!options.url || button.disabled) return;
    const scope = button.closest('.dharmaCardActions') || button.parentElement;
    const qtyInput = scope ? scope.querySelector('[data-dharmashop-qty] input') : null;
    const body = new URLSearchParams();
    body.set('product_id', button.dataset.dharmashopAddToCart || '');
    body.set('qty', qtyInput && qtyInput.value ? qtyInput.value : '1');
    if (button.dataset.variantId) body.set('variant_id', button.dataset.variantId);
    body.set(Joomla.getOptions('csrf.token'), '1');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    fetch(options.url, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.cart) updateIndicators(data.cart);
        if (data.success) flash(button);
        else showError(button, data.message || text('COM_DHARMASHOP_CART_ERROR_GENERIC', 'Error'));
      })
      .catch(() => showError(button, text('COM_DHARMASHOP_CART_ERROR_GENERIC', 'Error')))
      .finally(() => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
      });
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-dharmashop-add-to-cart]');
    if (!button) return;
    event.preventDefault();
    add(button);
  });
})();
