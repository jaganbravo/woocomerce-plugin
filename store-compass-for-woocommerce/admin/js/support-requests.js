/**
 * Support request detail modal.
 */
(function () {
	var i18n = window.unmaiAnalytixSrModal || window.datavizSrModal || {};

	function escHtml(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function buildSection(label, value, isCode) {
		if (!value) {
			return '';
		}
		var content;
		if (isCode) {
			try {
				content = '<pre class="dataviz-sr-detail-code">' + escHtml(JSON.stringify(JSON.parse(value), null, 2)) + '</pre>';
			} catch (e) {
				content = '<pre class="dataviz-sr-detail-code">' + escHtml(value) + '</pre>';
			}
		} else {
			content = '<p class="dataviz-sr-detail-text">' + escHtml(value) + '</p>';
		}
		return '<div class="dataviz-sr-detail-section"><strong>' + escHtml(label) + '</strong>' + content + '</div>';
	}

	document.querySelectorAll('.dataviz-sr-view-details').forEach(function (el) {
		el.addEventListener('click', function (e) {
			e.preventDefault();
			var modal = document.getElementById('dataviz-sr-detail-modal');
			if (!modal) {
				return;
			}
			var body = modal.querySelector('.dataviz-sr-detail-body');
			var data = JSON.parse(this.dataset.detail);
			var html = '';
			html += buildSection(i18n.question || '', data.question);
			html += buildSection(i18n.errorReason || '', data.error_reason);
			html += buildSection(i18n.description || '', data.description);
			html += buildSection(i18n.entityType || '', data.entity_type);
			html += buildSection(i18n.user || '', data.user_name);
			html += buildSection(i18n.created || '', data.created_at);
			html += buildSection(i18n.rawIntent || '', data.raw_intent, true);
			body.innerHTML = html || '<p>' + escHtml(i18n.noDetails || '') + '</p>';
			modal.style.display = 'flex';
		});
	});

	var modal = document.getElementById('dataviz-sr-detail-modal');
	if (modal) {
		modal.querySelector('.dataviz-sr-modal__close').addEventListener('click', function () {
			modal.style.display = 'none';
		});
		modal.querySelector('.dataviz-sr-modal__backdrop').addEventListener('click', function () {
			modal.style.display = 'none';
		});
	}
})();
