<?php

/**
 * Uptime card widget view.
 *
 * @var CView $this
 * @var array $data
 */

$fields = $data['fields_values'];

if ($data['error'] !== null) {
	$content = (new CDiv($data['error']))
		->addClass('uptime-card')
		->addClass('uptime-card--empty');
}
else {
	$items = [];

	if ((int) $fields['show_header'] === 1) {
		$items[] = (new CDiv([
			(new CDiv($data['title']))->addClass('uptime-card__title'),
			(new CDiv([
				(new CSpan())->addClass('uptime-card__status-dot'),
				(new CSpan($data['status_label']))->addClass('uptime-card__status-text')
			]))
				->addClass('uptime-card__status')
				->addClass('uptime-card__status--'.$data['status'])
				->addStyle('color: '.$data['status_color'].';')
		]))->addClass('uptime-card__header');
	}

	$bars = [];

	foreach ($data['bars'] as $bar) {
		$bars[] = (new CDiv())
			->addClass('uptime-card__bar')
			->addClass('uptime-card__bar--'.$bar['state'])
			->addStyle('background-color: '.$bar['color'].';')
			->setAttribute('title', $bar['tooltip'])
			->setAttribute('aria-label', $bar['tooltip']);
	}

	$items[] = (new CDiv($bars))
		->addClass('uptime-card__timeline')
		->addStyle(
			'--uptime-card-bar-height: '.max(4, (int) $fields['bar_height']).'px;'.
			'--uptime-card-bar-gap: '.max(0, (int) $fields['bar_spacing']).'px;'.
			'--uptime-card-bar-radius: '.max(0, (int) $fields['bar_radius']).'px;'
		);

	if ((int) $fields['show_footer'] === 1) {
		$average = (int) $fields['show_average'] === 1 && $data['uptime'] !== null
			? sprintf('%.2f%%', $data['uptime'])
			: '';

		$footer_center = $data['history_truncated']
			? trim($average.' '._('(limited history)'))
			: $average;

		$items[] = (new CDiv([
			(new CSpan($data['range_label']))->addClass('uptime-card__footer-edge'),
			(new CSpan($footer_center))->addClass('uptime-card__average'),
			(new CSpan(_('Now')))->addClass('uptime-card__footer-edge')
		]))->addClass('uptime-card__footer');
	}

	$content = (new CDiv($items))->addClass('uptime-card');
}

(new CWidgetView($data))
	->addItem($content)
	->show();
