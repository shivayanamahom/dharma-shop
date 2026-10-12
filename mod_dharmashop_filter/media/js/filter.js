/**
 * Dharma Shop: фильтр товаров. Подключается только при включённых настройках модуля «AJAX» или «Применять сразу».
 * Разметку выводит сервер: скрипт отправляет состояние формы на адрес страницы, из ответа берёт готовый список товаров и новую форму
 * фильтра (счётчики, чипы, «Найдено») и подменяет их; адрес меняется через history.pushState. Любая ошибка — обычный переход по адресу.
 * Настройки формы читаются из data-атрибутов: data-ajax="1", data-apply="instant|button", data-delay="мс" (ввод чисел).
 * После замены приходит событие `dharmashop:updated` (как у списка товаров).
 */
(() => {
  'use strict';

  const FORM = 'form[data-dharmashop-filter]';
  const LIST = '[data-dharmashop-listing]';
  let controller = null;
  let timer = 0;
  let pushed = false;

  const formOf = (node) => (node && node.closest ? node.closest(FORM) : null);
  const isAjax = (form) => form.dataset.ajax === '1';
  const isInstant = (form) => form.dataset.apply === 'instant';

  // Адрес страницы с состоянием формы: пустые значения не передаются, страница пагинации сбрасывается.
  const buildUrl = (form) => {
    const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    const params = new URLSearchParams();
    url.searchParams.forEach((value, key) => {
      if (!key.startsWith('filter[') && key !== 'start' && key !== 'limitstart') params.append(key, value);
    });
    new FormData(form).forEach((value, key) => {
      if (typeof value === 'string' && value !== '' && key !== 'start' && key !== 'limitstart') params.append(key, value);
    });
    if (!Array.from(params).some(([key, value]) => key.startsWith('filter[') && value !== '')) params.delete('filter_module');
    url.search = params.toString();

    return url.toString();
  };

  const snapshot = (form) => JSON.stringify(Array.from(new FormData(form).entries()).map(([key, value]) => [key, typeof value === 'string' ? value : '']));

  const fallback = (url) => window.location.assign(url);

  const load = async (url, push) => {
    const box = document.querySelector(LIST);
    if (!box) return fallback(url);

    if (controller) controller.abort();
    controller = new AbortController();
    const mine = controller;
    const before = new Map(Array.from(document.querySelectorAll(FORM)).map((form) => [form.id, snapshot(form)]));
    box.setAttribute('aria-busy', 'true');
    box.classList.add('opacity-50');

    try {
      const response = await fetch(url, { signal: mine.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!response.ok) throw new Error(String(response.status));
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const next = doc.querySelector(LIST);
      if (!next) throw new Error('no listing');

      // Администратор мог сменить позицию/макет уже после открытия страницы.
      // Подмена одной формы в старом контейнере оставила бы вертикальный фильтр в прежней верхней позиции.
      for (const old of document.querySelectorAll(FORM)) {
        const fresh = doc.getElementById(old.id);
        if (fresh && old.dataset.configuration !== fresh.dataset.configuration) return fallback(response.url || url);
      }

      const active = document.activeElement;
      const focus = active && formOf(active) && active.name ? { form: formOf(active).id, name: active.name, value: active.value, type: active.type } : null;

      box.innerHTML = next.innerHTML;
      const oldControls=document.querySelector('[data-dharmashop-listing-controls]');
      const nextControls=doc.querySelector('[data-dharmashop-listing-controls]');
      if(oldControls && nextControls)oldControls.replaceWith(nextControls);

      document.querySelectorAll(FORM).forEach((old) => {
        const fresh = old.id ? doc.getElementById(old.id) : null;
        // Если посетитель уже изменил форму, пока шёл запрос, её не заменяем: следующий запрос принесёт актуальную.
        if (!fresh || snapshot(old) !== before.get(old.id)) return;
        // Раскрытые и свёрнутые группы (Bootstrap collapse) остаются как были.
        const opened = new Map(Array.from(old.querySelectorAll('.accordion-collapse')).map((c) => [c.id, c.classList.contains('show')]));
        fresh.querySelectorAll('.accordion-collapse').forEach((c) => {
          if (!opened.has(c.id)) return;
          const show = opened.get(c.id);
          c.classList.toggle('show', show);
          const button = fresh.querySelector('[data-bs-target="#' + c.id + '"]');
          if (button) {
            button.classList.toggle('collapsed', !show);
            button.setAttribute('aria-expanded', show ? 'true' : 'false');
          }
        });
        old.replaceWith(fresh);
        if (focus && focus.form === fresh.id) {
          const candidates = Array.from(fresh.querySelectorAll('[name]')).filter((el) => el.name === focus.name);
          const target = candidates.find((el) => el.value === focus.value) || candidates[0];
          if (target) {
            target.focus({ preventScroll: true });
            if (focus.type === 'number' || focus.type === 'text') {
              try { target.setSelectionRange(target.value.length, target.value.length); } catch (e) { /* поле без выделения */ }
            }
          }
        }
      });

      // Кнопка открытия выдвижной панели (вне формы): число выбранных условий.
      document.querySelectorAll('.dharmaFilterToggle').forEach((toggle) => {
        const fresh = doc.querySelector('.dharmaFilterToggle[data-bs-target="' + toggle.dataset.bsTarget + '"]');
        if (fresh) toggle.innerHTML = fresh.innerHTML;
      });

      if (doc.title) document.title = doc.title;
      ['link[rel="canonical"]', 'meta[name="robots"]'].forEach((selector) => {
        const old=document.head.querySelector(selector), fresh=doc.head.querySelector(selector);
        if(fresh){if(old)old.replaceWith(fresh);else document.head.append(fresh);}else if(old){old.remove();}
      });
      if (push) {
        history.pushState({ dharmashopFilter: 1 }, '', response.url || url);
        pushed = true;
      }
      document.dispatchEvent(new CustomEvent('dharmashop:updated'));
      // При выборе и сбросе оставляем посетителя у фильтра; список не прокручивает страницу принудительно.
    } catch (error) {
      if (error.name === 'AbortError') return;
      fallback(url);
    } finally {
      if (controller === mine) {
        box.removeAttribute('aria-busy');
        box.classList.remove('opacity-50');
      }
    }
  };

  const apply = (form) => {
    window.clearTimeout(timer);
    if (isAjax(form)) {
      load(buildUrl(form), true);
    } else if (isInstant(form)) {
      form.requestSubmit();
    }
  };

  const schedule = (form, delay) => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => apply(form), delay);
  };

  const isText = (el) => el.matches('input[type="number"], input[type="text"], input[type="search"]');

  document.addEventListener('change', (event) => {
    const form = formOf(event.target);
    if (form && isInstant(form) && !event.target.matches('[data-filter-search]')) schedule(form, isText(event.target) ? 0 : 120);
  });

  document.addEventListener('input', (event) => {
    const form = formOf(event.target);
    if (form && isInstant(form) && isText(event.target)) schedule(form, Math.max(200, Number(form.dataset.delay) || 700));
  });

  document.addEventListener('submit', (event) => {
    const form = formOf(event.target);
    if (!form || !isAjax(form)) return;
    event.preventDefault();
    apply(form);
  });

  // Чипы, «Сбросить» и быстрые интервалы цены — обычные ссылки; при AJAX они тоже меняют список без перезагрузки.
  document.addEventListener('click', (event) => {
    const link = event.target.closest ? event.target.closest('a[href]') : null;
    const form = link ? formOf(link) : null;
    if (!link || !form || !isAjax(form) || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    load(link.href, true);
  });

  window.addEventListener('popstate', () => {
    if (pushed || (history.state && history.state.dharmashopFilter)) load(window.location.href, false);
  });
})();
