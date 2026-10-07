/**
 * Dharma Shop: «Показать ещё» и бесконечная прокрутка (настройка магазина «Тип пагинации»).
 * Нумерованная пагинация остаётся в разметке (для поисковиков и без JavaScript); скрипт скрывает её и добавляет кнопку.
 * Следующая порция берётся фрагментом (format=raw) с адреса следующей страницы из этой же навигации и добавляется к списку;
 * адрес страницы не меняется. Режим — в параметрах скрипта `com_dharmashop.pagination` (type: more | infinite).
 * После замены списка (сортировка, вид) приходит событие `dharmashop:updated`, кнопка создаётся заново.
 */
(() => {
  'use strict';

  const options = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_dharmashop.pagination')) || {};
  const infinite = options.type === 'infinite';
  const label = (window.Joomla && Joomla.Text && Joomla.Text._('COM_DHARMASHOP_SHOW_MORE')) || 'Show more';
  // Адрес следующей страницы: ссылка рядом с активным номером; у последней страницы её нет.
  const nextUrl = (scope) => {
    const link = scope.querySelector('.pagination li.active + li a[href]');
    return link && link.getAttribute('href') !== '#' ? new URL(link.href, window.location.href) : null;
  };

  // Корень карточек: сетка/список (.row) или тело таблицы; новые элементы добавляются в него.
  const itemsRoot = (scope) => {
    const children = [...scope.children];
    const row = children.find((element) => element.matches('.row'));
    if (row) return row;
    const table = children.find((element) => element.matches('.table-responsive'));

    return table ? table.querySelector('tbody') : null;
  };

  const initialise = (container) => {
    const wrapper = container.querySelector('[data-dharmashop-pagination]');
    if (!wrapper || container.querySelector('[data-dharmashop-more]')) return;
    const hasNext = !!nextUrl(wrapper);
    wrapper.hidden = hasNext;
    if (!hasNext) return;

    // Скрытая навигация текущей порции: из неё берётся адрес следующей страницы.
    let current = wrapper;
    let busy = false;
    let observer = null;
    const holder = document.createElement('div');
    holder.className = 'text-center mt-4';
    holder.setAttribute('data-dharmashop-more', '');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-primary';
    button.textContent = label;
    holder.appendChild(button);
    wrapper.after(holder);

    const finish = (showNavigation) => {
      if (observer) observer.disconnect();
      holder.remove();
      if (showNavigation) current.hidden = false;
    };

    const load = () => {
      const url = nextUrl(current);
      const root = itemsRoot(container);
      if (busy || !url || !root) return;
      busy = true;
      button.disabled = true;
      container.setAttribute('aria-busy', 'true');
      url.searchParams.set('format', 'raw');

      fetch(url.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => {
          if (!response.ok) throw new Error(String(response.status));
          return response.text();
        })
        .then((html) => {
          const template = document.createElement('template');
          template.innerHTML = html;
          const incoming = itemsRoot(template.content);
          const pagination = template.content.querySelector('[data-dharmashop-pagination]');
          if (!incoming || !pagination) throw new Error('no items');
          root.append(...incoming.children);
          current.replaceWith(pagination);
          current = pagination;
          current.hidden = true;
          if (!nextUrl(current)) finish(false);
        })
        .catch(() => finish(true))
        .finally(() => {
          busy = false;
          button.disabled = false;
          container.removeAttribute('aria-busy');
          // Если кнопка всё ещё в зоне видимости (высокий экран), наблюдатель должен сработать снова.
          if (observer && holder.isConnected) {
            observer.unobserve(holder);
            observer.observe(holder);
          }
        });
    };

    button.addEventListener('click', load);

    if (infinite && 'IntersectionObserver' in window) {
      observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) load();
      }, { rootMargin: '400px 0px' });
      observer.observe(holder);
    }
  };

  const boot = () => document.querySelectorAll('[data-dharmashop-listing]').forEach(initialise);

  document.addEventListener('dharmashop:updated', boot);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
