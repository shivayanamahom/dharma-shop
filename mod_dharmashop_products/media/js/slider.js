(() => {
  'use strict';
  const init = (root) => {
    const track = root.querySelector('[data-products-track]');
    if (!track || track.swiper || typeof window.Swiper !== 'function') return;
    let options = {};
    try { options = JSON.parse(root.dataset.sliderOptions || '{}'); } catch { return; }
    const count = (key) => Math.max(1, Math.min(6, Number(options.columns?.[key]) || 1));
    const total = track.children[0]?.children.length || 0;
    if (!total) return;
    const prev = root.querySelector('[data-products-prev]');
    const next = root.querySelector('[data-products-next]');
    const pagination = root.querySelector('[data-products-pagination]');
    const controls = root.querySelector('[data-products-controls]');
    const loop = !!options.loop && total > Math.max(...['xs','sm','md','lg','xl','xxl'].map(count));
    const swiper = new window.Swiper(track, {
      slidesPerView: count('xs'), spaceBetween: Math.max(0, Math.min(64, Number(options.gap) || 0)),
      speed: Math.max(0, Math.min(3000, Number(options.speed) || 0)), loop, loopAddBlankSlides: false,
      watchOverflow: true, observer: true, observeParents: true, resizeObserver: true,
      noSwipingSelector: 'input, select, textarea, button', navigation: prev && next ? { prevEl: prev, nextEl: next } : false,
      pagination: pagination ? { el: pagination, clickable: true, bulletClass: 'dharmaProductsBullet', bulletActiveClass: 'active',
        modifierClass: 'dharmaProductsPagination-', clickableClass: 'dharmaProductsPaginationClickable',
        renderBullet: (i, cls) => '<button type="button" class="' + cls + ' bg-secondary" data-bs-target aria-label="' + (i + 1) + '"></button>' } : false,
      autoplay: options.autoplay ? { delay: Math.max(1000, Math.min(60000, Number(options.delay) || 5000)), pauseOnMouseEnter: true, disableOnInteraction: true, stopOnLastSlide: !loop } : false,
      breakpoints: Object.fromEntries(Object.entries({ sm: 576, md: 768, lg: 992, xl: 1200, xxl: 1400 }).map(([key, width]) => [width, { slidesPerView: count(key) }]))
    });
    const update = () => {
      if (controls) controls.classList.toggle('d-none', swiper.isLocked);
      if (prev) prev.disabled = swiper.isLocked || (!loop && swiper.isBeginning);
      if (next) next.disabled = swiper.isLocked || (!loop && swiper.isEnd);
    };
    ['lock','unlock','slideChange','resize','breakpoint','observerUpdate'].forEach((name) => swiper.on(name, update));
    root.addEventListener('focusin', () => swiper.autoplay?.stop());
    update();
  };
  const boot = () => document.querySelectorAll('[data-dharmashop-products-slider]').forEach(init);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true }); else boot();
  document.addEventListener('joomla:updated', boot);
  document.addEventListener('dharmashop:updated', boot);
})();
