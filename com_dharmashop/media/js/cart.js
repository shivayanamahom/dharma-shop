/**
 * Dharma Shop: добавление в корзину из карточек и страницы товара. Подключается, только если «Режим каталога» выключен.
 *
 * Кнопка — элемент с data-dharmashop-add-to-cart="<id товара>" (data-variant-id — вариант; по умолчанию вариант по умолчанию).
 * Режимы (настройка «Режим кнопки»): button — только кнопка; qty — количество берётся из ближайшего [data-dharmashop-qty];
 * stepper — после добавления кнопка заменяется виджетом (убрать / число / «+») внутри [data-dharmashop-cart-control].
 *
 * Запросы — POST с токеном на cart.add / cart.update / cart.remove (JSON). Ответ содержит сводку корзины и карту товаров в корзине
 * (cart.products: id товара → {item, qty}); после каждого ответа шлётся событие `dharmashop:cart-updated` (detail = cart + source + notification), по нему
 * обновляются значки модуля ([data-dharmashop-cart-count], [data-dharmashop-cart-total]) и все виджеты на странице — в том числе
 * после действий в мини-корзине.
 */
(() => {
  'use strict';

  const options = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_dharmashop.cart')) || {};
  const text = (key, fallback) => (window.Joomla && Joomla.Text && Joomla.Text._(key)) || fallback;
  const round = (value) => Math.round(value * 1000) / 1000;
  const format = (value) => String(round(value));
  const busy = new WeakSet();

  const post = (url, params) => {
    const body = new URLSearchParams(params);
    body.set(Joomla.getOptions('csrf.token'), '1');

    return fetch(url, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    }).then((response) => response.json());
  };

  const indicators = (cart) => {
    document.querySelectorAll('[data-dharmashop-cart-count]').forEach((element) => {
      element.textContent = String(cart.count);
      element.classList.toggle('d-none', cart.count === 0 && element.hasAttribute('data-hide-empty'));
    });
    document.querySelectorAll('[data-dharmashop-cart-total]').forEach((element) => {
      element.textContent = cart.totalText || cart.subtotalText;
    });
  };

  // Сообщение об ошибке рядом с действием на несколько секунд (классы Bootstrap, без зависимости от шаблона).
  const showError = (anchor, message) => {
    const host = anchor.closest('.dharmaCardActions') || anchor.parentElement;
    const note = document.createElement('div');
    note.className = 'small text-danger w-100 text-end mt-1';
    note.setAttribute('role', 'alert');
    note.textContent = message;
    host.appendChild(note);
    window.setTimeout(() => note.remove(), 4000);
  };

  // --- Виджет «убрать / число / +» -----------------------------------------------------------------------------------------------

  const updateMinus = (control) => {
    const stepper = control.querySelector('[data-dharmashop-stepper]');
    if (!stepper) return;
    const last = (parseFloat(control.dataset.qty) || 0) - (parseFloat(control.dataset.step) || 1) < (parseFloat(control.dataset.min) || 1) - 1e-9;
    stepper.querySelector('[data-dharmashop-stepper-trash]').classList.toggle('d-none', !last);
    stepper.querySelector('[data-dharmashop-stepper-minus-sign]').classList.toggle('d-none', last);
  };

  // Показывает состояние товара в виджете: в корзине — виджет вместо кнопки, нет — кнопка.
  const render = (control, state) => {
    const button = control.querySelector('[data-dharmashop-add-to-cart]');
    let stepper = control.querySelector('[data-dharmashop-stepper]');

    if (state && state.qty > 0) {
      if (!stepper) {
        const template = document.querySelector('template[data-dharmashop-stepper-template]');
        if (!template) return;
        stepper = template.content.firstElementChild.cloneNode(true);
        control.appendChild(stepper);
      }
      control.dataset.item = String(state.item);
      control.dataset.qty = String(state.qty);
      stepper.querySelector('[data-dharmashop-stepper-qty]').textContent = format(state.qty);
      updateMinus(control);
      if (button) button.classList.add('d-none');
    } else {
      if (stepper) stepper.remove();
      control.dataset.qty = '0';
      control.dataset.item = '0';
      if (button) button.classList.remove('d-none');
    }
  };

  const sync = (products) => {
    if (!products) return;
    document.querySelectorAll('[data-dharmashop-cart-control]').forEach((control) => render(control, products[control.dataset.product]));
  };

  const apply = (data, anchor, source) => {
    if (data.cart) {
      indicators(data.cart);
      sync(data.cart.products);
      // source: «add» — добавлена позиция (боковая панель модуля может открыться), «change» — изменено количество или позиция удалена.
      document.dispatchEvent(new CustomEvent('dharmashop:cart-updated', { detail: Object.assign({}, data.cart, { source: data.success ? source : 'error', notification: data.success ? data.notification : null }) }));
    }
    if (!data.success) showError(anchor, data.message || text('COM_DHARMASHOP_CART_ERROR_GENERIC', 'Error'));
  };

  const stepperAction = (control, direction) => {
    if (busy.has(control)) return;
    const item = control.dataset.item;
    const qty = parseFloat(control.dataset.qty) || 0;
    const step = parseFloat(control.dataset.step) || 1;
    const min = parseFloat(control.dataset.min) || step;
    const next = round(qty + direction * step);
    busy.add(control);
    const request = next < min - 1e-9
      ? post(options.removeUrl, { item_id: item })
      : post(options.updateUrl, { ['qty[' + item + ']']: format(next) });

    request.then((data) => apply(data, control, 'change'))
      .catch(() => showError(control, text('COM_DHARMASHOP_CART_ERROR_GENERIC', 'Error')))
      .finally(() => busy.delete(control));
  };

  // --- Кнопка «В корзину» --------------------------------------------------------------------------------------------------------

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
    if (!options.url || busy.has(button)) return;
    const scope = button.closest('.dharmaCardActions') || button.parentElement;
    const qtyInput = scope ? scope.querySelector('[data-dharmashop-qty] input') : null;
    const params = { product_id: button.dataset.dharmashopAddToCart || '', qty: qtyInput && qtyInput.value ? qtyInput.value : '1' };
    if (button.dataset.variantId) params.variant_id = button.dataset.variantId;
    busy.add(button);
    button.setAttribute('aria-busy', 'true');

    post(options.url, params)
      .then((data) => {
        const control = button.closest('[data-dharmashop-cart-control]');
        apply(data, button, 'add');
        // Кнопка без виджета: короткое подтверждение «Добавлено»; с виджетом подтверждением служит сам виджет.
        if (data.success && !control) flash(button);
      })
      .catch(() => showError(button, text('COM_DHARMASHOP_CART_ERROR_GENERIC', 'Error')))
      .finally(() => {
        busy.delete(button);
        button.removeAttribute('aria-busy');
      });
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-dharmashop-add-to-cart]');
    const plus = event.target.closest('[data-dharmashop-stepper-plus]');
    const minus = event.target.closest('[data-dharmashop-stepper-minus]');

    if (button) {
      event.preventDefault();
      add(button);
    } else if (plus || minus) {
      event.preventDefault();
      stepperAction((plus || minus).closest('[data-dharmashop-cart-control]'), plus ? 1 : -1);
    }
  });

  // Другие скрипты (мини-корзина) меняют корзину и сообщают об этом событием с картой товаров.
  document.addEventListener('dharmashop:cart-updated', (event) => {
    if (event.detail && event.detail.products) sync(event.detail.products);
  });
})();
