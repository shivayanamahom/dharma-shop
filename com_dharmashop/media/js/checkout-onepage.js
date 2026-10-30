/**
 * Dharma Shop: оформление «в один шаг» (C100). Состав корзины вверху страницы меняет cart-page.js; после каждого изменения
 * (событие `dharmashop:cart-updated`) форма заказа и общая боковая панель итогов подгружаются с сервера заново: цена и
 * доступность доставки и оплаты, итоги и прогресс скидки зависят от состава корзины. Введённые покупателем значения возвращаются
 * в новую форму по именам полей; затем шлётся событие `dharmashop:checkout-replaced` (detail.form — новая форма): на него заново
 * привязываются checkout.js и скрипты способов доставки. Сервер при отправке всё равно проверяет выбранные способы и суммы.
 */
(() => {
  'use strict';

  const options = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_dharmashop.onepage')) || {};
  const scope = document.querySelector('[data-dharmashop-checkout-scope]');
  const holder = document.querySelector('[data-dharmashop-onepage-form]');
  const aside = document.querySelector('[data-dharmashop-onepage-aside]');
  if (!scope || !holder || !aside || !options.formUrl) return;

  const DELAY = 350;
  let timer = null;
  let controller = null;

  // Поля, которые сервер выдаёт заново при каждой отрисовке: токен формы, задача, ключ отправки и ловушка для ботов.
  const skipped = (name) => !name || /^(task|checkout_key|website|promotion_action)$/.test(name) || /^[a-f0-9]{32}$/.test(name);

  const snapshot = (root) => Array.from(root.querySelectorAll('input, select, textarea'))
    .filter((field) => !field.closest('[data-dharmashop-cart-page]') && !skipped(field.name) && !['submit', 'button', 'file'].includes(field.type)
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

  // Картинки (логотипы способов доставки, фото) переносятся из старых узлов в новую разметку с тем же адресом: иначе браузер
  // создаёт их заново и они мигают при каждом изменении корзины.
  const keepImages = (fresh, current) => {
    const pool = Array.from(current.querySelectorAll('img'));
    fresh.querySelectorAll('img').forEach((img) => {
      const index = pool.findIndex((old) => old.getAttribute('src') === img.getAttribute('src') && old.className === img.className);
      if (index < 0) return;
      img.replaceWith(pool.splice(index, 1)[0]);
    });
  };

  const busy = (state) => {
    [holder, aside].forEach((block) => {
      block.setAttribute('aria-busy', state ? 'true' : 'false');
      block.classList.toggle('opacity-75', state);
    });
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
        const parts = document.createElement('div');
        parts.innerHTML = html;
        const saved = snapshot(scope);
        // Липкая карточка итога: прежний отступ сразу переходит к новой, иначе она на кадр прыгает под шапку.
        const stickyTop = aside.querySelector('.dharmaCheckoutTotal')?.style.top || '';
        const main = parts.querySelector('[data-onepage-part="main"]');
        const side = parts.querySelector('[data-onepage-part="aside"]');
        if (main) keepImages(main, holder);
        if (side) keepImages(side, aside);
        holder.replaceChildren(...(main ? main.childNodes : []));
        aside.replaceChildren(...(side ? side.childNodes : []));
        const card = aside.querySelector('.dharmaCheckoutTotal');
        if (card && stickyTop) card.style.top = stickyTop;
        restore(scope, saved);
        document.dispatchEvent(new CustomEvent('dharmashop:checkout-replaced', { detail: { form: holder.querySelector('[data-dharmashop-checkout-form]') } }));
      })
      .catch((error) => {
        // Сбой сети не ломает страницу: прежняя форма остаётся, а итоги при отправке всё равно считает сервер.
        if (error.name !== 'AbortError') console.warn('Dharma Shop: не удалось обновить оформление', error);
      })
      .finally(() => busy(false));
  };

  // Липкий итог: карточка с итогом и кнопкой стоит под деталями панели, а при прокрутке залипает сверху под шапкой сайта. Отступ равен
  // нижней границе липкой шапки (числовое значение задаёт скрипт, как у липкого фильтра); без него карточка ушла бы под шапку.
  const desktop = window.matchMedia('(min-width: 992px)');
  let fitTimer = 0;

  const headerBottom = (card) => {
    let bottom = 0;
    [0.25, 0.5, 0.75].forEach((share) => {
      document.elementsFromPoint(Math.round(window.innerWidth * share), 1).forEach((node) => {
        if (card.contains(node) || node.contains(card) || node === document.documentElement || node === document.body) return;
        const position = window.getComputedStyle(node).position;
        if (position === 'fixed' || position === 'sticky') bottom = Math.max(bottom, node.getBoundingClientRect().bottom);
      });
    });

    return Math.max(0, Math.min(300, Math.round(bottom)));
  };

  const fit = () => {
    fitTimer = 0;
    const card = aside.querySelector('.dharmaCheckoutTotal');
    if (card) card.style.top = desktop.matches ? headerBottom(card) + 8 + 'px' : '';
  };

  // Таймер, а не requestAnimationFrame: в неактивной вкладке кадры не идут, а отступ нужен сразу.
  const schedule = () => {
    if (!fitTimer) fitTimer = window.setTimeout(fit, 16);
  };

  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule);
  desktop.addEventListener('change', schedule);
  document.addEventListener('dharmashop:checkout-replaced', schedule);
  schedule();

  document.addEventListener('dharmashop:cart-updated', () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(refresh, DELAY);
  });
})();
