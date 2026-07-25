/**
 * 站点清理 AJAX 流程与文章类型快捷操作。
 * 「全选 / 仅内置」只改当前页面勾选；「开始清理」始终按页面勾选提交。
 */
(function () {
	function initCleanup() {
		var btn = document.getElementById('wpse-cleanup-run');
		var form = document.getElementById('wpse-cleanup-form');
		var prog = document.getElementById('wpse-cleanup-progress');
		var bar = document.getElementById('wpse-cleanup-bar');
		var log = document.getElementById('wpse-cleanup-log');
		var cfg = window.WPSE_CLEANUP;
		if (!btn || !form || !prog || !bar || !log || !cfg) {
			return;
		}

		var labels = (cfg.i18n && cfg.i18n.labels) || {};
		var typeOps = (cfg.i18n && cfg.i18n.typeOps) || ['revisions', 'auto_drafts', 'trash_posts'];
		var selectAllBtn = document.getElementById('wpse-pt-select-all');
		var selectBuiltinBtn = document.getElementById('wpse-pt-select-builtin');

		/**
		 * @param {string} text
		 */
		function append(text) {
			var div = document.createElement('div');
			div.textContent = text;
			log.appendChild(div);
		}

		/**
		 * @returns {NodeListOf<HTMLInputElement>}
		 */
		function typeInputs() {
			return form.querySelectorAll('input[name="post_types[]"]');
		}

		/**
		 * @returns {string[]}
		 */
		function selectedPostTypes() {
			var types = [];
			typeInputs().forEach(function (el) {
				if (el.checked && el.value) {
					types.push(el.value);
				}
			});
			return types;
		}

		if (selectAllBtn) {
			selectAllBtn.addEventListener('click', function (e) {
				e.preventDefault();
				typeInputs().forEach(function (el) {
					el.checked = true;
				});
			});
		}

		if (selectBuiltinBtn) {
			selectBuiltinBtn.addEventListener('click', function (e) {
				e.preventDefault();
				typeInputs().forEach(function (el) {
					el.checked = el.getAttribute('data-wpse-group') === 'builtin';
				});
			});
		}

		/**
		 * @param {string} action
		 * @param {string[]} postTypes
		 * @returns {Promise<object>}
		 */
		function request(action, postTypes) {
			return new Promise(function (resolve, reject) {
				var body = new URLSearchParams();
				body.set('action', 'wpse_cleanup_' + action);
				body.set('nonce', cfg.nonce);
				postTypes.forEach(function (type) {
					body.append('post_types[]', type);
				});
				fetch(cfg.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString(),
					credentials: 'same-origin',
				})
					.then(function (res) {
						return res.json();
					})
					.then(resolve)
					.catch(reject);
			});
		}

		btn.addEventListener('click', function () {
			var checked = form.querySelectorAll('input[name="ops[]"]:checked');
			if (!checked.length) {
				append(cfg.i18n.selectOne || '请选择清理项');
				return;
			}

			var ops = [];
			checked.forEach(function (el) {
				ops.push(el.value);
			});

			var needsTypes = ops.some(function (op) {
				return typeOps.indexOf(op) !== -1;
			});
			var postTypes = selectedPostTypes();
			if (needsTypes && !postTypes.length) {
				append(cfg.i18n.selectType || '请至少选择一种文章类型');
				return;
			}

			btn.disabled = true;
			log.textContent = '';
			bar.style.width = '0%';
			prog.hidden = false;

			var total = ops.length;
			var done = 0;

			function step() {
				if (!ops.length) {
					append(cfg.i18n.done || '全部清理完成');
					bar.style.width = '100%';
					prog.hidden = true;
					btn.disabled = false;
					return;
				}
				var op = ops.shift();
				var name = labels[op] || op;
				append(name + (cfg.i18n.start || '开始…'));
				request(op, postTypes)
					.then(function (r) {
						if (r && r.success) {
							var msg = r.data && r.data.message ? r.data.message : 'OK';
							append(name + '：' + msg);
						} else {
							var err =
								r && r.data && r.data.message
									? r.data.message
									: cfg.i18n.fail || '失败';
							append(name + '：' + err);
						}
					})
					.catch(function () {
						append(name + (cfg.i18n.fail || '失败'));
					})
					.finally(function () {
						done += 1;
						bar.style.width = Math.round((done * 100) / total) + '%';
						step();
					});
			}

			step();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCleanup);
	} else {
		initCleanup();
	}
})();
