/**
 * 设置页 Tab 切换。
 */
(function () {
	function initTabs() {
		var root = document.querySelector('.wpse-wrap');
		if (!root) {
			return;
		}
		var tabs = root.querySelectorAll('.wpse-tabs [data-wpse-tab]');
		var panels = root.querySelectorAll('.wpse-panel');
		var cleanupTool = document.getElementById('wpse-cleanup-tool');
		var globalSubmit = root.querySelector('.wpse-global-submit');

		/**
		 * @param {string} id
		 */
		function activate(id) {
			var isCleanup = id === 'wpse-tab-cleanup';
			tabs.forEach(function (tab) {
				var active = tab.getAttribute('data-wpse-tab') === id;
				tab.classList.toggle('nav-tab-active', active);
			});
			panels.forEach(function (panel) {
				panel.classList.toggle('is-active', panel.id === id);
			});
			if (cleanupTool) {
				cleanupTool.hidden = !isCleanup;
			}
			if (globalSubmit) {
				globalSubmit.hidden = isCleanup;
			}
			// 清理 Tab 内容在表单外的工具区，占位 panel 始终隐藏。
			var cleanupPanel = document.getElementById('wpse-tab-cleanup');
			if (cleanupPanel) {
				cleanupPanel.hidden = true;
				cleanupPanel.classList.remove('is-active');
			}
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function (e) {
				e.preventDefault();
				var id = tab.getAttribute('data-wpse-tab');
				if (id) {
					activate(id);
					if (history.replaceState) {
						history.replaceState(null, '', '#' + id);
					}
				}
			});
		});

		var hash = (window.location.hash || '').replace(/^#/, '');
		if (hash && document.getElementById(hash)) {
			activate(hash);
		} else {
			activate('wpse-tab-url');
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTabs);
	} else {
		initTabs();
	}
})();
