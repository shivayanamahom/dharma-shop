/** Offset is numeric module configuration; no stylesheet or scroll listener. */
(() => {
  'use strict';
  const desktop=matchMedia('(min-width: 992px)');
  const update=()=>document.querySelectorAll('[data-filter-sticky]').forEach((form)=>{
    const enabled=desktop.matches || (form.dataset.filterSticky==='always' && !form.closest('[class*="offcanvas-"]'));
    const target=form.closest('.moduletable') || form;
    target.classList.toggle('position-sticky',enabled);
    target.style.top=enabled?Math.max(0,Math.min(300,Number(form.dataset.stickyOffset)||0))+'px':'';
    target.style.maxHeight=enabled?'calc(100vh - '+(Math.max(0,Math.min(300,Number(form.dataset.stickyOffset)||0))+16)+'px)':'';
    target.classList.toggle('overflow-auto',enabled);
  });
  desktop.addEventListener('change',update);document.addEventListener('DOMContentLoaded',update);document.addEventListener('dharmashop:updated',update);update();
})();
