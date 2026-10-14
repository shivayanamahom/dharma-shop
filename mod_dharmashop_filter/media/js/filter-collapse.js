/* Кнопка «Свернуть» в углу горизонтального фильтра: прячет группы, оставляет выбранные значения и счётчик. Состояние — в localStorage браузера. */
(() => {
  const KEY = (form) => 'dharmashopFilterCollapsed:' + form.id;
  const read = (form) => { try { return window.localStorage.getItem(KEY(form)) === '1'; } catch (e) { return false; } };
  const write = (form, value) => { try { value ? window.localStorage.setItem(KEY(form), '1') : window.localStorage.removeItem(KEY(form)); } catch (e) { /* хранилище недоступно */ } };

  const apply = (form, collapsed) => {
    const button = form.querySelector('[data-filter-collapse]');
    const body = button ? document.getElementById(button.getAttribute('aria-controls')) : null;
    if (!button || !body) return;
    body.hidden = collapsed;
    form.classList.toggle('dharmaFilter-collapsed', collapsed);
    button.hidden = false;
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    const label = collapsed ? button.dataset.labelExpand : button.dataset.labelCollapse;
    button.title = label;
    button.setAttribute('aria-label', label);
  };

  const init = () => document.querySelectorAll('form[data-dharmashop-filter]').forEach((form) => {
    if (form.querySelector('[data-filter-collapse]')) apply(form, read(form));
  });

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-filter-collapse]');
    const form = button ? button.closest('form') : null;
    if (!form) return;
    const collapsed = button.getAttribute('aria-expanded') === 'true';
    write(form, collapsed);
    apply(form, collapsed);
  });
  // После замены формы (AJAX, каскад) состояние берётся из хранилища заново.
  document.addEventListener('dharmashop:updated', init);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
