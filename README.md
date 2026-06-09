# Zabbix Uptime Card Widget

A Zabbix dashboard widget inspired by `dylandoamaral/uptime-card`.

It renders one selected Zabbix item as a compact status timeline:

- green bars for fully OK periods
- red bars for periods meeting the configured problem threshold
- yellow bars for mixed periods
- gray bars for unknown/no-data periods
- current status and average uptime for the selected time period

The implementation is a Zabbix frontend widget for the Zabbix 7.x/8.0 widget framework.

## Install

Copy the widget directory into the Zabbix frontend widgets directory:

```bash
cp -a uptime_card /usr/share/zabbix/ui/widgets/uptime_card
```

Then in Zabbix:

1. Go to **Administration -> General -> Modules**.
2. Click **Scan directory**.
3. Enable **Uptime card**.
4. Edit a dashboard and add the **Uptime card** widget.

For container installs, mount or copy `uptime_card` into the frontend container's widgets directory.

## Configuration

The widget needs an item with history enabled. For simple availability checks, use an item whose values map clearly to available/unavailable states, for example `1` for OK and `0` for problem.

Recommended item choices:

- `icmpping` from an ICMP/simple check template is the best direct match. It stores `1` when reachable and `0` when unreachable.
- `agent.ping` is usable only when it has recent history rows. Zabbix returns `1` when the agent is available and no value when unavailable, so missing `agent.ping` data is unknown/no-data, not an explicit `0`.
- For agent availability, create a calculated item such as `1 - nodata(//agent.ping,5m)` and select that item in the widget. This produces explicit `1`/`0` history.
- `system.uptime` is not a host availability item. It is seconds since boot and is better for reboot detection.

Fields:

- **Item**: Zabbix item whose historical values should be rendered.
- **Title**: Optional override. If empty, the widget uses `Host: item name`.
- **Time period**: Dashboard time selector compatible time period. Default is the dashboard time period with `now-24h` fallback.
- **OK values**: Comma or newline separated values considered OK. Default: `1,on,up,available,ok,true`.
- **Problem values**: Comma or newline separated values considered failed. Default: `0,off,down,unavailable,problem,false`.
- **Unknown values**: Optional values rendered as no data.
- **Bars**: Number of timeline bars.
- **Problem threshold**: Percentage of a bar that must be problem state before it is red. Mixed bars below this threshold are yellow.
- **Bar height, spacing, radius**: Timeline appearance.
- **Colors**: OK, problem, mixed, and unknown colors.

## Calculation

The widget treats item history as state changes over time. Each value is classified as OK, problem, or unknown using the configured value lists. Numeric values not matched by those lists are classified as OK when greater than `0` and problem when `0` or lower. The last value before the selected range seeds the initial state; if there is no previous value, the range starts as unknown. A state lasts until the next history value changes it.

For the selected time period `[from, to]`, the widget splits the range into `Bars` equal intervals. For each bar `i`:

```text
bar_duration_i = bar_to_i - bar_from_i
overlap_i,j = max(0, min(segment_to_j, bar_to_i) - max(segment_from_j, bar_from_i))
state_duration_i,state = sum(overlap_i,j for every segment j in state)
state_percent_i,state = 100 * state_duration_i,state / bar_duration_i
```

Any part of a bar that is not covered by known history is counted as unknown.

The bar color is selected from those percentages:

- unknown if the bar is effectively 100% unknown
- OK if the bar is effectively 100% OK
- problem if `problem_percent_i >= Problem threshold`
- mixed otherwise

The displayed average uptime is duration-based and excludes unknown time:

```text
average_uptime = 100 * total_ok_duration / (total_ok_duration + total_problem_duration)
```

If the selected period contains only unknown time, the widget shows `-` for the average. Unknown time does not lower the percentage; it is omitted from the denominator because Zabbix has no value proving whether the item was OK or in problem state.
