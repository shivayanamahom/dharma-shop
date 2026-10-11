/** Group members: choose first, then add a native Joomla subform row. */
import JoomlaDialog from 'joomla.dialog';

const pending = new WeakSet();
const selectedIds = (subform) => new Set(subform.getRows().map((row) =>
  Number(row.querySelector('.js-input-value')?.value)).filter((id) => Number.isSafeInteger(id) && id > 0));

const openPicker = (subform, button) => {
  if (pending.has(subform) || subform.getRows().length >= Number(subform.maximum)) return;
  const template = subform.querySelector('template.subform-repeatable-template-section');
  const select = template?.content.querySelector('[data-button-action="select"][data-modal-config]');
  if (!select) return;
  const config = JSON.parse(select.dataset.modalConfig);
  const url = new URL(config.src, window.location.origin);
  url.searchParams.set('picker_exclude', [...selectedIds(subform)].slice(0, 500).join(','));
  url.searchParams.set('limitstart', '0');
  config.src = url.toString();
  const after = button.closest(subform.repeatableElement);
  const dialog = new JoomlaDialog(config);
  dialog.classList.add('joomla-dialog-content-select-field');
  pending.add(subform);

  const receive = (event) => {
    if (event.origin !== window.location.origin || event.source !== dialog.querySelector('iframe')?.contentWindow) return;
    const data = event.data;
    if (data?.messageType === 'joomla:cancel') { dialog.close(); return; }
    if (data?.messageType !== 'joomla:content-select' || data.contentType !== 'com_dharmashop.product') return;
    const id = Number(data.id);
    if (!Number.isSafeInteger(id) || id < 1 || selectedIds(subform).has(id)) return;
    let product;
    try { product = JSON.parse(data.pickerProduct); } catch { return; }
    if (Number(product?.id) !== id || !subform.isConnected) return;
    // Native addRow fixes names/IDs, initializes fields and retains Joomla drag ordering.
    const row = subform.addRow(after?.isConnected ? after : null);
    if (!row) { dialog.close(); return; }
    const field = row.querySelector('[data-dharmashop-product-field]');
    const value = row.querySelector('.js-input-value');
    const title = row.querySelector('.js-input-title');
    field.dataset.pickerProduct = JSON.stringify(product);
    value.value = String(id);
    title.value = String(data.title || product.title || id);
    value.dispatchEvent(new Event('change', { bubbles: true }));
    dialog.close();
  };
  window.addEventListener('message', receive);
  dialog.addEventListener('joomla-dialog:close', () => {
    window.removeEventListener('message', receive);
    pending.delete(subform);
    dialog.destroy();
    button.focus();
  }, { once: true });
  dialog.show();
};

const add = (event) => {
  if (event.type === 'keydown' && event.code !== 'Space') return;
  const button = event.target.closest('.group-add');
  const subform = button?.closest('joomla-field-subform.dharma-group-members');
  if (!subform || !subform.closest('#productgroup-form')) return;
  // Capture before the native subform's add handler; cancel creates no empty row.
  event.preventDefault();
  event.stopPropagation();
  openPicker(subform, button);
};
document.addEventListener('click', add, true);
document.addEventListener('keydown', add, true);

// Group-specific preview: selected axes only, with native Bootstrap styling.
const memberFields = () => {
  const axes = document.querySelector('#jform_axes');
  if (!axes) return;
  const selected = [...axes.selectedOptions].filter((option) => Number(option.value) > 0);
  document.querySelectorAll('#productgroup-form [data-group-member-choices]').forEach((list) => {
    const field = list.closest('[data-dharmashop-product-field]');
    let product;
    try { product = JSON.parse(field.dataset.pickerProduct || 'null'); } catch { product = null; }
    const show = selected.length > 0 && Number(product?.id) === Number(field.querySelector('.js-input-value')?.value) && Number(product?.id) > 0;
    field.querySelector('[data-group-member-panel]').hidden = !show;
    field.querySelector('[data-group-member-main]').classList.toggle('col-xxl-8', show);
    list.replaceChildren();
    if (!show) return;
    selected.forEach((axis) => {
      const box = document.createElement('div');
      box.className = 'border rounded bg-body px-3 py-2 flex-grow-1';
      const title = document.createElement('dt');
      title.className = 'small text-body-secondary fw-normal mb-1';
      title.textContent = axis.textContent;
      const value = document.createElement('dd');
      value.className = 'mb-0 fw-semibold d-flex flex-wrap align-items-center gap-2';
      const options = product.choices?.[axis.value];
      if (!Array.isArray(options) || !options.length) {
        value.textContent = list.dataset.empty;
        value.classList.add('text-warning-emphasis');
      } else options.forEach((option) => {
        const label = document.createElement('span');
        label.className = 'd-inline-flex align-items-center gap-2';
        if (/^#[0-9a-f]{6}$/i.test(option.color || '')) {
          const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
          svg.setAttribute('width', '24'); svg.setAttribute('height', '24');
          svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
          svg.classList.add('border', 'rounded-circle', 'flex-shrink-0');
          const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
          circle.setAttribute('cx', '12'); circle.setAttribute('cy', '12');
          circle.setAttribute('r', '12'); circle.setAttribute('fill', option.color);
          svg.append(circle); label.append(svg);
        } else if (option.image) {
          try {
            const url = new URL(option.image, window.location.origin);
            if (['http:', 'https:'].includes(url.protocol)) {
              const image = document.createElement('img');
              image.src = url.href; image.width = 24; image.height = 24; image.alt = '';
              image.loading = 'lazy'; image.decoding = 'async';
              image.className = 'border rounded-circle object-fit-cover flex-shrink-0';
              label.append(image);
            }
          } catch { /* Keep the text if a sample URL is invalid. */ }
        }
        const text = document.createElement('span'); text.textContent = option.title;
        label.append(text); value.append(label);
      });
      box.append(title, value); list.append(box);
    });
  });
};
document.addEventListener('change', (event) => {
  if (event.target.matches('#jform_axes, #productgroup-form .js-input-value')) queueMicrotask(memberFields);
});
document.addEventListener('joomla:updated', () => queueMicrotask(memberFields));
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', memberFields, { once: true });
else memberFields();
