/** Optional native Bootstrap enhancement. Search is local and never submits a catalog query. */
(() => {
  'use strict';
  const updateSelected = () => document.querySelectorAll('[data-filter-selected-count]').forEach((badge) => {
    const group = badge.closest('.dharmaFilterSection');
    let count = group.querySelectorAll('input[type="checkbox"]:checked,input[type="radio"]:checked').length;
    // The empty radio means "Any"; a selected range counts as one condition.
    count -= group.querySelectorAll('input[type="radio"][value=""]:checked').length;
    group.querySelectorAll('select').forEach((select) => { count += [...select.selectedOptions].filter((option) => option.value !== '').length; });
    if ([...group.querySelectorAll('input[type="number"]')].some((input) => input.value !== '')) count++;
    badge.textContent = String(count);
    badge.classList.toggle('d-none', count === 0);
  });
  const update = (box) => {
    const text = (box.querySelector('[data-filter-search]')?.value || '').trim().toLocaleLowerCase();
    const rows = [...box.querySelectorAll('[data-filter-option]')];
    const limit = Math.max(0, Number(box.dataset.limit) || 0);
    const more = box.querySelector('[data-filter-more]');
    const expanded = more?.getAttribute('aria-expanded') === 'true';
    let visible = 0, overflow = 0;
    rows.forEach((row) => {
      const chosen = row.querySelector('input')?.checked;
      const matches = chosen || !text || row.dataset.label.toLocaleLowerCase().includes(text);
      const hidden = matches && !chosen && !text && limit > 0 && !expanded && visible >= limit;
      row.classList.toggle('d-none', !matches || hidden);
      if (matches && !hidden) visible++;
      if (hidden) overflow++;
    });
    if (more) {
      more.classList.toggle('d-none', !!text || (!overflow && !(expanded && rows.length > limit && limit > 0)));
      more.textContent = expanded ? more.dataset.less : more.dataset.more + (overflow ? ' (' + overflow + ')' : '');
    }
    box.querySelector('[data-filter-no-values]')?.classList.toggle('d-none', visible > 0);
  };
  const init = () => { updateSelected(); document.querySelectorAll('[data-filter-options]').forEach((box) => {
    box.querySelector('[data-filter-search]')?.classList.remove('d-none'); update(box);
  }); };
  document.addEventListener('input', (event) => { if(event.target.matches('[data-filter-search]')) update(event.target.closest('[data-filter-options]')); else if(event.target.closest('[data-dharmashop-filter]')) updateSelected(); });
  document.addEventListener('click', (event) => {
    const more=event.target.closest('[data-filter-more]'); if(!more)return;
    more.setAttribute('aria-expanded',more.getAttribute('aria-expanded')==='true'?'false':'true'); update(more.closest('[data-filter-options]'));
  });
  document.addEventListener('change', (event) => {const box=event.target.closest('[data-filter-options]');if(box)update(box);if(event.target.closest('[data-dharmashop-filter]'))updateSelected();});
  document.addEventListener('DOMContentLoaded',init); document.addEventListener('dharmashop:updated',init); init();
})();
