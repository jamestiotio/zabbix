<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
const view = {

	init({tile_providers}) {
		this.tile_providers = tile_providers;
		this.defaults = {
			geomaps_tile_url: '',
			geomaps_attribution: '',
			geomaps_max_zoom: ''
		};

		document.querySelector('[name="geomaps_tile_provider"]')
			.addEventListener('change', this.events.tileProviderChange.bind(this));
	},

	events: {
		tileProviderChange(e) {
			const tile_url = document.getElementById('geomaps_tile_url');
			const attribution = document.getElementById('geomaps_attribution');
			const max_zoom = document.getElementById('geomaps_max_zoom');
			const data = this.tile_providers[e.target.value] || this.defaults;

			if (e.target.value !== '') {
				tile_url.readOnly = true;
				attribution.readOnly = true;
				max_zoom.readOnly = true;
				tile_url.tabIndex = -1;
				attribution.tabIndex = -1;
				max_zoom.tabIndex = -1;
			}
			else {
				tile_url.readOnly = false;
				attribution.readOnly = false;
				max_zoom.readOnly = false;
				tile_url.removeAttribute('tabIndex');
				attribution.removeAttribute('tabIndex');
				max_zoom.removeAttribute('tabIndex');
			}

			tile_url.value = data.geomaps_tile_url;
			attribution.value = data.geomaps_attribution;
			max_zoom.value = data.geomaps_max_zoom;
		}
	}
};
</script>
