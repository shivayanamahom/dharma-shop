/** Related product sliders. The Swiper library is shared through Joomla WAM; controls belong to each block. */
(() => {
  'use strict';
  const initialise = (root) => {
    const element = root.querySelector('[data-related-track]');
    if (!element || element.swiper || typeof window.Swiper !== 'function') return;
    const options = JSON.parse(root.dataset.sliderOptions || '{}');
    const total = element.children[0]?.children.length || 0;
    if (!total) return;
    // Keep the configured card width even when fewer products than columns exist.
    const count = (key) => Math.max(1, Math.min(6, Number(options.columns?.[key]) || 1));
    const prev = root.querySelector('[data-related-prev]');
    const next = root.querySelector('[data-related-next]');
    const pagination = root.querySelector('[data-related-pagination]');
    const controls = root.querySelector('[data-related-controls]');
    const loop = !!options.loop && total > Math.max(...['xs','sm','md','lg','xl','xxl'].map(count));
    const swiper = new window.Swiper(element, {
      slidesPerView: count('xs'),
      spaceBetween: Math.max(0, Math.min(64, Number(options.gap) || 0)),
      // Use the configured duration for both manual and automatic transitions.
      speed: Math.max(0, Math.min(3000, Number(options.speed) || 0)),
      loop,
      loopAddBlankSlides: false,
      watchOverflow: true,
      observer: true,
      observeParents: true,
      resizeObserver: true,
      // Leave image carousels and purchase controls to their own handlers.
      noSwipingSelector: '[data-dharmashop-card-slider], .carousel, input, select, textarea, button, [data-dharmashop-cart-control]',
      navigation: prev && next ? { prevEl: prev, nextEl: next } : false,
      pagination: pagination ? {
        el: pagination,
        clickable: true,
        bulletClass: 'dharmaRelatedBullet',
        bulletActiveClass: 'active',
        modifierClass: 'dharmaRelatedPagination-',
        clickableClass: 'dharmaRelatedPaginationClickable',
        horizontalClass: 'dharmaRelatedPaginationHorizontal',
        renderBullet: (index, className) => '<button type="button" class="' + className + ' bg-secondary" data-bs-target aria-label="' + (index + 1) + '"></button>',
      } : false,
      a11y: { enabled: true, prevSlideMessage: prev?.getAttribute('aria-label'), nextSlideMessage: next?.getAttribute('aria-label') },
      autoplay: options.autoplay ? {
        delay: Math.max(1000, Math.min(60000, Number(options.delay) || 5000)),
        pauseOnMouseEnter: true,
        disableOnInteraction: true,
        stopOnLastSlide: !loop,
      } : false,
      breakpoints: Object.fromEntries(Object.entries({ sm: 576, md: 768, lg: 992, xl: 1200, xxl: 1400 })
        .map(([key, width]) => [width, { slidesPerView: count(key) }])),
    });
    const updateControls = () => {
      if (controls) controls.classList.toggle('d-none', swiper.isLocked);
      if (prev) prev.disabled = swiper.isLocked || (!loop && swiper.isBeginning);
      if (next) next.disabled = swiper.isLocked || (!loop && swiper.isEnd);
    };
    ['lock','unlock','slideChange','resize','breakpoint','observerUpdate'].forEach((name) => swiper.on(name, updateControls));
    // Once a visitor starts using the cards with the keyboard, stop automatic motion.
    root.addEventListener('focusin', () => swiper.autoplay?.stop());
    updateControls();
  };
  const boot = () => document.querySelectorAll('[data-dharmashop-related-slider]').forEach(initialise);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
  document.addEventListener('joomla:updated', boot);
  document.addEventListener('dharmashop:updated', boot);
})();
