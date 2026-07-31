<div id="app-settings">
	<div id="app-settings-header">
		<?php /* Until Nextcloud 33 the server turned `data-apps-slide-toggle`
		         into a click handler that slid the panel open. That plugin went
		         away with jQuery in 34, so the attribute is gone too and the
		         handler in js/script.js does the work — it sets the `opened`
		         class that the CSS of the server still watches for. */ ?>
		<button class="settings-button"
		        type="button"
		        aria-controls="app-settings-content"
		        aria-expanded="false">
			<?php p($l->t('Settings'));?>
		</button>
	</div>
	<div id="app-settings-content">
	</div>
</div>