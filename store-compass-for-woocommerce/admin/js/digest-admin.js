/**
 * Email digest admin: show weekly/monthly fields based on frequency.
 */
(function () {
	var freq = document.getElementById('frequency');
	if (!freq) {
		return;
	}
	freq.addEventListener('change', function () {
		document.querySelectorAll('.dataviz-digest-row--weekly').forEach(function (row) {
			row.style.display = freq.value === 'weekly' ? '' : 'none';
		});
		document.querySelectorAll('.dataviz-digest-row--monthly').forEach(function (row) {
			row.style.display = freq.value === 'monthly' ? '' : 'none';
		});
	});
})();
