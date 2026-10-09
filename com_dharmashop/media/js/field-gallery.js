/** Additional field galleries: independent Swiper instances and Fancybox groups. */
(() => {
  'use strict';

  const initialise = (root) => {
    const element = root.querySelector('[data-field-gallery-slider]');
    if (element && !element.swiper && typeof window.Swiper === 'function') {
      const columns = JSON.parse(root.dataset.columns || '{}');
      const count = (key) => Math.max(1, Math.min(6, Number(columns[key]) || 1));
      const controls = root.querySelector('[data-field-gallery-controls]');
      const prev = root.querySelector('[data-field-gallery-prev]');
      const next = root.querySelector('[data-field-gallery-next]');
      const swiper = new window.Swiper(element, {
        slidesPerView: count('xs'),
        spaceBetween: 16,
        watchOverflow: true,
        observer: true,
        observeParents: true, // UITab panels can be hidden when the page first loads.
        resizeObserver: true,
        a11y: { enabled: true },
        navigation: prev && next ? { prevEl: prev, nextEl: next } : false,
        breakpoints: Object.fromEntries(Object.entries({ sm: 576, md: 768, lg: 992, xl: 1200, xxl: 1400 })
          .map(([key, width]) => [width, { slidesPerView: count(key) }])),
      });
      const updateControls = () => {
        if (controls) controls.classList.toggle('d-none', swiper.isLocked);
        if (prev) prev.disabled = swiper.isBeginning || swiper.isLocked;
        if (next) next.disabled = swiper.isEnd || swiper.isLocked;
      };
      ['lock', 'unlock', 'slideChange', 'resize', 'breakpoint', 'observerUpdate'].forEach((name) => swiper.on(name, updateControls));
      updateControls();
    }
    if (root.dataset.lightbox === '1' && !root.dataset.fieldLightboxReady
      && window.Fancybox && typeof window.Fancybox.bind === 'function') {
      window.Fancybox.bind('#' + root.id + ' [data-fancybox="' + root.id + '"]', {});
      root.dataset.fieldLightboxReady = '1';
    }
  };
  const boot = () => document.querySelectorAll('[data-dharmashop-field-gallery]').forEach(initialise);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
  // Reuse on fragment updates; an already initialised block is never bound twice.
  document.addEventListener('joomla:updated', boot);
})();
