/**
 * Dharma Shop: загрузчик изображений для форм админки.
 * Работает на обычных средствах браузера (fetch/XHR, Drag and Drop) и токене формы Joomla; от внутренних скриптов
 * медиа-менеджера Joomla не зависит. Если скрипт не загрузился, значение поля (путь к файлу) остаётся обычным текстом в скрытом поле.
 */
(() => {
	'use strict';

	const showError = (message) => {
		if (window.Joomla && typeof Joomla.renderMessages === 'function') {
			Joomla.renderMessages({ error: [message] });
		} else {
			window.alert(message);
		}
	};

	const init = (root) => {
		if (root.dataset.ready === '1') {
			return;
		}

		root.dataset.ready = '1';

		const input = root.querySelector('.dharmashop-uploader__input');
		const fileInput = root.querySelector('.dharmashop-uploader__file');
		const drop = root.querySelector('.dharmashop-uploader__drop');
		const preview = root.querySelector('.dharmashop-uploader__preview');
		const hint = root.querySelector('.dharmashop-uploader__hint');
		const path = root.querySelector('.dharmashop-uploader__path');
		const server = root.querySelector('.dharmashop-uploader__server');
		const native = root.querySelector('.dharmashop-uploader__native');
		const remove = root.querySelector('.dharmashop-uploader__remove');
		const progress = root.querySelector('.progress');
		const bar = root.querySelector('.progress-bar');
		const maxBytes = parseInt(root.dataset.maxBytes || '0', 10);
		const accept = (root.dataset.accept || '').split(',').filter(Boolean);

		const cleanPath = (value) => String(value || '').split('#')[0];

		const render = () => {
			const value = cleanPath(input.value);
			const has = value !== '';

			preview.hidden = !has;
			hint.hidden = has;
			remove.disabled = !has;
			path.value = value;

			if (has) {
				preview.src = root.dataset.root + value.replace(/^\/+/, '');
			}
		};

		const setBusy = (busy) => {
			root.classList.toggle('is-busy', busy);
			progress.hidden = !busy;
			bar.style.width = '0%';
		};

		const upload = (file) => {
			const extension = (file.name.split('.').pop() || '').toLowerCase();

			if (accept.length && !accept.includes(extension)) {
				showError(root.dataset.errorType);

				return;
			}

			if (maxBytes && file.size > maxBytes) {
				showError(root.dataset.errorSize);

				return;
			}

			const data = new FormData();
			data.append('file', file);
			data.append('folder', root.dataset.folder);
			data.append(root.dataset.token, '1');

			const xhr = new XMLHttpRequest();
			xhr.open('POST', root.dataset.url);
			xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			xhr.upload.addEventListener('progress', (event) => {
				if (event.lengthComputable) {
					bar.style.width = Math.round((event.loaded / event.total) * 100) + '%';
				}
			});
			xhr.addEventListener('load', () => {
				setBusy(false);
				let response = null;

				try {
					response = JSON.parse(xhr.responseText);
				} catch (e) {
					showError(root.dataset.errorServer);

					return;
				}

				if (!response || !response.success || !response.data || !response.data.path) {
					showError((response && response.message) || root.dataset.errorServer);

					return;
				}

				input.value = response.data.path;
				input.dispatchEvent(new Event('change', { bubbles: true }));
				render();
			});
			xhr.addEventListener('error', () => {
				setBusy(false);
				showError(root.dataset.errorServer);
			});

			setBusy(true);
			xhr.send(data);
		};

		const pick = (files) => {
			if (files && files.length && !root.classList.contains('is-busy')) {
				upload(files[0]);
			}
		};

		drop.addEventListener('click', () => fileInput.click());
		drop.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				fileInput.click();
			}
		});
		fileInput.addEventListener('change', () => {
			pick(fileInput.files);
			fileInput.value = '';
		});

		['dragenter', 'dragover'].forEach((name) => drop.addEventListener(name, (event) => {
			event.preventDefault();
			root.classList.add('is-dragover');
		}));
		['dragleave', 'dragend'].forEach((name) => drop.addEventListener(name, () => root.classList.remove('is-dragover')));
		drop.addEventListener('drop', (event) => {
			event.preventDefault();
			root.classList.remove('is-dragover');
			pick(event.dataTransfer && event.dataTransfer.files);
		});

		// «Выбрать с сервера»: открываем штатное окно выбора файла Joomla (поле Media спрятано внутри нашего поля)
		// и забираем выбранный путь, когда он изменится. От внутреннего устройства окна не зависим: следим только за значением.
		const nativeInput = native ? native.querySelector('input[type="text"], input.field-media-input') : null;
		const nativeButton = native ? native.querySelector('.button-select') : null;

		if (server && nativeInput && nativeButton) {
			server.addEventListener('click', () => {
				const before = nativeInput.value;
				const started = Date.now();

				nativeButton.click();

				const timer = window.setInterval(() => {
					if (!document.body.contains(root) || Date.now() - started > 600000) {
						window.clearInterval(timer);

						return;
					}

					const value = cleanPath(nativeInput.value);

					if (nativeInput.value !== before && value !== '') {
						window.clearInterval(timer);
						input.value = value;
						input.dispatchEvent(new Event('change', { bubbles: true }));
						render();
					}
				}, 300);
			});
		} else if (server) {
			server.hidden = true;
		}

		remove.addEventListener('click', () => {
			input.value = '';
			input.dispatchEvent(new Event('change', { bubbles: true }));
			render();
		});

		render();
	};

	const boot = () => document.querySelectorAll('.dharmashop-uploader').forEach(init);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
