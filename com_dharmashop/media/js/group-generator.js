/** Native Joomla subforms and submit actions; only draft preview and token insertion are client-side. */
const init = () => {
  const panel = document.querySelector('[data-group-generator]');
  if (!panel) return;
  const template = document.querySelector('#jform_title_template');
  const groupTitle = document.querySelector('#jform_title');
  const axes = document.querySelector('#jform_axes');
  const subform = panel.querySelector('joomla-field-subform.dharma-generator-rows');
  const snapshot = () => [...axes.selectedOptions].map((option) => option.value).join(',');
  const initialAxes = snapshot();
  const update = () => {
    const changed = snapshot() !== initialAxes;
    panel.querySelector('[data-generator-axes-notice]').hidden = !changed;
    panel.querySelector('[data-generator-prepare]').disabled = changed || !subform;
    const rows = subform?.getRows() || [];
    panel.querySelector('[data-generator-create]').disabled = changed || rows.length === 0;
    const counter = panel.querySelector('[data-generator-count]');
    if (counter) counter.textContent = Joomla.Text._('COM_DHARMASHOP_GENERATOR_COUNT').replace('%s', String(rows.length));
    const clear = panel.querySelector('[data-generator-clear]');
    if (clear) clear.disabled = rows.length === 0;
    rows.forEach((row) => {
      const replacements = { '{group.title}': groupTitle.value };
      const values = [];
      row.querySelectorAll('select[name*="[axis_"]').forEach((field) => {
        const id = field.name.match(/\[axis_(\d+)\]/)?.[1];
        const text = field.value ? (field.selectedOptions[0]?.textContent || '') : '';
        replacements[`{field.${id}}`] = text;
        if (text) values.push(text);
      });
      replacements['{variant.values}'] = values.join(' ');
      const manual = row.querySelector('input[name$="[custom_title]"]');
      const preview = row.querySelector('input[name$="[title_preview]"]');
      const formula = template.value.trim() || '{group.title} {variant.values}';
      preview.value = manual.value.trim() || formula.replace(/\{[^{}]+\}/g, (token) => replacements[token] ?? token).replace(/\s+/g, ' ').trim();
    });
  };
  panel.addEventListener('click', (event) => {
    const token = event.target.closest('[data-generator-token]');
    if (token) {
      template.focus();
      const before = template.value.slice(0, template.selectionStart);
      const after = template.value.slice(template.selectionEnd);
      const prefix = /[\p{L}\p{N}}]$/u.test(before) ? ' ' : '';
      const suffix = /^[\p{L}\p{N}{]/u.test(after) ? ' ' : '';
      template.setRangeText(prefix + token.dataset.generatorToken + suffix, template.selectionStart, template.selectionEnd, 'end');
      template.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (event.target.closest('[data-generator-clear]') && subform) {
      if (window.confirm(Joomla.Text._('COM_DHARMASHOP_GENERATOR_CLEAR_CONFIRM'))) {
        subform.getRows().reverse().forEach((row) => row.querySelector('.group-remove')?.click());
      }
    }
  });
  document.querySelector('#productgroup-form').addEventListener('input', update);
  document.querySelector('#productgroup-form').addEventListener('change', update);
  subform?.addEventListener('subform-row-add', (event) => {
    const row = event.detail.row;
    [['price', 'generation_price'], ['stock_qty', 'generation_stock'], ['product_state', 'generation_state']].forEach(([name, source]) => {
      const field = row.querySelector(`[name$="[${name}]"]`);
      const defaults = document.querySelector(`#jform_${source}`);
      if (field && defaults) field.value = defaults.value;
    });
  });
  document.addEventListener('joomla:updated', () => queueMicrotask(update));
  if (subform) new MutationObserver(update).observe(subform, { childList: true });
  customElements.whenDefined('joomla-field-subform').then(update);
  update();
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
else init();
