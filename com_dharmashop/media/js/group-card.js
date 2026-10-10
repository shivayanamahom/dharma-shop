/** One variant per card. No variant matrix/gallery prefetch; delegated events survive listing replacement. */
(() => {
  'use strict';
  const requests = new WeakMap();
  const controls = '[data-group-choice]';
  const purchase = '[data-dharmashop-add-to-cart], [data-dharmashop-stepper-plus], [data-dharmashop-stepper-minus], [data-dharmashop-qty-step]';
  const busy = (card, value) => {
    card.querySelectorAll('.dharmaCardActions button, .dharmaCardActions input').forEach((input) => {
      if (value && !input.disabled) { input.dataset.groupDisabled = '1'; input.disabled = true; }
      else if (!value && input.dataset.groupDisabled) { delete input.dataset.groupDisabled; input.disabled = false; }
    });
  };
  const restore = (card) => card.querySelectorAll(controls).forEach((control) => {
    if (control.tagName === 'SELECT') {
      const option = Array.from(control.options).find((value) => value.defaultSelected);
      if (option) control.value = option.value;
    } else control.checked = control.defaultChecked;
  });
  const dispose = (card) => {
    card.querySelectorAll('[data-dharmashop-card-slider]').forEach((element) => element.swiper && element.swiper.destroy(true, true));
    card.querySelectorAll('.carousel').forEach((element) => window.bootstrap?.Carousel?.getInstance(element)?.dispose());
  };
  document.addEventListener('click', (event) => {
    if (event.target.closest(purchase)?.closest('[data-dharmashop-group-card][aria-busy="true"], [data-dharmashop-group-card][data-group-pending]')) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);
  document.addEventListener('change', async (event) => {
    const control = event.target.closest(controls);
    const card = control?.closest('[data-dharmashop-group-card]');
    if (!card) return;
    requests.get(card)?.abort();
    const controller = new AbortController();
    requests.set(card, controller);
    const data = new URLSearchParams({ context: card.dataset.groupContext, source: card.dataset.groupSource });
    card.querySelectorAll(controls).forEach((input) => {
      if (input.tagName === 'SELECT' || input.checked) data.set('choice[' + input.dataset.groupChoice + ']', input.value);
    });
    const message = card.querySelector('[data-group-message]');
    if (message) message.textContent = '';
    card.setAttribute('aria-busy', 'true');
    card.classList.add('opacity-50');
    busy(card, true);
    let publicError = '';
    let unavailable = false;
    // Keep choices enabled for quick changes; purchases are blocked in capture phase until a response settles.
    try {
      const url = new URL(card.dataset.groupUrl, window.location.href);
      if (url.origin !== window.location.origin) throw new Error('Invalid endpoint');
      const response = await fetch(url, { method: 'POST', body: data, signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!response.ok) throw new Error('Request failed');
      const result = await response.json();
      if (requests.get(card) !== controller || !card.isConnected) return;
      if (!result.success || typeof result.html !== 'string') {
        publicError = typeof result.message === 'string' ? result.message : '';
        unavailable = result.reason === 'unavailable';
        throw new Error('Selection failed');
      }
      const range = document.createRange();
      range.selectNode(card);
      const replacement = range.createContextualFragment(result.html.trim()).firstElementChild;
      if (!replacement?.matches('[data-dharmashop-group-card]') || replacement.dataset.dharmashopGroup !== card.dataset.dharmashopGroup) throw new Error('Invalid card');
      const field = control.dataset.groupChoice;
      const value = control.value;
      dispose(card);
      card.replaceWith(replacement);
      document.dispatchEvent(new CustomEvent('dharmashop:updated'));
      const focus = Array.from(replacement.querySelectorAll(controls)).find((input) => input.dataset.groupChoice === field && (input.tagName === 'SELECT' || input.value === value));
      focus?.focus({ preventScroll: true });
    } catch (error) {
      if (error.name === 'AbortError' || requests.get(card) !== controller || !card.isConnected) return;
      if (unavailable) card.dataset.groupPending = '1';
      else { restore(card); delete card.dataset.groupPending; }
      if (message) message.textContent = publicError || (window.Joomla?.Text?._('COM_DHARMASHOP_GROUP_CARD_ERROR') || 'Unable to switch variant. Please try again.');
    } finally {
      if (requests.get(card) === controller) {
        requests.delete(card);
        card.removeAttribute('aria-busy');
        card.classList.toggle('opacity-50', Boolean(card.dataset.groupPending));
        busy(card, Boolean(card.dataset.groupPending));
      }
    }
  });
})();
