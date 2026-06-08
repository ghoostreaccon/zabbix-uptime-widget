<?php

namespace Widgets\UptimeCard\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};
use Zabbix\Widgets\Fields\{
	CWidgetFieldMultiSelectItem,
	CWidgetFieldTextBox
};

class WidgetForm extends CWidgetForm {
	public const DEFAULT_COLOR_PALETTE = [
		'45C669', 'C66445', 'C6B145', 'C9C9C9', '2AB5FF', '385CC7', 'EC1594', '3CA20D',
		'F3601B', '1CAE59', '45CFDB', '894BBC', '6D6D6D', 'FF465C', 'B0AF07', '0EC9AC'
	];

	public function addFields(): self {
		return $this
			->addField(
				(new CWidgetFieldMultiSelectItem('itemid', _('Item')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				new CWidgetFieldTextBox('title', _('Title'))
			);
	}
}
