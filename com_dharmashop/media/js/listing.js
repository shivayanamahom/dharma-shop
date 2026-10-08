/**
 * Dharma Shop: список товаров категории. Сортировка и вид подгружаются без перезагрузки и без смены адреса
 * (адрес страницы остаётся каноническим — для поисковиков); без JavaScript работают обычные формы и ссылки.
 * Фрагмент приходит с того же адреса страницы с параметрами format=raw, sort, product_view.
 * Страницы пагинации — настоящие адреса (`?start=N`): ссылки открываются переходом, сортировка и вид, выбранные
 * на странице, добавляются к адресу страницы. Если открыта страница пагинации, смена сортировки или вида тоже идёт переходом.
 * Переход выполняется событием `dharmashop:navigate` (отменяемое, detail.url): по умолчанию — обычная загрузка адреса;
 * скрипт AJAX-пагинации (pagination.js, настройка магазина) отменяет событие и подгружает страницу сам.
 * После замены страницы приходит событие `dharmashop:updated`, форма подключается заново.
 */
(() => {
  'use strict';

  const initials = new WeakMap();
  const currentForm = () => document.querySelector('[data-dharmashop-listing-controls]');
  const stateOf = (form) => {
    const data = new FormData(form);
    return { sort: String(data.get('sort') || ''), view: String(data.get('product_view') || '') };
  };
  // Адрес со страницей или с сортировкой/видом уже не канонический: любое изменение — переход на адрес, а не подмена содержимого.
  const onPaginationPage = () => /[?&](start|limitstart|sort|product_view)=/.test(window.location.search);
  const navigate = (url) => {
    const event = new CustomEvent('dharmashop:navigate', { cancelable: true, detail: { url: String(url) } });
    if (document.dispatchEvent(event)) window.location.assign(String(url));
  };
  // Адрес первой страницы с выбранными сортировкой и видом: то, что отправила бы форма без JavaScript.
  const formUrl = (form) => {
    const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    const current = stateOf(form);
    url.search = '';
    // Поисковый запрос страницы (?q=) остаётся: сортировка и вид меняют порядок найденных товаров, а не сбрасывают поиск.
    const query = new URLSearchParams(window.location.search).get('q');
    if (query) url.searchParams.set('q', query);
    if (current.sort) url.searchParams.set('sort', current.sort);
    if (current.view) url.searchParams.set('product_view', current.view);
    return url;
  };

  const initialise = (form) => {
    if (form.dataset.dharmashopListingReady) return;
    form.dataset.dharmashopListingReady = '1';
    initials.set(form, stateOf(form));
    const container = document.querySelector('[data-dharmashop-listing]');
    if (!container) return;
    const submit = form.querySelector('[data-dharmashop-listing-submit]');
    if (submit) submit.hidden = true;
    let controller = null;

    const load = () => {
      if (controller) controller.abort();
      controller = new AbortController();
      const url = formUrl(form);
      url.searchParams.set('format', 'raw');
      container.setAttribute('aria-busy', 'true');
      container.classList.add('opacity-50');

      fetch(url.toString(), { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => {
          if (!response.ok) throw new Error(String(response.status));
          return response.text();
        })
        .then((html) => {
          container.innerHTML = html;
          document.dispatchEvent(new CustomEvent('dharmashop:updated'));
          if (container.getBoundingClientRect().top < 0) {
            container.scrollIntoView({ block: 'start' });
          }
        })
        .catch((error) => {
          if (error.name === 'AbortError') return;
          // Не получилось подгрузить: обычная отправка формы (страница с параметрами).
          form.submit();
        })
        .finally(() => {
          container.removeAttribute('aria-busy');
          container.classList.remove('opacity-50');
        });
    };
    // На странице пагинации или с параметрами в адресе новая сортировка — переход на адрес первой страницы с ней.
    const apply = () => (onPaginationPage() ? navigate(formUrl(form)) : load());

    form.addEventListener('change', (event) => {
      if (event.target.matches('[name="sort"], [name="product_view"]')) apply();
    });
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      apply();
    });
  };

  // Штатная постраничная навигация Joomla: переход на адрес страницы; сортировка и вид из формы добавляются, если они не по умолчанию.
  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-dharmashop-listing] .pagination a[href]');
    if (!link || link.getAttribute('href') === '#' || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
    event.preventDefault();
    const url = new URL(link.href, window.location.href);
    const form = currentForm();
    url.searchParams.delete('sort');
    url.searchParams.delete('product_view');

    if (form) {
      const current = stateOf(form);
      const initial = initials.get(form) || current;
      const fromUrl = new URLSearchParams(window.location.search);
      if (current.sort && (current.sort !== initial.sort || fromUrl.has('sort'))) url.searchParams.set('sort', current.sort);
      if (current.view && (current.view !== initial.view || fromUrl.has('product_view'))) url.searchParams.set('product_view', current.view);
    }

    navigate(url);
  });

  const boot = () => document.querySelectorAll('[data-dharmashop-listing-controls]').forEach(initialise);

  document.addEventListener('dharmashop:updated', boot);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
