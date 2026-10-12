(() => {
  'use strict';

  const messageSaved = 'com_dharmashop.quick-created';
  const completion = document.querySelector('[data-quick-create-complete]');
  if (completion && window.parent !== window) {
    window.parent.postMessage({ messageType: messageSaved }, window.location.origin);
  }

  const modal = document.getElementById('dharmashop-quick-fields-modal');
  if (!modal) return;

  // Joomla inserts the iframe when the modal opens.
  const getIframe = () => modal.querySelector('iframe');
  const getEditor = () => {
    const iframe = getIframe();
    const form = iframe?.contentDocument?.querySelector('form.form-validate');
    if (!form) return null;
    const view = new URL(form.action, window.location.href).searchParams.get('view');
    return ['field', 'fieldgroup'].includes(view) ? { iframe, form, view } : null;
  };
  const updateFooter = () => {
    const editing = Boolean(getEditor());
    modal.querySelectorAll('[data-quick-create-kind]').forEach((button) => { button.hidden = editing; });
    modal.querySelectorAll('[data-quick-editor-save], [data-quick-editor-back]').forEach((button) => {
      button.hidden = !editing;
    });
  };

  modal.addEventListener('shown.bs.modal', () => {
    getIframe()?.addEventListener('load', updateFooter);
    updateFooter();
  });
  modal.addEventListener('click', (event) => {
    const create = event.target.closest('[data-quick-create-kind]');
    if (create?.dataset.url && getIframe()) getIframe().src = create.dataset.url;

    const save = event.target.closest('[data-quick-editor-save]');
    const back = event.target.closest('[data-quick-editor-back]');
    if (!save && !back) return;
    const editor = getEditor();
    if (editor) {
      editor.iframe.contentWindow.Joomla.submitbutton(`${editor.view}.${save ? 'save' : 'cancel'}`, `#${editor.form.id}`, Boolean(save));
    }
  });

  window.addEventListener('message', (event) => {
    const iframe = getIframe();
    if (event.origin !== window.location.origin || !iframe || event.source !== iframe.contentWindow) return;
    if (event.data?.messageType !== messageSaved) return;
    // Only the successful server-side save page sends this message.
    modal.addEventListener('hidden.bs.modal', () => window.location.reload(), { once: true });
    window.bootstrap?.Modal.getOrCreateInstance(modal).hide();
  });

  if (document.querySelector('[data-quick-create-open-on-load]')) {
    window.setTimeout(() => window.bootstrap?.Modal.getOrCreateInstance(modal).show(), 0);
  }
})();
