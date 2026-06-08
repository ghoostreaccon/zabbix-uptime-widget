<?php

namespace Widgets\UptimeCard\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {
	private const HISTORY_LIMIT = 20000;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'has_custom_time_period' => 'in 1'
		]);
	}

	protected function doAction(): void {
		$fields = $this->getFieldsWithDefaults();
		[$time_from, $time_to] = $this->getTimePeriod($fields);

		$data = $this->getDefaultResponseData($fields, $time_from, $time_to);

		if ($time_to <= $time_from) {
			$data['error'] = _('Invalid time period.');
			$this->setResponse(new CControllerResponseData($data));
			return;
		}

		$itemid = $fields['itemid'];

		if ($itemid === null || $itemid === '' || (is_array($itemid) && !$itemid)) {
			$data['error'] = _('Select an item to display uptime.');
			$this->setResponse(new CControllerResponseData($data));
			return;
		}

		$db_items = API::Item()->get([
			'output' => ['itemid', 'name', 'key_', 'value_type', 'units', 'lastvalue', 'lastclock'],
			'selectHosts' => ['name'],
			'itemids' => $itemid,
			'webitems' => true,
			'filter' => [
				'value_type' => [
					ITEM_VALUE_TYPE_FLOAT,
					ITEM_VALUE_TYPE_STR,
					ITEM_VALUE_TYPE_LOG,
					ITEM_VALUE_TYPE_UINT64,
					ITEM_VALUE_TYPE_TEXT
				]
			],
			'limit' => 1
		]);

		if (!$db_items) {
			$data['error'] = _('No readable item history found.');
			$this->setResponse(new CControllerResponseData($data));
			return;
		}

		$item = $db_items[0];
		$host = $item['hosts'][0]['name'] ?? '';
		$item_label = $host !== '' ? $host.': '.$item['name'] : $item['name'];

		$data['title'] = trim((string) $fields['title']) !== '' ? trim((string) $fields['title']) : $item_label;
		$data['item'] = [
			'itemid' => $item['itemid'],
			'name' => $item['name'],
			'host' => $host,
			'key_' => $item['key_']
		];

		$ok_values = $this->parseStateValues($fields['ok_values']);
		$ko_values = $this->parseStateValues($fields['ko_values']);
		$none_values = $this->parseStateValues($fields['none_values']);

		[$previous, $history] = $this->getHistory($item, $time_from, $time_to);
		$segments = $this->buildSegments($previous, $history, $time_from, $time_to, $ok_values, $ko_values, $none_values);
		$colors = $this->getColors($fields);

		[$bars, $uptime] = $this->buildBars(
			$segments,
			$time_from,
			$time_to,
			(int) $fields['bar_count'],
			(int) $fields['problem_threshold'],
			$colors
		);

		$current_value = $this->getCurrentValue($item, $history, $previous);
		$current_state = $current_value !== null
			? $this->classifyValue($current_value, $ok_values, $ko_values, $none_values)
			: 'none';

		$data['bars'] = $bars;
		$data['uptime'] = $uptime;
		$data['status'] = $current_state;
		$data['status_color'] = $colors[$current_state === 'ko' ? 'ko' : ($current_state === 'ok' ? 'ok' : 'none')];
		$data['status_label'] = $this->makeStatusLabel($current_state, $current_value);
		$data['history_truncated'] = count($history) >= self::HISTORY_LIMIT;

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getFieldsWithDefaults(): array {
		$now = time();

		$fields = $this->fields_values + [
			'itemid' => null,
			'title' => '',
			'time_period' => ['from_ts' => $now - 86400, 'to_ts' => $now],
			'ok_values' => '1,on,up,available,ok,true',
			'ko_values' => '0,off,down,unavailable,problem,false',
			'none_values' => '',
			'bar_count' => 36,
			'problem_threshold' => 100,
			'bar_height' => 46,
			'bar_spacing' => 4,
			'bar_radius' => 1,
			'show_header' => 1,
			'show_average' => 1,
			'show_footer' => 1,
			'ok_color' => '45C669',
			'ko_color' => 'C66445',
			'half_color' => 'C6B145',
			'none_color' => 'C9C9C9'
		];

		if (!is_array($fields['time_period'])) {
			$fields['time_period'] = ['from_ts' => $now - 86400, 'to_ts' => $now];
		}

		$fields['time_period'] += ['from_ts' => $now - 86400, 'to_ts' => $now];

		return $fields;
	}

	private function getTimePeriod(array $fields): array {
		$now = time();
		$time_period = is_array($fields['time_period']) ? $fields['time_period'] : [];

		$time_from = array_key_exists('from_ts', $time_period)
			? (int) $time_period['from_ts']
			: $now - 86400;
		$time_to = array_key_exists('to_ts', $time_period)
			? (int) $time_period['to_ts']
			: $now;

		return [$time_from, $time_to];
	}

	private function getDefaultResponseData(array $fields, int $time_from, int $time_to): array {
		return [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'fields_values' => $fields,
			'title' => trim((string) $fields['title']),
			'item' => null,
			'bars' => [],
			'uptime' => null,
			'status' => 'none',
			'status_label' => _('No data'),
			'status_color' => $this->normalizeColor($fields['none_color'], 'C9C9C9'),
			'range_label' => $this->formatAge(max(0, $time_to - $time_from)),
			'from_label' => $this->formatClock($time_from),
			'to_label' => $this->formatClock($time_to),
			'history_truncated' => false,
			'error' => null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];
	}

	private function getHistory(array $item, int $time_from, int $time_to): array {
		$history_type = (int) $item['value_type'];
		$output = ['itemid', 'clock', 'ns', 'value'];

		$previous = API::History()->get([
			'output' => $output,
			'history' => $history_type,
			'itemids' => $item['itemid'],
			'time_till' => max(0, $time_from - 1),
			'sortfield' => ['clock', 'ns'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1
		]);

		$history = API::History()->get([
			'output' => $output,
			'history' => $history_type,
			'itemids' => $item['itemid'],
			'time_from' => $time_from,
			'time_till' => $time_to,
			'sortfield' => ['clock', 'ns'],
			'sortorder' => ZBX_SORT_UP,
			'limit' => self::HISTORY_LIMIT
		]);

		return [$previous[0] ?? null, $history];
	}

	private function buildSegments(?array $previous, array $history, int $time_from, int $time_to,
			array $ok_values, array $ko_values, array $none_values): array {
		$segments = [];
		$cursor = $time_from;
		$state = $previous !== null
			? $this->classifyValue($previous['value'], $ok_values, $ko_values, $none_values)
			: 'none';

		foreach ($history as $row) {
			$clock = (int) $row['clock'];

			if ($clock < $time_from || $clock > $time_to) {
				continue;
			}

			if ($clock > $cursor) {
				$segments[] = [
					'from' => $cursor,
					'to' => $clock,
					'state' => $state
				];
				$cursor = $clock;
			}

			$state = $this->classifyValue($row['value'], $ok_values, $ko_values, $none_values);
		}

		if ($cursor < $time_to) {
			$segments[] = [
				'from' => $cursor,
				'to' => $time_to,
				'state' => $state
			];
		}

		return $segments;
	}

	private function buildBars(array $segments, int $time_from, int $time_to, int $bar_count,
			int $problem_threshold, array $colors): array {
		$bar_count = max(1, min(120, $bar_count));
		$total = max(1, $time_to - $time_from);
		$bars = [];
		$ok_duration_sum = 0.0;
		$known_duration_sum = 0.0;

		for ($i = 0; $i < $bar_count; $i++) {
			$bar_from = $time_from + ($total * $i / $bar_count);
			$bar_to = $i === $bar_count - 1 ? $time_to : $time_from + ($total * ($i + 1) / $bar_count);
			$duration = max(0.000001, $bar_to - $bar_from);
			$amounts = ['ok' => 0.0, 'ko' => 0.0, 'none' => 0.0];

			foreach ($segments as $segment) {
				$overlap = min($segment['to'], $bar_to) - max($segment['from'], $bar_from);

				if ($overlap > 0) {
					$amounts[$segment['state']] += $overlap;
				}
			}

			$known = $amounts['ok'] + $amounts['ko'] + $amounts['none'];

			if ($known < $duration) {
				$amounts['none'] += $duration - $known;
			}

			$repartition = [
				'ok' => ($amounts['ok'] / $duration) * 100,
				'ko' => ($amounts['ko'] / $duration) * 100,
				'none' => ($amounts['none'] / $duration) * 100
			];
			$visual_state = $this->getVisualState($repartition, $problem_threshold);
			$ok_duration_sum += $amounts['ok'];
			$known_duration_sum += $amounts['ok'] + $amounts['ko'];

			$bars[] = [
				'from' => (int) floor($bar_from),
				'to' => (int) ceil($bar_to),
				'repartition' => $repartition,
				'state' => $visual_state,
				'color' => $colors[$visual_state],
				'tooltip' => sprintf(
					'%s - %s | %.2f%% OK',
					$this->formatClock((int) floor($bar_from)),
					$this->formatClock((int) ceil($bar_to)),
					$repartition['ok']
				)
			];
		}

		return [$bars, $known_duration_sum > 0.0 ? ($ok_duration_sum / $known_duration_sum) * 100 : null];
	}

	private function getVisualState(array $repartition, int $problem_threshold): string {
		if ($repartition['none'] >= 99.999) {
			return 'none';
		}

		if ($repartition['ok'] >= 99.999) {
			return 'ok';
		}

		if ($repartition['ko'] >= $problem_threshold) {
			return 'ko';
		}

		return 'half';
	}

	private function getCurrentValue(array $item, array $history, ?array $previous): ?string {
		if ($history) {
			$last = $history[count($history) - 1];
			return (string) $last['value'];
		}

		if ($item['lastclock'] !== null && $item['lastclock'] !== '0') {
			return (string) $item['lastvalue'];
		}

		return $previous !== null ? (string) $previous['value'] : null;
	}

	private function makeStatusLabel(string $state, ?string $value): string {
		$label = [
			'ok' => _('OK'),
			'ko' => _('Problem'),
			'none' => _('Unknown')
		][$state] ?? _('Unknown');

		if ($value === null || $value === '') {
			return $label;
		}

		return sprintf('%s (%s)', $label, $value);
	}

	private function classifyValue($value, array $ok_values, array $ko_values, array $none_values): string {
		$normalized = strtolower(trim((string) $value));

		if (in_array($normalized, $none_values, true)) {
			return 'none';
		}

		if (in_array($normalized, $ok_values, true)) {
			return 'ok';
		}

		if (in_array($normalized, $ko_values, true)) {
			return 'ko';
		}

		if (is_numeric($normalized)) {
			return (float) $normalized > 0 ? 'ok' : 'ko';
		}

		if ($ok_values && !$ko_values) {
			return 'ko';
		}

		if (!$ok_values && $ko_values) {
			return 'ok';
		}

		return 'none';
	}

	private function parseStateValues($values): array {
		$result = [];

		foreach (preg_split('/[\r\n,]+/', (string) $values) as $value) {
			$value = strtolower(trim($value));

			if ($value !== '') {
				$result[$value] = true;
			}
		}

		return array_keys($result);
	}

	private function getColors(array $fields): array {
		return [
			'ok' => $this->normalizeColor($fields['ok_color'], '45C669'),
			'ko' => $this->normalizeColor($fields['ko_color'], 'C66445'),
			'half' => $this->normalizeColor($fields['half_color'], 'C6B145'),
			'none' => $this->normalizeColor($fields['none_color'], 'C9C9C9')
		];
	}

	private function normalizeColor($color, string $fallback): string {
		$hex = preg_replace('/[^0-9a-f]/i', '', (string) $color);
		$hex = strtoupper($hex ?: $fallback);

		if (strlen($hex) === 3) {
			$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
		}

		if (strlen($hex) !== 6) {
			$hex = $fallback;
		}

		return '#'.$hex;
	}

	private function formatAge(int $seconds): string {
		$units = [
			['seconds' => 31536000, 'label' => 'year'],
			['seconds' => 2592000, 'label' => 'month'],
			['seconds' => 604800, 'label' => 'week'],
			['seconds' => 86400, 'label' => 'day'],
			['seconds' => 3600, 'label' => 'hour'],
			['seconds' => 60, 'label' => 'minute']
		];

		foreach ($units as $unit) {
			if ($seconds >= $unit['seconds']) {
				$value = (int) floor($seconds / $unit['seconds']);
				return $value.' '.$unit['label'].($value === 1 ? '' : 's').' ago';
			}
		}

		return max(0, $seconds).' seconds ago';
	}

	private function formatClock(int $clock): string {
		return date('Y-m-d H:i:s', $clock);
	}
}
