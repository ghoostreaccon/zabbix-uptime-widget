<?php

?>

window.widget_form = new class {
	init({color_palette}) {
		colorPalette.setThemeColors(color_palette);

		for (const colorpicker of jQuery('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			jQuery(colorpicker).colorpicker();
		}

		const overlay = overlays_stack.getById('widget_properties');

		if (overlay !== null) {
			for (const event of ['overlay.reload', 'overlay.close']) {
				overlay.$dialogue[0].addEventListener(event, () => { jQuery.colorpicker('hide'); });
			}
		}
	}
};
