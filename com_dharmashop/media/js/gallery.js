/**
 * Dharma Shop: галерея изображений для форм админки (несколько файлов).
 * Загрузка — тем же обработчиком, что и у одиночного загрузчика (токен формы Joomla, fetch/XHR); порядок — перетаскиванием
 * карточек (HTML5 Drag and Drop); первое изображение — главное. Значения — обычные поля формы jform[gallery][N][path|alt|title].
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

		const list = root.querySelector('.dharmashop-gallery__list');
		const template = root.querySelector('.dharmashop-gallery__tpl');
		const fileInput = root.querySelector('.dharmashop-gallery__file');
		const drop = root.querySelector('.dharmashop-gallery__drop');
		const progress = root.querySelector('.progress');
		const bar = root.querySelector('.progress-bar');
		const native = root.querySelector('.dharmashop-gallery__native');
		const name = root.dataset.name;
		const isFiles = root.dataset.kind === 'files';
		const isFieldGallery = root.dataset.kind === 'field-gallery';
		const isVideo = root.dataset.kind === 'video';
		const maxCount = parseInt(root.dataset.maxCount || '100', 10);
		const maxBytes = parseInt(root.dataset.maxBytes || '0', 10);
		const accept = (root.dataset.accept || '').split(',').filter(Boolean);
		const cleanPath = (value) => String(value || '').split('#')[0];

		// Имена полей по порядку карточек: первая — главное изображение.
		const renumber = () => {
			list.querySelectorAll('.dharmashop-gallery__item').forEach((item, index) => {
				item.draggable = true;
				item.querySelectorAll('[data-field]').forEach((field) => {
					field.name = name + '[' + index + '][' + field.dataset.field + ']';
				});
			});
		};

		const has = (path) => Array.from(list.querySelectorAll('[data-field="path"]')).some((input) => input.value === path);

		const addItem = (data) => {
			data = typeof data === 'string' ? { path: data } : (data || {});
			const path = isVideo && data.source !== 'local' ? String(data.url || data.path || '') : cleanPath(data.path);

			if (!path) {
				return;
			}

			const existing = Array.from(list.querySelectorAll('[data-field="path"]'))
				.find((input) => input.value === path)?.closest('.dharmashop-gallery__item');
			if (existing) {
				// Fill missing metadata when a service link is added again; keep edited captions.
				const title = existing.querySelector('[data-field="title"]');
				if (isVideo && data.title && title && !title.value.trim()) { title.value = data.title; }
				if (isVideo && data.poster) {
					const poster = existing.querySelector('.dharmashop-video__poster');
					const icon = existing.querySelector('.dharmashop-video__icon');
					existing.querySelector('[data-field="poster"]').value = data.poster;
					if (poster) {
						poster.src = root.dataset.root + data.poster.replace(/^\/+/, '');
						poster.hidden = false;
					}
					if (icon) { icon.hidden = true; }
				}
				return;
			}

			if (list.children.length >= maxCount) {
				showError(root.dataset.errorCount || root.dataset.errorServer);
				return;
			}

			const holder = document.createElement('div');
			holder.innerHTML = template.innerHTML.trim();
			const item = holder.firstElementChild;
			item.querySelector('[data-field="path"]').value = path;
			if (isFiles) {
				item.querySelector('.dharmashop-gallery__filename').textContent = data.filename || path.split('/').pop();
				item.querySelector('.dharmashop-gallery__details').textContent = (Number(data.bytes || 0) / 1024).toFixed(1) + ' KiB';
			} else if (isVideo) {
				item.querySelector('[data-field="path"]').value = data.source === 'local' || !data.source ? data.path || '' : data.url || path;
				item.querySelector('[data-field="poster"]').value = data.poster || '';
				item.querySelector('[data-field="mime"]').value = data.mime || (data.source && data.source !== 'local' ? 'application/vnd.dharmashop.video.' + data.source : '');
				item.querySelector('[data-field="source"]').value = data.source || 'local';
				item.querySelector('[data-field="url"]').value = data.source && data.source !== 'local' ? data.url || path : '';
				item.querySelector('[data-field="title"]').value = data.title || data.filename || '';
				const heading = item.querySelector('.dharmashop-gallery__filename');
				heading.textContent = data.source && data.source !== 'local' ? data.serviceName || data.source.toUpperCase() : data.filename || path.split('/').pop();
				const poster = item.querySelector('.dharmashop-video__poster');
				const icon = item.querySelector('.dharmashop-video__icon');
				if (data.poster && poster) {
					poster.src = root.dataset.root + data.poster.replace(/^\/+/, '');
					poster.hidden = false;
					if (icon) icon.hidden = true;
				}
			} else {
				item.querySelector('.dharmashop-gallery__thumb').src = root.dataset.root + path.replace(/^\/+/, '');
			}
			list.appendChild(item);
			renumber();
		};

		const setProgress = (done, total, fraction) => {
			progress.hidden = total === 0 || done >= total;
			bar.style.width = Math.round(((done + fraction) / Math.max(total, 1)) * 100) + '%';
		};

		const uploadOne = (file, done, total) => new Promise((resolve) => {
			const extension = (file.name.split('.').pop() || '').toLowerCase();

			if (accept.length && !accept.includes(extension)) {
				showError(root.dataset.errorType);
				resolve();

				return;
			}

			if (maxBytes && file.size > maxBytes) {
				showError(root.dataset.errorSize);
				resolve();

				return;
			}

			const data = new FormData();
			data.append('file', file);
			if (isFiles || isFieldGallery || isVideo) {
				data.append('field_id', root.dataset.fieldId);
				if (isFiles || isVideo) { data.append('entity_id', root.dataset.entityId || '0'); }
				if (isFiles || isVideo) {
					const title = root.closest('form')?.querySelector('[name="jform[title]"]');
					data.append('entity_title', title ? title.value : '');
				}
			} else { data.append('folder', root.dataset.folder); }
			data.append(root.dataset.token, '1');

			const xhr = new XMLHttpRequest();
			xhr.open('POST', root.dataset.url);
			xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			xhr.upload.addEventListener('progress', (event) => {
				if (event.lengthComputable) {
					setProgress(done, total, event.loaded / event.total);
				}
			});
			xhr.addEventListener('load', () => {
				let response = null;

				try {
					response = JSON.parse(xhr.responseText);
				} catch (e) {
					showError(root.dataset.errorServer);
					resolve();

					return;
				}

				if (!response || !response.success || !response.data || !response.data.path) {
					showError((response && response.message) || root.dataset.errorServer);
				} else {
					addItem(isFiles || isVideo ? response.data : response.data.path);
				}

				resolve();
			});
			xhr.addEventListener('error', () => {
				showError(root.dataset.errorServer);
				resolve();
			});
			xhr.send(data);
		});

		let busy = false;

		// Файлы грузятся по очереди, прогресс общий.
		const uploadAll = async (files) => {
			files = Array.from(files || []).filter((file) => file && (isFiles || (isVideo
				? (file.type.startsWith('video/') || /\.(mp4|webm|ogv)$/i.test(file.name))
				: (!file.type || file.type.startsWith('image/') || /\.(avif|webp|png|jpe?g|gif|bmp|svg)$/i.test(file.name)))));

			if (!files.length || busy) {
				return;
			}

			busy = true;
			root.classList.add('is-busy');

			for (let i = 0; i < files.length; i++) {
				if (list.children.length >= maxCount) { showError(root.dataset.errorCount || root.dataset.errorServer); break; }
				setProgress(i, files.length, 0);
				await uploadOne(files[i], i, files.length);
			}

			setProgress(files.length, files.length, 0);
			root.classList.remove('is-busy');
			busy = false;
		};

		const fromBlob = (blob, type) => {
			const extension = ({ 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'image/gif': 'gif', 'image/avif': 'avif' })[type] || 'png';
			const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14);

			return new File([blob], 'clipboard-' + stamp + '.' + extension, { type });
		};

		const choose = root.querySelector('.dharmashop-gallery__choose');
		const pasteButton = root.querySelector('.dharmashop-gallery__paste');
		const server = root.querySelector('.dharmashop-gallery__server');
		const addVideo = root.querySelector('.dharmashop-video__add');
		if (isVideo && addVideo) {
			const providerSelect = root.querySelector('.dharmashop-video__provider');
			const videoUrlInput = root.querySelector('.dharmashop-video__url');
			const detectProvider = (value) => {
				try {
					const host = new URL(value).hostname.toLowerCase().replace(/^www\./, '');
					if (['youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com'].includes(host)) { return 'youtube'; }
					if (host === 'rutube.ru' || host.endsWith('.rutube.ru') || host === 'rutube.com' || host.endsWith('.rutube.com')) { return 'rutube'; }
					if (['vk.com', 'm.vk.com', 'vkvideo.ru'].includes(host)) { return 'vk'; }
				} catch (e) { /* Keep the manually selected provider until a valid URL is entered. */ }
				return '';
			};
			const syncProvider = () => {
				const detected = detectProvider(videoUrlInput.value.trim());
				if (detected) { providerSelect.value = detected; }
			};
			videoUrlInput.addEventListener('input', syncProvider);
			videoUrlInput.addEventListener('change', syncProvider);
			addVideo.addEventListener('click', async () => {
				const url = videoUrlInput.value.trim();
				syncProvider();
				const provider = providerSelect.value;
				if (!url) { showError(root.dataset.errorUrl || root.dataset.errorServer); return; }
				const payload = new FormData();
				payload.append('field_id', root.dataset.fieldId);
				payload.append('provider', provider);
				payload.append('url', url);
				payload.append(root.dataset.token, '1');
				addVideo.disabled = true;
				try {
					const response = await fetch(root.dataset.resolveUrl, { method: 'POST', body: payload, credentials: 'same-origin' }).then((result) => result.json());
					if (!response || !response.success || !response.data || !response.data.url) { showError((response && response.message) || root.dataset.errorServer); return; }
					addItem({ ...response.data, source: provider, mime: 'application/vnd.dharmashop.video.' + provider });
					root.querySelector('.dharmashop-video__url').value = '';
				} catch (e) { showError(root.dataset.errorServer); }
				finally { addVideo.disabled = false; }
			});
		}
		const isMac = /Mac|iPhone|iPad/i.test(navigator.platform || navigator.userAgent);

		if (isMac && pasteButton) {
			pasteButton.textContent = pasteButton.textContent.replace('Ctrl+V', '\u2318V');
		}

		choose.addEventListener('click', (event) => {
			event.stopPropagation();
			fileInput.click();
		});
		fileInput.addEventListener('change', () => {
			uploadAll(fileInput.files);
			fileInput.value = '';
		});
		drop.addEventListener('keydown', (event) => {
			if (event.target === drop && (event.key === 'Enter' || event.key === ' ')) {
				event.preventDefault();
				fileInput.click();
			}
		});

		// Вставка из буфера: Ctrl+V / ⌘V при фокусе внутри поля.
		root.addEventListener('paste', (event) => {
			if (event.target && event.target.matches && event.target.matches('input[type="text"]')) {
				return;
			}

			const files = event.clipboardData && event.clipboardData.files ? Array.from(event.clipboardData.files) : [];
			const images = files.filter((file) => isFiles || file.type.startsWith('image/'));

			if (images.length) {
				event.preventDefault();
				uploadAll(images.map((file) => (isFiles || (file.name && !/^image\.(png|jpe?g)$/i.test(file.name)) ? file : fromBlob(file, file.type))));
			}
		});

		if (pasteButton) {
			pasteButton.addEventListener('click', async (event) => {
				event.stopPropagation();

				if (!navigator.clipboard || typeof navigator.clipboard.read !== 'function') {
					showError(root.dataset.errorPasteUnsupported);

					return;
				}

				try {
					const found = [];

					for (const item of await navigator.clipboard.read()) {
						const type = item.types.find((t) => t.startsWith('image/'));

						if (type) {
							found.push(fromBlob(await item.getType(type), type));
						}
					}

					if (found.length) {
						uploadAll(found);
					} else {
						showError(root.dataset.errorPasteEmpty);
					}
				} catch (e) {
					showError(root.dataset.errorPasteDenied);
				}
			});
		}

		['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (event) => {
			if (event.dataTransfer && Array.from(event.dataTransfer.types || []).includes('Files')) {
				event.preventDefault();
				root.classList.add('is-dragover');
			}
		}));
		['dragleave', 'dragend'].forEach((type) => drop.addEventListener(type, () => root.classList.remove('is-dragover')));
		drop.addEventListener('drop', (event) => {
			event.preventDefault();
			root.classList.remove('is-dragover');
			uploadAll(event.dataTransfer && event.dataTransfer.files);
		});

		// «Выбрать с сервера»: штатное окно выбора файла Joomla; следим только за значением штатного поля и добавляем выбранное.
		const nativeInput = native ? native.querySelector('input[type="text"], input.field-media-input') : null;
		const nativeButton = native ? native.querySelector('.button-select') : null;

		if (server && nativeInput && nativeButton) {
			server.addEventListener('click', (event) => {
				event.stopPropagation();
				nativeInput.value = '';
				const started = Date.now();

				nativeButton.click();

				const timer = window.setInterval(() => {
					if (!document.body.contains(root) || Date.now() - started > 600000) {
						window.clearInterval(timer);

						return;
					}

					const value = cleanPath(nativeInput.value);

					if (value !== '') {
						window.clearInterval(timer);
						if (isFiles || isVideo) {
							const data = new FormData();
							data.append('path', value);
							data.append('field_id', root.dataset.fieldId);
							data.append(root.dataset.token, '1');
							fetch(root.dataset.selectUrl, { method: 'POST', body: data, credentials: 'same-origin' })
								.then((response) => response.json()).then((response) => {
									if (response.success && response.data && response.data.path) { addItem(response.data); }
									else { showError(response.message || root.dataset.errorServer); }
								}).catch(() => showError(root.dataset.errorServer));
						} else { addItem(value); }
						nativeInput.value = '';
					}
				}, 300);
			});
		} else if (server) {
			server.hidden = true;
		}

		// Убрать изображение из галереи (файл удаляется с диска при сохранении товара, если больше нигде не используется).
		list.addEventListener('click', (event) => {
			const move = event.target.closest('.dharmashop-gallery__up, .dharmashop-gallery__down');
			if (move) {
				const item = move.closest('.dharmashop-gallery__item');
				const up = move.classList.contains('dharmashop-gallery__up');
				const other = up ? item.previousElementSibling : item.nextElementSibling;
				if (other) { list.insertBefore(item, up ? other : other.nextSibling); renumber(); }
				return;
			}
			const button = event.target.closest('.dharmashop-gallery__remove');

			if (button) {
				button.closest('.dharmashop-gallery__item').remove();
				renumber();
			}
		});

		// Порядок: перетаскивание карточек.
		let dragging = null;

		list.addEventListener('dragstart', (event) => {
			const item = event.target.closest && event.target.closest('.dharmashop-gallery__item');

			if (!item || event.target.matches('input')) {
				return;
			}

			dragging = item;
			item.classList.add('is-dragging');
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', root.id || name);
		});
		list.addEventListener('dragover', (event) => {
			if (!dragging) {
				return;
			}

			event.preventDefault();
			const over = event.target.closest('.dharmashop-gallery__item');

			if (!over || over === dragging) {
				return;
			}

			const rect = over.getBoundingClientRect();
			const after = (event.clientX - rect.left) > rect.width / 2;
			list.insertBefore(dragging, after ? over.nextSibling : over);
		});
		list.addEventListener('drop', (event) => {
			if (dragging) {
				event.preventDefault();
			}
		});
		list.addEventListener('dragend', () => {
			if (dragging) {
				dragging.classList.remove('is-dragging');
				dragging = null;
				renumber();
			}
		});

		renumber();
	};

	const boot = () => document.querySelectorAll('.dharmashop-gallery').forEach(init);

	document.addEventListener('joomla:updated', boot);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
