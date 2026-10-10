/** Progressive variant selection: ordinary URLs; AJAX downloads only the chosen product page. */
(() => {
  'use strict';
  if (window.dharmaProductChoiceReady) return;
  window.dharmaProductChoiceReady = true;
  const rootSelector = '#dharma_shop.dharmaProduct';
  let request = null;
  const assetLoads = new Map();
  const text = (key) => window.Joomla?.Text?._(key) || 'Unable to switch variant. Please try again.';
  const restore = (form) => form?.querySelectorAll('[data-product-choice]').forEach((input) => {
    if (input.tagName === 'SELECT') input.value = Array.from(input.options).find((option) => option.defaultSelected)?.value || '';
    else input.checked = input.defaultChecked;
  });
  const boot = () => document.querySelectorAll('[data-product-choices]').forEach((form) => {
    form.querySelector('[data-product-choice-submit]')?.classList.add('d-none');
  });
  const busy = (root, value) => {
    root.setAttribute('aria-busy', String(value));
    root.classList.toggle('opacity-50', value);
    root.querySelectorAll('.dharmaProductBuyActions button, .dharmaProductBuyActions input').forEach((input) => {
      if (value && !input.disabled) { input.dataset.choiceDisabled = '1'; input.disabled = true; }
      else if (!value && input.dataset.choiceDisabled) { delete input.dataset.choiceDisabled; input.disabled = false; }
    });
  };
  const dispose = (root) => {
    root.querySelectorAll('.swiper').forEach((element) => element.swiper?.destroy(true, true));
    root.querySelectorAll('.carousel').forEach((element) => window.bootstrap?.Carousel?.getInstance(element)?.dispose());
    root.querySelectorAll('[data-dharmashop-gallery]').forEach((gallery) => window.Fancybox?.unbind?.('#' + gallery.id + ' [data-fancybox]'));
    root.querySelectorAll('[data-dharmashop-field-gallery]').forEach((gallery) => window.Fancybox?.unbind?.('#' + gallery.id + ' [data-fancybox="' + gallery.id + '"]'));
    window.Fancybox?.close?.();
  };
  const assetUrl = (element, attribute, base) => new URL(element.getAttribute(attribute), base);
  // Only native head resources; never execute scripts from product descriptions or arbitrary inline code.
  const assets = async (page, base, signal) => {
    page.head.querySelectorAll('script.joomla-script-options[type="application/json"]').forEach((element) => {
      window.Joomla?.loadOptions?.(JSON.parse(element.textContent));
    });
    for (const original of page.head.querySelectorAll('link[rel="stylesheet"][href], script[src]')) {
      if (signal.aborted) throw new DOMException('Aborted', 'AbortError');
      const attribute = original.tagName === 'SCRIPT' ? 'src' : 'href';
      const url = assetUrl(original, attribute, base);
      if (assetLoads.has(url.href)) { await assetLoads.get(url.href); continue; }
      const existing = Array.from(document.head.querySelectorAll(original.tagName === 'SCRIPT' ? 'script[src]' : 'link[rel="stylesheet"][href]'));
      if (existing.some((element) => assetUrl(element, attribute, location.href).href === url.href)) continue;
      // A new third-party dependency needs a normal page visit and its usual consent workflow.
      if (url.origin !== location.origin) throw new Error('External dependency');
      const pending = new Promise((resolve, reject) => {
        const element = document.createElement(original.tagName.toLowerCase());
        Array.from(original.attributes).forEach(({ name, value }) => {
          if (!name.toLowerCase().startsWith('on') && name !== attribute) element.setAttribute(name, value);
        });
        element.setAttribute(attribute, url.href);
        if (attribute === 'src') element.async = false;
        const timeout = setTimeout(() => { element.remove(); reject(new Error('Asset timed out')); }, 15000);
        element.onload = () => { clearTimeout(timeout); resolve(); };
        element.onerror = () => { clearTimeout(timeout); element.remove(); reject(new Error('Asset failed')); };
        document.head.append(element);
      });
      assetLoads.set(url.href, pending);
      try { await pending; } catch (error) { assetLoads.delete(url.href); throw error; }
    }
  };
  const metadata = (page) => {
    document.title = page.title;
    const selector = 'link[rel="canonical"], meta[name="description"], meta[name="robots"], meta[property^="og:"], meta[name^="twitter:"], script[type="application/ld+json"]';
    document.head.querySelectorAll(selector).forEach((element) => element.remove());
    page.head.querySelectorAll(selector).forEach((element) => document.head.append(document.importNode(element, true)));
    const oldCrumbs = document.querySelector('.breadcrumb');
    const newCrumbs = page.querySelector('.breadcrumb');
    if (oldCrumbs && newCrumbs) oldCrumbs.replaceWith(document.importNode(newCrumbs, true));
  };
  const load = async (url, form = null, push = true, navigate = false) => {
    const root = document.querySelector(rootSelector);
    if (!root) { location.assign(url); return; }
    request?.abort();
    const controller = new AbortController();
    request = controller;
    const message = form?.querySelector('[data-product-choice-message]');
    if (message) message.textContent = '';
    busy(root, true);
    let unavailable = false;
    try {
      const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!response.ok) {
        if (response.headers.get('content-type')?.includes('application/json')) {
          const error = await response.json();
          unavailable = error.reason === 'unavailable';
          throw new Error(typeof error.message === 'string' ? error.message : text('COM_DHARMASHOP_PRODUCT_CHOICE_UNAVAILABLE'));
        }
        throw new Error(text('COM_DHARMASHOP_PRODUCT_CHOICE_ERROR'));
      }
      if (navigate) {
        const result = await response.json();
        const target = new URL(result.url, location.href);
        if (!result.success || target.origin !== location.origin) throw new Error(text('COM_DHARMASHOP_PRODUCT_CHOICE_ERROR'));
        if (request === controller) location.assign(target.href);
        return;
      }
      const target = new URL(response.url);
      if (target.origin !== location.origin) throw new Error(text('COM_DHARMASHOP_PRODUCT_CHOICE_ERROR'));
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const replacement = page.querySelector(rootSelector);
      if (!replacement || replacement.dataset.productGroup !== root.dataset.productGroup) throw new Error(text('COM_DHARMASHOP_PRODUCT_CHOICE_UNAVAILABLE'));
      if (request !== controller || controller.signal.aborted) return;
      try { await assets(page, target.href, controller.signal); }
      catch (error) {
        if (error.name === 'AbortError') throw error;
        if (request === controller) location.assign(target.href);
        return;
      }
      if (request !== controller || !root.isConnected || controller.signal.aborted) return;
      dispose(root);
      root.replaceWith(document.importNode(replacement, true));
      metadata(page);
      if (push) history.pushState({ ...history.state, dharmaProductChoice: true }, '', target.href);
      document.dispatchEvent(new CustomEvent('dharmashop:updated'));
      document.dispatchEvent(new CustomEvent('joomla:updated'));
      boot();
    } catch (error) {
      if (error.name === 'AbortError' || request !== controller) return;
      if (!push) { location.reload(); return; }
      if (unavailable) root.dataset.productPending = '1';
      else { restore(form); delete root.dataset.productPending; }
      if (message) message.textContent = unavailable ? error.message : text('COM_DHARMASHOP_PRODUCT_CHOICE_ERROR');
    } finally {
      if (request === controller) { request = null; if (root.isConnected) { busy(root, Boolean(root.dataset.productPending)); root.setAttribute('aria-busy', 'false'); } }
    }
  };
  document.addEventListener('change', (event) => {
    const form = event.target.closest('[data-product-choice]')?.closest('[data-product-choices]');
    if (form) form.requestSubmit();
  });
  document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-product-choices]');
    if (!form) return;
    event.preventDefault();
    const url = new URL(form.action, location.href);
    const data = new URLSearchParams(new FormData(form));
    const navigate = form.dataset.choiceMode !== 'ajax';
    data.set(navigate ? 'product_choice_resolve' : 'product_choice_ajax', '1');
    url.search = data.toString();
    if (url.origin === location.origin) load(url.href, form, true, navigate);
  });
  // Delegated cart/quantity listeners also run on the old page: block purchases until replacement is complete.
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-dharmashop-add-to-cart], [data-dharmashop-qty-step], [data-dharmashop-stepper-plus], [data-dharmashop-stepper-minus]')?.closest(rootSelector + '[aria-busy="true"], ' + rootSelector + '[data-product-pending]')) {
      event.preventDefault(); event.stopImmediatePropagation();
    }
  }, true);
  if (document.querySelector('[data-product-choices][data-choice-mode="ajax"]')) history.replaceState({ ...history.state, dharmaProductChoice: true }, '', location.href);
  window.addEventListener('popstate', (event) => {
    if (event.state?.dharmaProductChoice && document.querySelector('[data-product-choices][data-choice-mode="ajax"]')) load(location.href, null, false);
  });
  document.addEventListener('dharmashop:updated', boot);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
