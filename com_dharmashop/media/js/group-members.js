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
