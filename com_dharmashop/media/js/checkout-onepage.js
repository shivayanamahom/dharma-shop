/**
 * Dharma Shop: оформление «в один шаг» (C100). Состав корзины вверху страницы меняет cart-page.js; после каждого изменения
 * (событие `dharmashop:cart-updated`) нижняя часть — форма заказа с боковой панелью — подгружается с сервера заново: цена и
 * доступность доставки и оплаты, итоги и прогресс скидки зависят от состава корзины. Введённые покупателем значения возвращаются
 * в новую форму по именам полей; затем шлётся событие `dharmashop:checkout-replaced` (detail.form — новая форма): на него заново
 * привязываются checkout.js и скрипты способов доставки. Сервер при отправке всё равно проверяет выбранные способы и суммы.
 */
(() => {
  'use strict';

  const options = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_dharmashop.onepage')) || {};
  const holder = document.querySelector('[data-dharmashop-onepage-form]');
  if (!holder || !options.formUrl) return;

  const DELAY = 350;
  let timer = null;
  let controller = null;

  // Поля, которые сервер выдаёт заново при каждой отрисовке: токен формы, задача, ключ отправки и ловушка для ботов.
  const skipped = (name) => !name || /^(task|checkout_key|website|promotion_action)$/.test(name) || /^[a-f0-9]{32}$/.test(name);

  const snapshot = (root) => Array.from(root.querySelectorAll('input, select, textarea'))
    .filter((field) => !skipped(field.name) && !['submit', 'button', 'file'].includes(field.type)
      // Скрытые поля сервер выдаёт сам; возвращаем только принадлежащие способам доставки и оплаты (расчёт по адресу и т. п.).
      && (field.type !== 'hidden' || /^(shipping|payment)\[/.test(field.name)))
    .map((field) => ({
      name: field.name,
      type: field.type,
      value: field.value,
      checked: field.checked,
      disabled: field.disabled,
    }));

  const restore = (root, fields) => {
    fields.forEach((saved) => {
      const matches = Array.from(root.querySelectorAll('input, select, textarea')).filter((field) => field.name === saved.name);
      if (!matches.length) return;

      if (saved.type === 'radio' || saved.type === 'checkbox') {
        const target = matches.find((field) => field.value === saved.value);
        // Выбор возвращаем только там, где такой вариант ещё есть и доступен: исчезнувший способ сервер заменил выбором по умолчанию.
        if (target && !target.disabled) target.checked = saved.checked;
      } else if (saved.type !== 'hidden' || saved.value !== '') {
        // Скрытое поле способа возвращаем только непустым: пустое значение — состояние «ещё не считали».
        matches[0].value = saved.value;
      }
    });
  };

  const busy = (state) => {
    holder.setAttribute('aria-busy', state ? 'true' : 'false');
    holder.classList.toggle('opacity-75', state);
  };

  const refresh = () => {
    timer = null;
    const form = holder.querySelector('[data-dharmashop-checkout-form]');
    // Идёт отправка заказа: форму не трогаем.
    if (form && form.getAttribute('aria-busy') === 'true') return;

    if (controller) controller.abort();
    controller = new AbortController();
    busy(true);

    fetch(options.formUrl, { credentials: 'same-origin', signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((response) => {
        if (!response.ok) throw new Error(String(response.status));
        return response.text();
      })
      .then((html) => {
        const saved = form ? snapshot(form) : [];
        holder.innerHTML = html;
        restore(holder, saved);
        document.dispatchEvent(new CustomEvent('dharmashop:checkout-replaced', { detail: { form: holder.querySelector('[data-dharmashop-checkout-form]') } }));
      })
      .catch((error) => {
        // Сбой сети не ломает страницу: прежняя форма остаётся, а итоги при отправке всё равно считает сервер.
        if (error.name !== 'AbortError') console.warn('Dharma Shop: не удалось обновить оформление', error);
      })
      .finally(() => busy(false));
  };

  document.addEventListener('dharmashop:cart-updated', () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(refresh, DELAY);
  });
})();
