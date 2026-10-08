/** Поиск по готовой форме настроек магазина; локально, без изменения значений и showon. C72. */
(() => {
    'use strict';

    const start = async () => {
        const widget = document.querySelector('[data-dharmashop-settings-search]');
        const tabs = document.querySelector('#component-form #configTabs');
        // В настройках нашего магазина список других компонентов занимает лишнюю колонку.
        const sidebar = document.querySelector('#component-form > .main-card-columns > #sidebar');
        const config = document.querySelector('#component-form > .main-card-columns > #config');
        if (sidebar && config) {
            sidebar.hidden = true;
            sidebar.classList.add('d-none');
            config.classList.remove('col-md-9');
            config.classList.add('col-12');
        }
        if (!widget || !tabs || widget.dataset.ready) return;
        await customElements.whenDefined('joomla-tab');
        widget.dataset.ready = '1';
        tabs.before(widget);
        widget.hidden = false;

        const input = widget.querySelector('input');
        const clear = widget.querySelector('[data-settings-clear]');
        const results = widget.querySelector('[data-settings-results]');
        const status = widget.querySelector('[data-settings-status]');
        const more = widget.querySelector('[data-settings-more]');
        const options = Joomla.getOptions('com_dharmashop.settingssearch', {});
        const normalize = (text) => text.toLocaleLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim();
        const text = (element) => element?.textContent?.replace(/\s+/g, ' ').trim() || '';
        const titleOf = (group) => text(group?.querySelector(':scope > .control-label label, :scope > .visually-hidden label'))
            .replace(/\s*\*\s*$/, '');
        const visible = (element) => !!element?.getClientRects().length;
        let records = [];
        let matches = [];
        let limit = 20;
        let refreshFrame = 0;
        let highlightCleanup = null;

        const conditionNodes = (group) => {
            const nodes = [];
            let node = group;
            while (node && node !== tabs) {
                if (node.matches('[data-showon]')) nodes.push(node);
                node = node.parentElement;
            }
            return nodes;
        };
        // Не проверяем видимость самой вкладки: поля других вкладок тоже должны находиться.
        const unavailable = (record) => conditionNodes(record.group).some((node) =>
            node.hidden || node.classList.contains('hidden') || node.classList.contains('d-none')
                || getComputedStyle(node).display === 'none');

        const buildIndex = () => {
            records = [];
            tabs.querySelectorAll('.control-group').forEach((group) => {
                const title = titleOf(group);
                const pane = group.closest('joomla-tab-element');
                if (!title || !pane) return;
                const path = [];
                let node = group.parentElement;
                while (node && node !== tabs) {
                    let name = '';
                    if (node.matches('fieldset')) name = text(node.querySelector(':scope > legend'));
                    else if (node.matches('joomla-tab-element')) name = node.getAttribute('name') || '';
                    else if (node.matches('.subform-repeatable-group')) {
                        const rowTitle = node.querySelector('input[name$="[label]"], input[name$="[title]"]');
                        const siblings = Array.from(node.parentElement.children).filter((child) => child.matches('.subform-repeatable-group'));
                        name = rowTitle?.value?.trim() || options.row.replace('%s', String(siblings.indexOf(node) + 1));
                    } else if (node.matches('.control-group')) name = titleOf(node);
                    if (name && path[0] !== name) path.unshift(name);
                    node = node.parentElement;
                }
                const description = Array.from(group.querySelectorAll('.form-text')).filter((item) => item.closest('.control-group') === group)
                    .map(text).join(' ');
                const control = Array.from(group.querySelectorAll('input:not([type="hidden"]), select, textarea, button'))
                    .find((item) => item.closest('.control-group') === group);
                const record = { group, pane, title, path: path.join(' → '), description, control };
                record.label = normalize(title);
                record.search = normalize([title, record.path, description].join(' '));
                records.push(record);
            });
        };

        const conditions = (record) => {
            const parts = [];
            const controls = [];
            conditionNodes(record.group).forEach((node) => {
                let rules;
                try { rules = JSON.parse(node.dataset.showon); } catch { return; }
                rules.forEach((rule) => {
                    const source = tabs.querySelector(`[name="${CSS.escape(rule.field)}"], [name="${CSS.escape(rule.field + '[]')}"]`);
                    if (!source) return;
                    controls.push(source);
                    const title = titleOf(source.closest('.control-group')) || rule.field;
                    const values = rule.values.map((value) => {
                        if (source.matches('select')) return text(Array.from(source.options).find((option) => option.value === value)) || value;
                        const radio = tabs.querySelector(`input[name="${CSS.escape(rule.field)}"][value="${CSS.escape(value)}"]`);
                        return radio ? text(radio.labels?.[0]) || value : value;
                    }).join(' / ');
                    parts.push(`${title} ${rule.sign || '='} ${values}`);
                });
            });
            return { text: parts.join('; '), controls };
        };

        const open = (record) => {
            if (!record.group.isConnected) { buildIndex(); render(); return; }
            const panes = [];
            let pane = record.pane;
            while (pane) {
                panes.unshift(pane);
                pane = pane.parentElement.closest('joomla-tab-element');
            }
            panes.forEach((item) => {
                const owner = item.parentElement;
                // Активную вкладку в мобильном accordion повторно не закрываем.
                if (!item.hasAttribute('active')) owner.activateTab(item, false);
            });
            let ancestor = record.group.parentElement;
            while (ancestor && ancestor !== tabs) {
                if (ancestor.matches('details')) ancestor.open = true;
                ancestor = ancestor.parentElement;
            }
            const blocked = unavailable(record);
            const dependency = blocked ? conditions(record) : { text: '', controls: [] };
            let target = record.group;
            if (blocked) {
                const source = dependency.controls.find(visible);
                target = source?.closest('.control-group') || record.group.closest('fieldset') || record.pane;
            }
            status.textContent = `${record.path} → ${record.title}`
                + (blocked ? ` — ${options.condition} ${dependency.text || options.unavailable}` : '');
            results.hidden = true;
            more.hidden = true;
            input.setAttribute('aria-expanded', 'false');

            requestAnimationFrame(() => {
                if (highlightCleanup) highlightCleanup();
                const classes = ['border', 'border-primary', 'rounded', 'bg-primary-subtle'];
                const added = classes.filter((className) => !target.classList.contains(className));
                target.classList.add(...added);
                const focus = blocked ? dependency.controls.find((item) => visible(item) && !item.disabled)
                    : Array.from(record.group.querySelectorAll('input:not([type="hidden"]), select, textarea, button'))
                        .find((item) => visible(item) && !item.disabled);
                if (focus) focus.focus({ preventScroll: true });
                else {
                    const old = target.getAttribute('tabindex');
                    target.setAttribute('tabindex', '-1');
                    target.focus({ preventScroll: true });
                    if (old === null) target.removeAttribute('tabindex');
                    else target.setAttribute('tabindex', old);
                }
                target.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                const timer = setTimeout(() => {
                    target.classList.remove(...added);
                    highlightCleanup = null;
                }, 3000);
                highlightCleanup = () => { clearTimeout(timer); target.classList.remove(...added); };
            });
        };

        const render = () => {
            const tokens = normalize(input.value).split(' ').filter(Boolean);
            clear.disabled = !input.value;
            results.replaceChildren();
            if (!tokens.length) {
                matches = [];
                results.hidden = more.hidden = true;
                status.textContent = '';
                input.setAttribute('aria-expanded', 'false');
                return;
            }
            matches = records.filter((record) => tokens.every((token) => record.search.includes(token)))
                .map((record) => ({ record, score: tokens.reduce((sum, token) => sum + (record.label.startsWith(token) ? 3 : record.label.includes(token) ? 2 : 0), 0) }))
                .sort((a, b) => b.score - a.score).map((hit) => hit.record);
            status.textContent = matches.length ? options.count.replace('%s', String(matches.length)) : options.empty;
            results.hidden = !matches.length;
            more.hidden = matches.length <= limit;
            input.setAttribute('aria-expanded', matches.length ? 'true' : 'false');
            matches.slice(0, limit).forEach((record) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action text-start';
                const title = document.createElement('div');
                title.className = 'fw-semibold';
                title.textContent = record.title;
                const path = document.createElement('div');
                path.className = 'small text-body-secondary';
                path.textContent = record.path;
                button.append(title, path);
                if (record.description) {
                    const description = document.createElement('div');
                    description.className = 'small mt-1';
                    description.textContent = record.description.length > 180 ? record.description.slice(0, 177) + '…' : record.description;
                    button.append(description);
                }
                if (unavailable(record)) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-warning-subtle text-body mt-1';
                    badge.textContent = options.conditional;
                    button.append(badge);
                }
                button.addEventListener('click', () => open(record));
                button.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') { event.preventDefault(); clear.click(); }
                    else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        const next = event.key === 'ArrowDown' ? button.nextElementSibling : button.previousElementSibling;
                        if (next) next.focus(); else input.focus();
                    }
                });
                results.append(button);
            });
        };
        const refresh = () => {
            cancelAnimationFrame(refreshFrame);
            refreshFrame = requestAnimationFrame(() => { buildIndex(); if (input.value && !results.hidden) render(); });
        };
        buildIndex();
        input.addEventListener('input', () => { limit = 20; render(); });
        input.addEventListener('focus', () => { if (input.value) render(); });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); if (matches[0]) open(matches[0]); }
            else if (event.key === 'ArrowDown') { event.preventDefault(); results.firstElementChild?.focus(); }
            else if (event.key === 'Escape') { event.preventDefault(); clear.click(); }
        });
        clear.addEventListener('click', () => { input.value = ''; render(); input.focus(); });
        more.addEventListener('click', () => { limit += 20; render(); });
        // Добавленные/удалённые строки subform и переименованные поля обновляют индекс без запроса на сервер.
        new MutationObserver(refresh).observe(tabs, { childList: true, subtree: true });
        tabs.addEventListener('input', refresh);
        tabs.addEventListener('change', refresh);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
