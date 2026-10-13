(() => {
  'use strict';

  const CHUNK_SIZE = 25;
  const text = (key, fallback) => window.Joomla?.Text?._(key) || fallback;
  let busy = false;

  const progressModal = () => document.getElementById('dharmashop-progress-modal');

  const makePanel = () => {
    const container = document.querySelector('[data-dharmashop-progress-container]');
    if (!container) return null;
    let panel = container.querySelector('[data-dharmashop-progress]');
    if (!panel) {
      panel = document.createElement('div');
      panel.dataset.dharmashopProgress = '';
      panel.className = 'alert alert-info mx-3 mb-3';
      panel.hidden = true;
      panel.innerHTML = '<div class="d-flex justify-content-between align-items-center gap-3"><span class="badge" data-progress-status></span><span data-progress-count></span></div><div class="progress mt-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar" data-progress-bar style="width:0%"></div></div><div class="mt-3" data-progress-detail></div><p class="mt-2 mb-0" data-progress-help></p>';
      panel.querySelector('.progress').setAttribute('aria-label', text('COM_DHARMASHOP_PROGRESS_BAR', 'Ход обработки'));
      container.append(panel);
    }
    window.bootstrap?.Modal.getOrCreateInstance(progressModal()).show();
    return panel;
  };

  const setStatus = (panel, state) => {
    const colors = { running: 'info', done: 'success', error: 'danger', review: 'warning' };
    const labels = {
      running: text('COM_DHARMASHOP_PROGRESS_RUNNING', 'Выполняется…'),
      done: text('COM_DHARMASHOP_PROGRESS_DONE', 'Завершено'),
      error: text('COM_DHARMASHOP_PROGRESS_ERROR', 'Обработка остановлена'),
      review: text('COM_DHARMASHOP_PROGRESS_REVIEW', 'Требуется подтверждение'),
    };
    panel.dataset.progressState = state;
    panel.classList.remove('alert-info', 'alert-success', 'alert-danger', 'alert-warning');
    panel.classList.add(`alert-${colors[state]}`);
    const status = panel.querySelector('[data-progress-status]');
    status.className = `badge text-bg-${colors[state]}`;
    status.textContent = labels[state];
    panel.setAttribute('aria-busy', String(state === 'running'));
    const bar = panel.querySelector('[data-progress-bar]');
    bar.classList.remove('bg-success', 'bg-danger', 'bg-warning');
    if (state !== 'running') {
      bar.classList.add(`bg-${colors[state]}`);
      bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
    }
    const modal = progressModal();
    const reload = modal?.querySelector('[data-progress-reload]');
    if (reload) {
      reload.hidden = state === 'running';
      reload.textContent = state === 'review'
        ? text('COM_DHARMASHOP_PROGRESS_CONFIRM', 'Перейти к подтверждению')
        : text('COM_DHARMASHOP_PROGRESS_RELOAD', 'Закрыть и обновить страницу');
    }
    const dismiss = modal?.querySelector('[data-progress-dismiss]');
    if (dismiss) {
      dismiss.hidden = state === 'running';
      dismiss.textContent = text('COM_DHARMASHOP_PROGRESS_DISMISS', 'Закрыть окно');
    }
    const close = modal?.querySelector('.btn-close');
    if (close) close.hidden = state === 'running';
    panel.querySelector('[data-progress-help]').textContent = state === 'done'
      ? text('COM_DHARMASHOP_PROGRESS_DONE_HELP', 'Обработка завершена. Обновление страницы покажет актуальные данные; операция уже выполнена.')
      : state === 'review'
        ? text('COM_DHARMASHOP_PROGRESS_REVIEW_HELP', 'Проверка завершена. Перейдите к результатам проверки и подтвердите удаление файлов.')
        : state === 'running'
          ? text('COM_DHARMASHOP_PROGRESS_RUNNING_HELP', 'Дождитесь завершения обработки.') : '';
  };

  const update = (panel, title, processed, total, unit, done, detail = '') => {
    if (!panel) return;
    panel.hidden = false;
    setStatus(panel, done ? 'done' : 'running');
    const titleNode = progressModal()?.querySelector('.modal-title');
    if (titleNode) titleNode.textContent = title;
    panel.querySelector('[data-progress-count]').textContent = total > 0 ? `${processed} / ${total} ${unit}` : `${processed} ${unit}`;
    const bar = panel.querySelector('[data-progress-bar]');
    const percent = done ? 100 : (total > 0 ? Math.min(100, Math.round(processed * 100 / total)) : 0);
    if (total > 0 || done) {
      bar.classList.toggle('progress-bar-striped', !done);
      bar.classList.toggle('progress-bar-animated', !done);
      bar.style.width = `${percent}%`;
      bar.textContent = `${percent}%`;
      bar.parentElement.setAttribute('aria-valuenow', String(percent));
    } else {
      bar.classList.add('progress-bar-striped', 'progress-bar-animated');
      bar.style.width = '100%';
      bar.textContent = '';
      bar.parentElement.removeAttribute('aria-valuenow');
    }
    panel.querySelector('[data-progress-detail]').textContent = detail;
  };

  const post = async (form, data) => {
    const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    let payload;
    try { payload = await response.json(); } catch (_) { throw new Error(text('COM_DHARMASHOP_PROGRESS_RESPONSE_ERROR', 'Сервер вернул неожиданный ответ. Обновите страницу и проверьте результат.')); }
    if (!response.ok || !payload.success) throw new Error(payload.error || text('COM_DHARMASHOP_PROGRESS_FAILED', 'Не удалось выполнить операцию.'));
    return payload;
  };

  const fieldsFrom = (host, root) => {
    const rows = [];
    const seen = new Set();
    const selector = '[name^="batch["], [name^="batch_prices["]';
    // The active dialog already contains the controls. Reading the entire document
    // can mix in controls from another open dialog and overwrite the user's choices.
    const controls = host?.querySelectorAll(selector) || root?.querySelectorAll?.(selector) || [];
    controls.forEach((field) => {
      if (field.disabled || !field.name) return;
      if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
      if (field.tagName === 'SELECT' && field.multiple) {
        Array.from(field.selectedOptions).forEach((option) => {
          const key = `${field.name}\u0000${option.value}`;
          if (!seen.has(key)) { seen.add(key); rows.push([field.name, option.value]); }
        });
      } else {
        const key = `${field.name}\u0000${field.value}`;
        if (!seen.has(key)) { seen.add(key); rows.push([field.name, field.value]); }
      }
    });
    return rows;
  };

  const runBatch = async (trigger) => {
    const form = document.querySelector('#adminForm');
    // Search both the batch block and the dialog's current document root. Joomla
    // moves inline dialog content out of the template before displaying it.
    const root = trigger.getRootNode();
    const host = trigger.closest('[data-dharmashop-batch-container]')
      || root.querySelector?.('[data-dharmashop-batch-container]');
    const panel = makePanel();
    const title = text('COM_DHARMASHOP_PROGRESS_BATCH', 'Пакетная обработка');
    const ids = Array.from(form.querySelectorAll('input[name="cid[]"]:checked')).map((input) => input.value);
    update(panel, title, 0, ids.length, text('COM_DHARMASHOP_PROGRESS_RECORDS', 'записей'), false);
    if (!ids.length) throw new Error(text('JGLOBAL_NO_ITEM_SELECTED', 'Сначала выберите записи в списке.'));

    const fields = fieldsFrom(host, root);
    const activeActions = fields.filter(([key, value]) => {
      const isAction = key.endsWith('[action]') || /\[(?:price|discount|period)_action\]$/.test(key);
      return isAction && value !== 'skip';
    });
    if (!activeActions.length) {
      throw new Error(text('COM_DHARMASHOP_BATCH_NO_ACTION', 'Выберите действие хотя бы в одном блоке пакетной обработки.'));
    }
    const base = new FormData(form);
    for (const key of Array.from(base.keys())) {
      if (key === 'task' || key === 'boxchecked' || key === 'batch_ajax' || key === 'cid[]' || key.startsWith('batch[') || key.startsWith('batch_prices[')) base.delete(key);
    }
    base.set('task', trigger.dataset.dharmashopBatchTask);
    base.set('batch_ajax', '1');

    for (let offset = 0; offset < ids.length; offset += CHUNK_SIZE) {
      const data = new FormData();
      for (const [key, value] of base.entries()) data.append(key, value);
      fields.forEach(([key, value]) => data.append(key, value));
      ids.slice(offset, offset + CHUNK_SIZE).forEach((id) => data.append('cid[]', id));
      const payload = await post(form, data);
      const processed = Math.min(ids.length, offset + Number(payload.processed || ids.slice(offset, offset + CHUNK_SIZE).length));
      update(panel, title, processed, ids.length, text('COM_DHARMASHOP_PROGRESS_RECORDS', 'записей'), processed >= ids.length);
    }
  };

  const runCommand = async (task) => {
    const form = document.querySelector('#adminForm');
    const panel = makePanel();
    const defaultTitle = text('COM_DHARMASHOP_PROGRESS_COMMAND', 'Выполнение команды');
    update(panel, defaultTitle, 0, 0, text('COM_DHARMASHOP_PROGRESS_RECORDS', 'записей'), false);
    const base = new FormData(form);
    for (const key of Array.from(base.keys())) if (key === 'task' || key === 'boxchecked' || key === 'cid[]') base.delete(key);
    base.set('task', task);
    base.set('batch_ajax', '1');
    let payload = { processed: 0, total: 0, unit: '' };
    do {
      const data = new FormData();
      for (const [key, value] of base.entries()) data.append(key, value);
      payload = await post(form, data);
      const extra = task === 'products.cmdWebp'
        ? `${text('COM_DHARMASHOP_CMD_WEBP_DONE_SHORT', 'Конвертировано')}: ${payload.converted}; ${text('COM_DHARMASHOP_CMD_WEBP_FAILED_SHORT', 'ошибок')}: ${payload.failed}`
        : task === 'products.cmdFacets'
          ? text('COM_DHARMASHOP_PROGRESS_FILTER_SCOPES_HELP', 'Учитываются категории и общий каталог без выбранной категории.') : '';
      update(panel, payload.title || defaultTitle, Number(payload.processed), Number(payload.total), payload.unit, Boolean(payload.done), extra);
      if (!payload.done) await new Promise((resolve) => window.setTimeout(resolve, 120));
    } while (!payload.done);
  };

  // Запуск экспорта из списка профилей (com_dharmashop_export): те же шаги, что у команд, но состояние хранится в самом запуске.
  const runExport = async (trigger) => {
    const form = document.querySelector('#adminForm');
    const panel = makePanel();
    const unit = text('COM_DHARMASHOP_PROGRESS_RECORDS', 'записей');
    const token = Array.from(form.querySelectorAll('input[type="hidden"]')).find((input) => input.value === '1' && /^[0-9a-f]{32}$/.test(input.name));
    const call = (task, id) => {
      const data = new FormData();
      data.set(token.name, '1');
      data.set('id', String(id));
      return post({ action: `index.php?option=com_dharmashop_export&task=${task}&format=json` }, data);
    };
    const summary = (payload) => `${text('COM_DHARMASHOP_EXPORT_PROGRESS_WRITTEN', 'Записано')}: ${payload.written}; ${text('COM_DHARMASHOP_EXPORT_PROGRESS_SKIPPED', 'пропущено')}: ${payload.skipped}`;
    update(panel, trigger.dataset.exportTitle || text('COM_DHARMASHOP_PROGRESS_COMMAND', 'Выполнение команды'), 0, 0, unit, false);
    let payload = await call('job.start', trigger.dataset.dharmashopExportRun);
    while (payload.state === 'running') {
      update(panel, payload.title, payload.processed, payload.total, unit, false, summary(payload));
      await new Promise((resolve) => window.setTimeout(resolve, 60));
      payload = await call('job.step', payload.id);
    }
    const detail = panel.querySelector('[data-progress-detail]');
    if (payload.state !== 'done') {
      update(panel, payload.title, payload.processed, payload.total, unit, false, summary(payload));
      setStatus(panel, 'error');
      detail.textContent = payload.error || text('COM_DHARMASHOP_PROGRESS_FAILED', 'Не удалось выполнить операцию.');
      return;
    }
    update(panel, payload.title, payload.processed, payload.total, unit, true, summary(payload));
    panel.querySelector('[data-progress-help]').textContent = text('COM_DHARMASHOP_EXPORT_PROGRESS_DONE_HELP', 'Выгрузка завершена.');
    payload.issues.forEach((line) => {
      const warning = document.createElement('div');
      warning.className = 'alert alert-warning mt-2 mb-0';
      warning.textContent = line;
      detail.append(warning);
    });
    const actions = document.createElement('div');
    actions.className = 'd-flex flex-wrap align-items-center gap-2 mt-3';
    if (payload.download) {
      const link = document.createElement('a');
      link.className = 'btn btn-success';
      link.href = payload.download;
      link.textContent = text('COM_DHARMASHOP_EXPORT_PROGRESS_DOWNLOAD', 'Скачать файл');
      actions.append(link);
    }
    if (payload.public) {
      const feed = document.createElement('a');
      feed.href = payload.public;
      feed.target = '_blank';
      feed.rel = 'noopener';
      feed.textContent = `${text('COM_DHARMASHOP_EXPORT_PROGRESS_FEED', 'Ссылка для площадки')}: ${payload.public}`;
      actions.append(feed);
    }
    detail.append(actions);
  };

  // Импорт в одном окне прогресса: проверка → сводка → подтверждение → применение. Каталог меняется только после нажатия «Применить».
  const runImport = async (trigger) => {
    const form = document.querySelector('#adminForm');
    const panel = makePanel();
    const unit = text('COM_DHARMASHOP_PROGRESS_RECORDS', 'записей');
    const token = Array.from(form.querySelectorAll('input[type="hidden"]')).find((input) => input.value === '1' && /^[0-9a-f]{32}$/.test(input.name));
    const active = ['reading', 'checking', 'media', 'finalizing', 'applying'];
    const call = (task, id) => {
      const data = new FormData();
      data.set(token.name, '1');
      data.set('id', String(id));
      return post({ action: `index.php?option=com_dharmashop_import&task=${task}&format=json` }, data);
    };
    const sleep = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));
    const detail = () => panel.querySelector('[data-progress-detail]');
    const addButton = (box, label, className) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = className;
      button.textContent = label;
      box.append(button);
      return button;
    };
    const addLink = (box, label, href, className) => {
      const link = document.createElement('a');
      link.className = className;
      link.href = href;
      link.textContent = label;
      box.append(link);
    };
    const line = (s, applied) => {
      const key = applied ? 'RES' : 'SUM';
      return [[`COM_DHARMASHOP_IMPORT_${key}_CREATE`, s.create], [`COM_DHARMASHOP_IMPORT_${key}_UPDATE`, s.update], [`COM_DHARMASHOP_IMPORT_${key}_UNCHANGED`, s.unchanged], ['COM_DHARMASHOP_IMPORT_SUM_ERRORS', s.errors]]
        .map(([k, n]) => `${text(k, k)}: ${n}`).join(' · ');
    };
    update(panel, trigger.dataset.importTitle || text('COM_DHARMASHOP_IMPORT_RESULT', 'Импорт'), 0, 0, unit, false);
    let payload = trigger.dataset.dharmashopImportStart
      ? await call('job.start', trigger.dataset.dharmashopImportStart)
      : { id: trigger.dataset.dharmashopImportRun, state: 'reading' };
    const id = payload.id;
    for (;;) {
      while (active.includes(payload.state)) {
        update(panel, payload.title || '', Number(payload.processed), Number(payload.total), unit, false, `${payload.phase} · ${text('COM_DHARMASHOP_IMPORT_ERRORS', 'Ошибок')}: ${payload.errors}`);
        await sleep(80);
        payload = await call('job.step', id);
      }
      const box = document.createElement('div');
      box.className = 'd-flex flex-wrap align-items-center gap-2 mt-3';
      if (payload.state === 'checked') {
        update(panel, payload.title, Number(payload.total), Number(payload.total), unit, true, line(payload.summary, false));
        setStatus(panel, 'review');
        panel.querySelector('[data-progress-help]').textContent = payload.errors
          ? (payload.canApply
            ? text('COM_DHARMASHOP_IMPORT_REVIEW_SKIP', 'Строки с ошибками (%d) будут пропущены, остальные записаны.').replace('%d', String(payload.errors))
            : text('COM_DHARMASHOP_IMPORT_REVIEW_ERRORS', 'Есть строки с ошибками: исправьте файл или разрешите пропуск ошибочных строк в профиле.'))
          : text('COM_DHARMASHOP_IMPORT_REVIEW_HELP', 'Проверьте сводку: каталог пока не изменён.');
        const toReview = progressModal()?.querySelector('[data-progress-reload]');
        if (toReview) toReview.hidden = true; // решение принимается здесь, перезагрузка страницы не нужна
        const apply = payload.canApply ? addButton(box, text('COM_DHARMASHOP_IMPORT_APPLY', 'Применить'), 'btn btn-success') : null;
        if (payload.errorsUrl) addLink(box, text('COM_DHARMASHOP_IMPORT_ERRORS_CSV', 'Скачать ошибки (CSV)'), payload.errorsUrl, 'btn btn-outline-danger');
        addLink(box, text('COM_DHARMASHOP_IMPORT_RESULT', 'Отчёт'), payload.url, 'btn btn-outline-secondary');
        detail().append(box);
        if (!apply) return;
        await new Promise((resolve) => apply.addEventListener('click', resolve, { once: true }));
        box.remove();
        payload = await call('job.approve', id);
        continue;
      }
      if (payload.state === 'failed') {
        update(panel, payload.title, Number(payload.processed), Number(payload.total), unit, false, payload.error);
        setStatus(panel, 'error');
        const resume = addButton(box, text('COM_DHARMASHOP_IMPORT_CONTINUE', 'Продолжить'), 'btn btn-primary');
        addLink(box, text('COM_DHARMASHOP_IMPORT_RESULT', 'Отчёт'), payload.url, 'btn btn-outline-secondary');
        detail().append(box);
        await new Promise((resolve) => resume.addEventListener('click', resolve, { once: true }));
        box.remove();
        payload = await call('job.resume', id);
        continue;
      }
      update(panel, payload.title, Number(payload.total), Number(payload.total), unit, payload.state === 'done', payload.summary ? line(payload.summary, true) : '');
      if (payload.state !== 'done') setStatus(panel, 'error');
      if (payload.errorsUrl) addLink(box, text('COM_DHARMASHOP_IMPORT_ERRORS_CSV', 'Скачать ошибки (CSV)'), payload.errorsUrl, 'btn btn-outline-danger');
      addLink(box, text('COM_DHARMASHOP_IMPORT_RESULT', 'Отчёт'), payload.url, 'btn btn-primary');
      detail().append(box);
      return;
    }
  };

  const runImageCommand = async (initialTask) => {
    const form = document.querySelector('#adminForm');
    const panel = makePanel();
    update(panel, text('COM_DHARMASHOP_PROGRESS_COMMAND', 'Выполнение команды'), 0, 0, text('COM_DHARMASHOP_PROGRESS_RECORDS', 'записей'), false);
    let task = initialTask;
    let payload;
    do {
      const data = new FormData(form);
      data.set('task', task);
      data.set('batch_ajax', '1');
      payload = await post(form, data);
      const detail = `${text('COM_DHARMASHOP_IMAGE_COMMAND_COUNT_FILES', 'Файлов')}: ${payload.files}; ${text('COM_DHARMASHOP_IMAGE_COMMAND_COUNT_DELETED', 'Удалено')}: ${payload.deleted}; ${text('COM_DHARMASHOP_IMAGE_COMMAND_COUNT_PROTECTED', 'Защищено')}: ${payload.protected}`;
      update(panel, payload.title || text('COM_DHARMASHOP_PROGRESS_COMMAND', 'Выполнение команды'), Number(payload.processed), Number(payload.total), payload.unit, Boolean(payload.done), detail);
      if (payload.phase === 'review' || payload.phase === 'done') {
        if (payload.phase === 'review') setStatus(panel, 'review');
        break;
      }
      task = 'imagecommands.proceed';
    } while (true);
  };

  document.addEventListener('click', async (event) => {
    const path = event.composedPath();
    const isElement = (node) => node?.nodeType === 1 && typeof node.matches === 'function';
    const batchTrigger = path.find((node) => isElement(node) && node.matches('[data-dharmashop-batch-task]'));
    const commandTrigger = path.find((node) => isElement(node) && node.localName === 'joomla-toolbar-button' && /^(products\.cmd|imagecommands\.(start|proceed|confirm))/.test(node.getAttribute('task') || ''));
    const importTrigger = path.find((node) => isElement(node) && node.matches('[data-dharmashop-import-run], [data-dharmashop-import-start]'));
    const exportTrigger = path.find((node) => isElement(node) && node.matches('[data-dharmashop-export-run]'));
    const reloadButton = path.find((node) => isElement(node) && node.matches('[data-progress-reload]'));
    const dismissButton = path.find((node) => isElement(node) && node.matches('[data-progress-dismiss]'));
    if (reloadButton) { event.preventDefault(); window.location.reload(); return; }
    if (dismissButton) {
      event.preventDefault();
      window.bootstrap?.Modal.getOrCreateInstance(progressModal()).hide();
      return;
    }
    if (!batchTrigger && !commandTrigger && !exportTrigger && !importTrigger) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    if (busy) return;
    busy = true;
    try {
      if (batchTrigger) await runBatch(batchTrigger);
      else if (exportTrigger) await runExport(exportTrigger);
      else if (importTrigger) await runImport(importTrigger);
      else if ((commandTrigger.getAttribute('task') || '').startsWith('imagecommands.')) await runImageCommand(commandTrigger.getAttribute('task'));
      else await runCommand(commandTrigger.getAttribute('task'));
    } catch (error) {
      const panel = makePanel();
      setStatus(panel, 'error');
      if (panel) {
        panel.hidden = false;
        panel.querySelector('[data-progress-detail]').textContent = error.message;
      }
    } finally { busy = false; }
  }, true);
  const autoImport = () => document.querySelector('[data-dharmashop-import-auto]')?.click();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoImport, { once: true });
  else autoImport();
})();
