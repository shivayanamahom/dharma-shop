(() => {
  'use strict';
  const options = Joomla.getOptions('com_dharmashop.favorites') || {};
  const tokenName = options.tokenName || '';
  let lastRemoved = null;

  async function submit(form, url) {
    const data = new FormData(form);
    if (tokenName && !data.has(tokenName)) data.append(tokenName, '1');
    const response = await fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok) throw new Error(options.error || 'Request failed');
    const result = await response.json();
    if (!result.success) throw new Error(result.message || options.error || 'Request failed');
    document.querySelectorAll('[data-dharmashop-favorites-count]').forEach((node) => {
      node.textContent = String(result.count);
      node.classList.toggle('d-none', node.hasAttribute('data-hide-empty') && result.count === 0);
    });
    return result;
  }

  function updatePageCount(count) {
    const node = document.querySelector('[data-favorites-page-count]');
    if (node) node.textContent = `(${count})`;
  }

  function setHidden(node, hidden) {
    if (!node) return;
    node.hidden = hidden;
    node.classList.toggle('d-none', hidden);
  }

  function showEmptyState() {
    const list = document.querySelector('[data-favorites-list]');
    const empty = document.querySelector('[data-favorites-empty-state]');
    setHidden(list, true);
    setHidden(empty, false);
    const clearForm = document.querySelector('[data-favorites-clear-form]');
    setHidden(clearForm, true);
  }

  function hideUndo() {
    lastRemoved = null;
    const undo = document.querySelector('[data-favorites-undo]');
    setHidden(undo, true);
  }

  function restoreCard(row, parent, nextSibling) {
    if (!row || !parent) return;
    parent.insertBefore(row, nextSibling && nextSibling.parentNode === parent ? nextSibling : null);
  }

  async function toggleProduct(productId, buttonToken) {
    const data = new FormData();
    data.append('product_id', String(productId));
    if (buttonToken || tokenName) data.append(buttonToken || tokenName, '1');
    const response = await fetch(options.toggleUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const result = await response.json();
    if (!response.ok || !result.success || !result.added) throw new Error(result.message || options.error || 'Request failed');
    document.querySelectorAll('[data-dharmashop-favorites-count]').forEach((node) => {
      node.textContent = String(result.count);
      node.classList.toggle('d-none', node.hasAttribute('data-hide-empty') && result.count === 0);
    });
    updatePageCount(result.count);
    return result;
  }

  document.addEventListener('click', async (event) => {
    const undoButton = event.target.closest('[data-favorites-undo-button]');
    if (undoButton) {
      event.preventDefault();
      if (!lastRemoved) return;
      undoButton.disabled = true;
      try {
        const result = await toggleProduct(lastRemoved.productId, lastRemoved.tokenName);
        const { row, parent, nextSibling } = lastRemoved;
        restoreCard(row, parent, nextSibling);
        document.querySelectorAll(`[data-favorites-toggle][data-product-id="${lastRemoved.productId}"]`).forEach((heart) => {
          heart.setAttribute('aria-pressed', 'true');
          heart.classList.remove('text-danger', 'text-primary');
          heart.classList.add(heart.dataset.favoriteActiveClass || 'text-danger');
        });
        document.dispatchEvent(new CustomEvent('dharmashop:favorites-updated', { detail: result }));
        const list = document.querySelector('[data-favorites-list]');
        const empty = document.querySelector('[data-favorites-empty-state]');
        setHidden(list, false);
        setHidden(empty, true);
        const clearForm = document.querySelector('[data-favorites-clear-form]');
        setHidden(clearForm, result.count === 0);
        hideUndo();
      } catch (error) {
        window.alert(error.message || options.error || 'Request failed');
        undoButton.disabled = false;
      }
      return;
    }

    const button = event.target.closest('[data-favorites-toggle]');
    if (!button) return;
    event.preventDefault();
    const productId = Number.parseInt(button.dataset.productId || '0', 10) || 0;
    const sameProductButtons = document.querySelectorAll(`[data-favorites-toggle][data-product-id="${productId}"]`);
    sameProductButtons.forEach((item) => { item.disabled = true; });
    try {
      const form = button.form ? new FormData(button.form) : new FormData();
      if (!form.has('product_id')) form.append('product_id', button.dataset.productId || '0');
      const buttonToken = button.dataset.tokenName || tokenName;
      if (buttonToken && !form.has(buttonToken)) form.append(buttonToken, '1');
      const response = await fetch(options.toggleUrl || button.dataset.url, { method: 'POST', body: form, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || options.error || 'Request failed');
      document.querySelectorAll(`[data-favorites-toggle][data-product-id="${Number.parseInt(result.productId || productId, 10) || productId}"]`).forEach((item) => {
        item.setAttribute('aria-pressed', result.added ? 'true' : 'false');
        item.classList.remove('text-danger', 'text-primary');
        item.classList.toggle(item.dataset.favoriteActiveClass || 'text-danger', result.added);
      });
      document.querySelectorAll('[data-dharmashop-favorites-count]').forEach((node) => {
        node.textContent = String(result.count);
        node.classList.toggle('d-none', node.hasAttribute('data-hide-empty') && result.count === 0);
      });
      if (document.querySelector('.dharmaFavorites') && !result.added) {
        const row = document.querySelector(`[data-favorite-row="${productId}"]`);
        if (row) {
          // Grid/list cards live inside a Bootstrap .col. Remove and restore the
          // whole column so deleting a card does not leave a blank grid slot.
          const removable = row.closest('.col') || row;
          lastRemoved = { productId, tokenName: buttonToken, row: removable, parent: removable.parentNode, nextSibling: removable.nextSibling };
          removable.remove();
          const undo = document.querySelector('[data-favorites-undo]');
          setHidden(undo, false);
        }
        updatePageCount(result.count);
        if (result.count === 0) showEmptyState();
      }
      document.dispatchEvent(new CustomEvent('dharmashop:favorites-updated', { detail: result }));
    } catch (error) {
      window.alert(error.message || options.error || 'Request failed');
    } finally { sameProductButtons.forEach((item) => { item.disabled = false; }); }
  });

  document.addEventListener('submit', async (event) => {
    const clearForm = event.target.closest('[data-favorites-clear-form]');
    if (clearForm) {
      event.preventDefault();
      const button = clearForm.querySelector('button[type="submit"]');
      if (button) button.disabled = true;
      try {
        const result = await submit(clearForm, clearForm.action || options.clearUrl);
        hideUndo();
        document.querySelectorAll('[data-favorite-row]').forEach((row) => row.remove());
        updatePageCount(result.count);
        showEmptyState();
      } catch (error) {
        window.alert(error.message || options.error || 'Request failed');
        if (button) button.disabled = false;
      }
      return;
    }
  });
})();
