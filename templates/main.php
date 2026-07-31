<?php
// Must come first: it provides the jQuery global and the dialogs that the
// scripts below expect, and that the server no longer ships. See src/legacy.js.
\OCP\Util::addScript('quicknotes', 'quicknotes-legacy');
// Handlebars has to be loaded before the templates it compiled: `templates.js`
// looks up the global as soon as it runs. Until Nextcloud 33 the server
// provided one, so the order did not matter.
\OCP\Util::addScript('quicknotes', 'vendor/handlebars');
\OCP\Util::addScript('quicknotes', 'templates');
\OCP\Util::addScript('quicknotes', 'vendor/isotope.pkgd');
\OCP\Util::addScript('quicknotes', 'vendor/medium-editor');
\OCP\Util::addScript('quicknotes', 'vendor/autolist');
\OCP\Util::addScript('quicknotes', 'vendor/lozad');
\OCP\Util::addScript('quicknotes', 'qn-colorpick');
\OCP\Util::addScript('quicknotes', 'notes-api');
\OCP\Util::addScript('quicknotes', 'script');
\OCP\Util::addStyle('quicknotes', 'not-vue');
\OCP\Util::addStyle('quicknotes', 'style');
\OCP\Util::addStyle('quicknotes', 'medium');
\OCP\Util::addStyle('quicknotes', 'qn-colorpick');
\OCP\Util::addStyle('quicknotes', 'vendor/medium-editor');
?>

	<?php /* Opens the navigation on narrow screens, where it sits off canvas.
	         The server did this with snap.js until Nextcloud 33; see
	         css/not-vue.css and the handler in js/script.js. */ ?>
	<button id="app-navigation-toggle"
	        type="button"
	        aria-controls="app-navigation"
	        aria-expanded="false"
	        aria-label="<?php p($l->t('Toggle navigation')); ?>">
		<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<path fill="currentColor" d="M3 6h18v2H3V6m0 5h18v2H3v-2m0 5h18v2H3v-2Z" />
		</svg>
	</button>

	<div id="app-navigation">
		<?php print_unescaped($this->inc('part.navigation')); ?>
		<?php print_unescaped($this->inc('part.settings')); ?>
	</div>

	<div id="app-content">
		<div id="app-content-wrapper">
			<?php print_unescaped($this->inc('part.content')); ?>
		</div>
	</div>
