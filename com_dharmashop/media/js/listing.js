/**
 * Вид карточек меняется фрагментом на текущей странице, без параметров оформления в адресе.
 * Выбор вида хранится в sessionStorage вкладки; GET product_view остаётся совместимым с прямыми ссылками и формой без JS.
 * Сортировка начинает выдачу с первой страницы. Пагинация использует dharmashop:navigate;
 * pagination.js может перехватить переход. more/infinite сохраняют загруженные порции при смене вида.
 */
(() => {
  'use strict';

  const VIEW_KEY = 'dharmashop.productView';
  const currentForm = () => document.querySelector('[data-dharmashop-listing-controls]');
  const stateOf = (form) => {
    const data = new FormData(form);
    return { sort: String(data.get('sort') || ''), view: String(data.get('product_view') || '') };
  };
  const rememberView = (view) => { try { sessionStorage.setItem(VIEW_KEY, view); } catch (_) { /* Недоступное хранилище не мешает переключению. */ } };
  const savedView = () => { try { return sessionStorage.getItem(VIEW_KEY); } catch (_) { return null; } };
  const pageStart = () => {
    const query = new URLSearchParams(window.location.search);
    return Math.max(0, Number(query.get('start') || query.get('limitstart') || 0));
  };
  const cleanUrl = (url, form) => {
    url.searchParams.delete('product_view');
    url.searchParams.delete('format');
    if (url.searchParams.get('sort') === form?.dataset.defaultSort) url.searchParams.delete('sort');
    return url;
  };
  const navigate = (url) => {
    const event = new CustomEvent('dharmashop:navigate', { cancelable: true, detail: { url: String(url) } });
    if (document.dispatchEvent(event)) window.location.assign(String(url));
  };
  const formUrl = (form, start = 0) => {
    const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    const current = stateOf(form);
    url.search = '';
    new URLSearchParams(window.location.search).forEach((value, key) => {
      if (key.startsWith('filter[') || key === 'filter_module') url.searchParams.append(key, value);
    });
    if (start > 0) url.searchParams.set('start', String(start));
    if (current.sort) url.searchParams.set('sort', current.sort);
    if (current.view) url.searchParams.set('product_view', current.view);
    return url;
  };
  const itemsRoot = (scope) => [...scope.children].find((el) => el.matches('.row'))
    || scope.querySelector('.table-responsive tbody');

  const initialise = (form) => {
    if (form.dataset.dharmashopListingReady) return;
    form.dataset.dharmashopListingReady = '1';
    const container = document.querySelector('[data-dharmashop-listing]');
    if (!container) return;
    const submit = form.querySelector('[data-dharmashop-listing-submit]');
    if (submit) submit.hidden = true;
    let controller = null;

    const load = async (preservePages) => {
      if (controller) controller.abort();
      const mine = controller = new AbortController();
      const pages = preservePages ? JSON.parse(container.dataset.dharmashopLoadedPages || JSON.stringify([pageStart()])) : [0];
      container.setAttribute('aria-busy', 'true');
      container.classList.add('opacity-50');
      const fragment = async (start) => {
        const url = formUrl(form, start);
        url.searchParams.set('format', 'raw');
        const response = await fetch(url.toString(), { signal: mine.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error(String(response.status));
        const template = document.createElement('template');
        template.innerHTML = await response.text();
        return template.content;
      };
      try {
        const combined = await fragment(pages[0]);
        const root = itemsRoot(combined);
        // Не более четырёх запросов одновременно, даже если посетитель догрузил много страниц.
        for (let i = 1; i < pages.length; i += 4) {
          const chunks = await Promise.all(pages.slice(i, i + 4).map(fragment));
          for (const chunk of chunks) {
            const incoming = itemsRoot(chunk);
            if (!root || !incoming) throw new Error('no items');
            root.append(...incoming.children);
            const pagination = chunk.querySelector('[data-dharmashop-pagination]');
            const current = combined.querySelector('[data-dharmashop-pagination]');
            if (current && pagination) current.replaceWith(pagination);
          }
        }
        if (mine.signal.aborted || !container.isConnected) return;
        container.replaceChildren(combined);
        container.dataset.dharmashopLoadedPages = JSON.stringify(pages);
        document.dispatchEvent(new CustomEvent('dharmashop:updated'));
      } catch (error) {
        if (error.name === 'AbortError') return;
        // Резервный переход сохраняет смещение; обычная GET-форма работает и без JS.
        window.location.assign(formUrl(form, preservePages ? pages[0] : 0).toString());
      } finally {
        if (controller === mine) {
          container.removeAttribute('aria-busy');
          container.classList.remove('opacity-50');
        }
      }
    };
    const changeView = () => {
      rememberView(stateOf(form).view);
      const url = cleanUrl(new URL(window.location.href), form);
      if (url.toString() !== window.location.href) window.history.replaceState(window.history.state, '', url);
      load(true);
    };
    const changeSort = () => {
      rememberView(stateOf(form).view);
      if (pageStart() || new URLSearchParams(window.location.search).has('sort')) navigate(cleanUrl(formUrl(form), form));
      else load(false);
    };
    form.addEventListener('change', (event) => {
      if (event.target.matches('[name="product_view"]')) changeView();
      else if (event.target.matches('[name="sort"]')) changeSort();
    });
    form.addEventListener('submit', (event) => { event.preventDefault(); changeSort(); });

    const explicit = new URLSearchParams(window.location.search).get('product_view');
    const preferred = explicit || savedView();
    if (['grid', 'list', 'table'].includes(preferred)) {
      if (explicit) rememberView(preferred);
      if (preferred !== stateOf(form).view) {
        const select = form.querySelector('select[name="product_view"]');
        const radio = form.querySelector('input[name="product_view"][value="' + preferred + '"]');
        if (select) select.value = preferred;
        else if (radio) radio.checked = true;
        load(true);
      }
    }
  };

  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-dharmashop-listing] .pagination a[href]');
    if (!link || link.getAttribute('href') === '#' || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
    event.preventDefault();
    const form = currentForm();
    const url = cleanUrl(new URL(link.href, window.location.href), form);
    if (form) {
      const current = stateOf(form);
      rememberView(current.view);
      url.searchParams.delete('sort');
      if (current.sort && current.sort !== form.dataset.defaultSort) url.searchParams.set('sort', current.sort);
    }
    navigate(url);
  });

  const boot = () => document.querySelectorAll('[data-dharmashop-listing-controls]').forEach(initialise);
  document.addEventListener('dharmashop:updated', boot);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
