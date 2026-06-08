# Zabbix Uptime Card Widget

A Zabbix dashboard widget inspired by `dylandoamaral/uptime-card`.

It renders one selected Zabbix item as a compact status timeline:

- green bars for fully OK periods
- red bars for fully/down-threshold problem periods
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

## Troubleshooting

### `Wrong Widget.php class name for module located at widgets/uptime_card`

The widget code must use the `Widgets\UptimeCard` PHP namespace when installed under
`/usr/share/zabbix/ui/widgets/uptime_card`.

Zabbix builds the PHP namespace prefix from the first path segment. If the widget is located at
`widgets/uptime_card`, Zabbix expects `Widgets\UptimeCard\Widget`, which matches this package.

Remove any old module copy and install it under `ui/widgets`:

```bash
rm -rf /usr/share/zabbix/modules/uptime_card
cp -a uptime_card /usr/share/zabbix/ui/widgets/uptime_card
```

Then go to **Administration -> General -> Modules**, click **Scan directory**, and enable
**Uptime card** again.

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

## Notes

The widget queries item history directly. If no value exists before the selected time period, the beginning of the period is rendered as unknown because Zabbix cannot prove the prior state from history.

This package does not copy code from the Home Assistant card; it ports the same status-timeline idea to the Zabbix dashboard widget API.
