/* Список контекстов изображения в таблице размеров: значения подставляются при первом обращении к списку (см. ImagecontextField). */
(() => {
  'use strict';
  const fill = (select) => {
    if (!select.hasAttribute('data-dharma-lazy-options')) return;
    const all = (window.Joomla && Joomla.getOptions) ? Joomla.getOptions('com_dharmashop.imageContexts') : null;
    if (!Array.isArray(all)) return;
    const current = select.value;
    const fragment = document.createDocumentFragment();
    all.forEach(([value, text]) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = text;
      if (value === current) option.selected = true;
      fragment.append(option);
    });
    select.replaceChildren(fragment);
    select.value = current;
    select.removeAttribute('data-dharma-lazy-options');
  };
  const handler = (event) => {
    const select = event.target instanceof Element ? event.target.closest('select[data-dharma-lazy-options]') : null;
    if (select) fill(select);
  };
  document.addEventListener('focusin', handler);
  document.addEventListener('mousedown', handler, true);
  document.addEventListener('keydown', handler, true);
})();
