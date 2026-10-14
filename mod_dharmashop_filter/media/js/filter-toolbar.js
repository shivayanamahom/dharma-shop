/* Кнопка «Фильтр» на телефоне встаёт в одну строку с сортировкой и переключателем вида списка (если они есть на странице). */
(() => {
  const place = () => {
    const toggle = document.querySelector('.dharmaFilterToggle');
    const bar = document.querySelector('[data-dharmashop-listing-controls] > .d-flex');
    if (!toggle || !bar || toggle.classList.contains('dharmaFilterToggle-inline')) return;
    const home = toggle.parentElement;
    bar.insertBefore(toggle, bar.firstChild);
    toggle.classList.add('dharmaFilterToggle-inline');
    bar.classList.add('dharmaListingBar-withFilter');
    // Заголовок модуля над кнопкой не нужен там, где кнопка видна (на широком экране фильтр остаётся блоком со своим заголовком).
    const heading = [...home.children].find((element) => /^H[1-6]$/.test(element.tagName));
    if (heading) {
      const hidden = [...toggle.classList].map((name) => /^d-(sm|md|lg|xl)-none$/.exec(name)).find(Boolean);
      heading.classList.add('d-none');
      if (hidden) heading.classList.add(`d-${hidden[1]}-block`);
    }
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', place);
  else place();
})();
