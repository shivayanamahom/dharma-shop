/** Native Joomla tab navigation follows the selected export format. No separate styling. */
(() => {
const initialise = async () => {
  const form = document.getElementById('profile-form');
  if (!form) return;
  await customElements.whenDefined('joomla-tab');
  const select = form.querySelector('[name="jform[format]"]');
  const tabs = form.querySelector('joomla-tab');
  const panel = form.querySelector('joomla-tab-element#columns');
  const formats = Joomla.getOptions('com_dharmashop_export.profile', {}).tableFormats || [];
  if (!select || !tabs || !panel) return;
  const update = () => {
    const visible = formats.includes(select.value);
    if (!visible && panel.hasAttribute('active')) tabs.activateTab(form.querySelector('joomla-tab-element#main'), false);
    panel.classList.toggle('hidden', !visible);
    form.querySelectorAll('button[aria-controls="columns"]').forEach((button) => { button.hidden = !visible || (button.getAttribute('role') !== 'tab' && tabs.getAttribute('view') !== 'accordion'); });
  };
  select.addEventListener('change', update);
  new MutationObserver(update).observe(tabs, { attributes: true, attributeFilter: ['view'] });
  update();
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise);
else initialise();
})();
