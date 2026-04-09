/* global document, window, CreatorReactorRoleImpersonation */
(function () {
	'use strict';

	var CFG = window.CreatorReactorRoleImpersonation;
	if (!CFG || typeof CFG !== 'object' || !CFG.ajaxUrl || !CFG.nonce) {
		return;
	}

	var STORAGE_POS = 'creatorreactor_imp_panel_pos';
	var panel = null;
	var titlebar = null;
	var selectEl = null;
	var primaryBtn = null;
	var msgEl = null;
	var hintEl = null;
	var drag = null;

	function parsePos(raw) {
		try {
			var o = JSON.parse(raw);
			if (o && typeof o.left === 'number' && typeof o.top === 'number') {
				return { left: o.left, top: o.top };
			}
		} catch (e) { /* ignore */ }
		return null;
	}

	var PANEL_W = 200;

	function defaultPos() {
		return {
			left: Math.max(8, window.innerWidth - PANEL_W - 12),
			top: Math.max(8, window.innerHeight - 130)
		};
	}

	function clampPanel() {
		if (!panel) {
			return;
		}
		var rect = panel.getBoundingClientRect();
		var maxL = Math.max(8, window.innerWidth - rect.width - 8);
		var maxT = Math.max(8, window.innerHeight - rect.height - 8);
		var l = parseFloat(panel.style.left) || 0;
		var t = parseFloat(panel.style.top) || 0;
		panel.style.left = Math.min(Math.max(8, l), maxL) + 'px';
		panel.style.top = Math.min(Math.max(8, t), maxT) + 'px';
	}

	function applySavedPosition() {
		var saved = parsePos(window.localStorage.getItem(STORAGE_POS));
		var p = saved || defaultPos();
		panel.style.left = p.left + 'px';
		panel.style.top = p.top + 'px';
		clampPanel();
	}

	function savePosition() {
		if (!panel) {
			return;
		}
		var l = parseFloat(panel.style.left);
		var t = parseFloat(panel.style.top);
		window.localStorage.setItem(STORAGE_POS, JSON.stringify({ left: l, top: t }));
	}

	function setMsg(text) {
		if (!msgEl) {
			return;
		}
		msgEl.textContent = text || '';
		msgEl.style.display = text ? 'block' : 'none';
	}

	/** Non-empty impersonation slug from server (null/undefined/'' => not impersonating). */
	function serverImpersonationSlug() {
		var v = CFG.current;
		if (v === null || v === undefined) {
			return '';
		}
		var s = String(v).trim();
		return s;
	}

	function isImpersonating() {
		return serverImpersonationSlug().length > 0;
	}

	function renderMode() {
		var imp = isImpersonating();
		panel.classList.toggle('creatorreactor-imp--impersonating', imp);
		if (hintEl) {
			if (imp) {
				var label = (typeof CFG.currentLabel === 'string' && CFG.currentLabel !== '') ? CFG.currentLabel : serverImpersonationSlug();
				hintEl.textContent = (CFG.i18n.viewingAs ? CFG.i18n.viewingAs + ' ' : '') + label;
				hintEl.style.display = 'block';
			} else {
				hintEl.textContent = '';
				hintEl.style.display = 'none';
			}
		}
		if (imp) {
			selectEl.style.display = 'none';
			primaryBtn.textContent = CFG.i18n.stop;
			primaryBtn.className = 'creatorreactor-imp__btn creatorreactor-imp__btn--stop';
			primaryBtn.setAttribute('data-action', 'stop');
		} else {
			selectEl.style.display = '';
			primaryBtn.textContent = CFG.i18n.impersonate;
			primaryBtn.className = 'creatorreactor-imp__btn';
			primaryBtn.setAttribute('data-action', 'impersonate');
		}
	}

	function postRole(role) {
		setMsg('');
		primaryBtn.disabled = true;
		var body = new URLSearchParams();
		body.set('action', 'creatorreactor_impersonate_role');
		body.set('nonce', CFG.nonce);
		body.set('role', role || '');

		return fetch(CFG.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) { return r.json(); }).then(function (json) {
			if (!json || !json.success) {
				var m = (json && json.data && json.data.message) ? json.data.message : CFG.i18n.error;
				setMsg(m);
				return;
			}
			if (json.data && json.data.impersonating) {
				CFG.current = json.data.role || '';
			} else {
				CFG.current = '';
			}
			window.location.reload();
		}).catch(function () {
			setMsg(CFG.i18n.error);
		}).finally(function () {
			primaryBtn.disabled = false;
		});
	}

	function onPrimaryClick() {
		var act = primaryBtn.getAttribute('data-action');
		if (act === 'stop') {
			postRole('');
			return;
		}
		var slug = (selectEl.value || '').trim();
		if (!slug && typeof CFG.loggedOutSlug === 'string' && CFG.loggedOutSlug.length) {
			slug = CFG.loggedOutSlug;
		}
		if (!slug) {
			return;
		}
		postRole(slug);
	}

	function onTitleMouseDown(e) {
		if (e.button !== 0) {
			return;
		}
		var rect = panel.getBoundingClientRect();
		drag = {
			ox: e.clientX - rect.left,
			oy: e.clientY - rect.top
		};
		e.preventDefault();
	}

	function onDocMouseMove(e) {
		if (!drag) {
			return;
		}
		panel.style.left = (e.clientX - drag.ox) + 'px';
		panel.style.top = (e.clientY - drag.oy) + 'px';
		clampPanel();
	}

	function onDocMouseUp() {
		if (drag) {
			drag = null;
			savePosition();
		}
	}

	function build() {
		panel = document.createElement('div');
		panel.id = 'creatorreactor-role-impersonation-panel';
		panel.setAttribute('role', 'region');
		panel.setAttribute('aria-label', CFG.i18n.title);

		titlebar = document.createElement('div');
		titlebar.className = 'creatorreactor-imp__titlebar';

		var h = document.createElement('p');
		h.className = 'creatorreactor-imp__title';
		h.textContent = CFG.i18n.title;
		titlebar.appendChild(h);

		var body = document.createElement('div');
		body.className = 'creatorreactor-imp__body';

		hintEl = document.createElement('p');
		hintEl.className = 'creatorreactor-imp__hint';
		hintEl.style.display = 'none';

		selectEl = document.createElement('select');
		selectEl.className = 'creatorreactor-imp__select';
		selectEl.setAttribute('aria-label', CFG.i18n.title || '');

		var roles = Array.isArray(CFG.roles) ? CFG.roles : [];
		var fromServer = serverImpersonationSlug();
		var loggedOut = (typeof CFG.loggedOutSlug === 'string' && CFG.loggedOutSlug.length) ? CFG.loggedOutSlug : '';
		var slugsInList = {};
		for (var k = 0; k < roles.length; k++) {
			if (roles[k] && roles[k].slug) {
				slugsInList[roles[k].slug] = true;
			}
		}
		/** When not actively impersonating (no cookie), or unknown slug: default to Logged out. */
		var desired = (fromServer && slugsInList[fromServer]) ? fromServer : loggedOut;
		for (var i = 0; i < roles.length; i++) {
			var r = roles[i];
			if (!r || !r.slug) {
				continue;
			}
			var opt = document.createElement('option');
			opt.value = r.slug;
			opt.textContent = r.label || r.slug;
			opt.selected = desired === r.slug;
			selectEl.appendChild(opt);
		}
		if (desired && selectEl.options.length) {
			selectEl.value = desired;
		}

		primaryBtn = document.createElement('button');
		primaryBtn.type = 'button';
		primaryBtn.className = 'creatorreactor-imp__btn';

		msgEl = document.createElement('p');
		msgEl.className = 'creatorreactor-imp__msg';
		msgEl.style.display = 'none';

		body.appendChild(hintEl);
		body.appendChild(selectEl);
		body.appendChild(primaryBtn);
		body.appendChild(msgEl);

		panel.appendChild(titlebar);
		panel.appendChild(body);

		document.body.appendChild(panel);

		renderMode();
		applySavedPosition();

		titlebar.addEventListener('mousedown', onTitleMouseDown);
		document.addEventListener('mousemove', onDocMouseMove);
		document.addEventListener('mouseup', onDocMouseUp);
		primaryBtn.addEventListener('click', onPrimaryClick);
		window.addEventListener('resize', clampPanel);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', build);
	} else {
		build();
	}
}());
