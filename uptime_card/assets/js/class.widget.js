class CWidgetUptimeCard extends CWidget {
	setContents(response) {
		super.setContents(response);

		for (const bar of this._body.querySelectorAll('.uptime-card__bar')) {
			bar.tabIndex = 0;
		}
	}
}
