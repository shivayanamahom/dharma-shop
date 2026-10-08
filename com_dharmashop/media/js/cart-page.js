/**
 * Dharma Shop: страница корзины без перезагрузки. Количество («−»/«+» и поле), удаление позиции и очистка — запросы POST с токеном
 * на cart.update / cart.remove / cart.clear (JSON), затем содержимое страницы подгружается заново фрагментом `layout=page&format=raw`
 * (в нём актуальные суммы, ошибки и количества). После каждого действия шлётся событие `dharmashop:cart-updated` (source = «page»):
 * значок и боковая панель модуля корзины обновляются. Изменения из модуля (мини-корзина) страница тоже подхватывает по этому событию.
 */
(() => {
  'use strict';

  const options = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_dharmashop.cartpage')) || {};
  const page = document.querySelector('[data-dharmashop-cart-page]');
  const messages = document.querySelector('[data-dharmashop-cart-messages]');
  if (!page || !options.fragmentUrl) return;

  let queue = Promise.resolve();
  let controller = null;
  let pending = 0;

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

  // Значок модуля корзины (число позиций и сумма) — и без боковой панели.
  const indicators = (cart) => {
    document.querySelectorAll('[data-dharmashop-cart-count]').forEach((element) => {
      element.textContent = String(cart.count);
      element.classList.toggle('d-none', cart.count === 0 && element.hasAttribute('data-hide-empty'));
    });
    document.querySelectorAll('[data-dharmashop-cart-total]').forEach((element) => {
      element.textContent = cart.subtotalText;
    });
  };

  const notify = (text, type) => {
    if (!messages) return;
    messages.textContent = '';
    if (!text) return;
    const note = document.createElement('div');
    note.className = 'alert alert-' + type + ' py-2';
    note.setAttribute('role', 'alert');
    note.textContent = text;
    messages.append(note);
  };

  const busy = (state) => {
    page.setAttribute('aria-busy', state ? 'true' : 'false');
    page.classList.toggle('opacity-75', state);
  };

  // Содержимое страницы целиком; фокус возвращается на поле количества той же позиции.
  const load = () => {
    if (controller) controller.abort();
    controller = new AbortController();
    const focused = document.activeElement && document.activeElement.closest('[data-line]');
    const focusId = focused && page.contains(focused) ? focused.dataset.line : null;

    return fetch(options.fragmentUrl, { credentials: 'same-origin', signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((response) => {
        if (!response.ok) throw new Error(String(response.status));
        return response.text();
      })
      .then((html) => {
        page.innerHTML = html;
        if (focusId) {
          const input = page.querySelector('[data-dharmashop-cartpage-qty="' + focusId + '"]');
          if (input) input.focus();
        }
      })
      .catch((error) => {
        if (error.name !== 'AbortError') notify(options.error || '', 'danger');
      });
  };

  // Действие в очередь: запросы идут по одному, перезагрузка содержимого — после последнего.
  const act = (url, params) => {
    pending += 1;
    busy(true);
    queue = queue
      .then(() => request(url, params))
      .then((data) => {
        notify(data.success ? '' : data.message || options.error, 'warning');
        if (data.cart) {
          indicators(data.cart);
          document.dispatchEvent(new CustomEvent('dharmashop:cart-updated', { detail: Object.assign({}, data.cart, { source: 'page' }) }));
        }
      })
      .catch(() => notify(options.error || '', 'danger'))
      .then(() => {
        pending -= 1;
        return pending === 0 ? load() : null;
      })
      .finally(() => {
        if (pending === 0) busy(false);
      });

    return queue;
  };

  const decimals = (step) => (String(step).split('.')[1] || '').length;
  const clamp = (input, value) => {
    const step = parseFloat(input.dataset.step) || 1;
    const min = parseFloat(input.dataset.min) || step;
    const number = Number.isFinite(value) ? value : min;

    return Math.max(min, min + Math.round((number - min) / step) * step).toFixed(decimals(step));
  };
  const parse = (input) => parseFloat(String(input.value).replace(',', '.'));
  const setQty = (input, value) => act(options.updateUrl, { ['qty[' + input.dataset.dharmashopCartpageQty + ']']: clamp(input, value) });

  page.addEventListener('click', (event) => {
    const step = event.target.closest('[data-dharmashop-cartpage-step]');
    const remove = event.target.closest('[data-dharmashop-cartpage-remove]');
    const clear = event.target.closest('[data-dharmashop-cartpage-clear]');

    if (step) {
      const input = step.closest('[data-dharmashop-cartpage-stepper]').querySelector('[data-dharmashop-cartpage-qty]');
      const direction = step.dataset.dharmashopCartpageStep === 'up' ? 1 : -1;
      input.value = clamp(input, (parse(input) || 0) + direction * (parseFloat(input.dataset.step) || 1));
      setQty(input, parse(input));
    } else if (remove) {
      event.preventDefault();
      act(options.removeUrl, { item_id: remove.dataset.dharmashopCartpageRemove });
    } else if (clear) {
      event.preventDefault();
      act(options.clearUrl, {});
    }
  });

  page.addEventListener('change', (event) => {
    const input = event.target.closest('[data-dharmashop-cartpage-qty]');
    if (input) setQty(input, parse(input));
  });

  // Enter в поле количества — применить, а не отправлять форму целиком.
  page.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && event.target.closest('[data-dharmashop-cartpage-qty]')) {
      event.preventDefault();
      setQty(event.target, parse(event.target));
    }
  });

  // Корзина изменена в другом месте (мини-корзина модуля): обновляем страницу.
  document.addEventListener('dharmashop:cart-updated', (event) => {
    if (!event.detail || event.detail.source !== 'page') load();
  });
})();
