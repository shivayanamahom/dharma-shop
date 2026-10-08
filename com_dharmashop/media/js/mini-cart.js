/**
 * Dharma Shop: мини-корзина в боковой панели (offcanvas Bootstrap) модуля корзины. Подключается модулем только в режиме
 * «открывать боковую панель». Содержимое панели — фрагмент `view=cart&layout=mini&format=raw`, подгружается при открытии и после
 * каждого изменения корзины. Действия в панели (количество «−»/«+», удаление) — POST с токеном на cart.update / cart.remove (JSON).
 * Событие `dharmashop:cart-updated` (его шлёт cart.js после добавления товара и этот скрипт после своих действий) обновляет значок
 * модуля и, если включено «открывать после добавления», открывает панель.
 */
(() => {
  'use strict';

  const options = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_dharmashop.minicart')) || {};
  const panel = document.querySelector('[data-dharmashop-minicart]');
  if (!panel || !options.fragmentUrl) return;
  const body = panel.querySelector('[data-dharmashop-minicart-body]');

  // Панель лежит в шапке сайта, а у шапки свой контекст наложения (z-index): внутри него панель оказывается под другими блоками.
  // Как и штатные модальные окна Bootstrap, переносим её в body.
  if (panel.parentElement !== document.body) document.body.appendChild(panel);
  let shown = false;
  let stale = true;
  let controller = null;

  const request = (url, params) => {
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
      element.textContent = cart.subtotalText;
    });
  };

  const load = () => {
    if (controller) controller.abort();
    controller = new AbortController();
    body.setAttribute('aria-busy', 'true');

    return fetch(options.fragmentUrl, { credentials: 'same-origin', signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((response) => {
        if (!response.ok) throw new Error(String(response.status));
        return response.text();
      })
      .then((html) => {
        body.innerHTML = html;
        stale = false;
      })
      .catch((error) => {
        if (error.name !== 'AbortError') body.textContent = options.error || '';
      })
      .finally(() => body.removeAttribute('aria-busy'));
  };

  const decimals = (step) => (String(step).split('.')[1] || '').length;
  const clamp = (input, value) => {
    const step = parseFloat(input.dataset.step) || 1;
    const min = parseFloat(input.dataset.min) || step;
    const number = Number.isFinite(value) ? value : min;

    return Math.max(min, min + Math.round((number - min) / step) * step).toFixed(decimals(step));
  };

  // Ответ на действие: значок модуля, сообщение об ошибке над списком, перезагрузка фрагмента (в нём актуальные количества и суммы).
  const done = (data) => {
    if (data.cart) {
      indicators(data.cart);
      document.dispatchEvent(new CustomEvent('dharmashop:cart-updated', { detail: data.cart, bubbles: true }));
    }
    return load().then(() => {
      if (!data.success && data.message) {
        const note = document.createElement('div');
        note.className = 'alert alert-warning py-2 small';
        note.setAttribute('role', 'alert');
        note.textContent = data.message;
        body.prepend(note);
      }
    });
  };

  const changeQty = (input, value) => request(options.updateUrl, { ['qty[' + input.dataset.dharmashopMinicartQty + ']']: clamp(input, value) }).then(done);

  body.addEventListener('click', (event) => {
    const step = event.target.closest('[data-dharmashop-minicart-step]');
    const remove = event.target.closest('[data-dharmashop-minicart-remove]');

    if (step) {
      const input = step.closest('[data-dharmashop-minicart-stepper]').querySelector('[data-dharmashop-minicart-qty]');
      const direction = step.dataset.dharmashopMinicartStep === 'up' ? 1 : -1;
      changeQty(input, (parseFloat(String(input.value).replace(',', '.')) || 0) + direction * (parseFloat(input.dataset.step) || 1));
    } else if (remove) {
      request(options.removeUrl, { item_id: remove.dataset.dharmashopMinicartRemove }).then(done);
    }
  });

  body.addEventListener('change', (event) => {
    const input = event.target.closest('[data-dharmashop-minicart-qty]');
    if (input) changeQty(input, parseFloat(String(input.value).replace(',', '.')));
  });

  panel.addEventListener('show.bs.offcanvas', () => {
    shown = true;
    if (stale) load();
  });
  panel.addEventListener('hidden.bs.offcanvas', () => {
    shown = false;
  });

  document.addEventListener('dharmashop:cart-updated', (event) => {
    if (event.detail) indicators(event.detail);
    stale = true;

    if (shown) {
      load();
    } else if (panel.dataset.openOnAdd === '1' && event.detail && event.detail.source === 'add' && window.bootstrap && bootstrap.Offcanvas) {
      bootstrap.Offcanvas.getOrCreateInstance(panel).show();
    }
  });
})();
