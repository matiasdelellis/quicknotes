<?php
\OCP\Util::addScript('quicknotes', 'templates');
\OCP\Util::addScript('quicknotes', 'vendor/handlebars');
\OCP\Util::addScript('quicknotes', 'vendor/isotope.pkgd');
\OCP\Util::addScript('quicknotes', 'vendor/medium-editor');
\OCP\Util::addScript('quicknotes', 'vendor/autolist');
\OCP\Util::addScript('quicknotes', 'vendor/lozad');
\OCP\Util::addScript('quicknotes', 'qn-dialogs');
\OCP\Util::addScript('quicknotes', 'qn-colorpick');
\OCP\Util::addScript('quicknotes', 'script');
\OCP\Util::addStyle('quicknotes', 'not-vue');
\OCP\Util::addStyle('quicknotes', 'style');
\OCP\Util::addStyle('quicknotes', 'medium');
\OCP\Util::addStyle('quicknotes', 'qn-colorpick');
\OCP\Util::addStyle('quicknotes', 'vendor/medium-editor');
?>

	<div id="app-navigation">
		<?php print_unescaped($this->inc('part.navigation')); ?>
		<?php print_unescaped($this->inc('part.settings')); ?>
	</div>

	<div id="app-content">
		<div id="app-content-wrapper">
			<?php print_unescaped($this->inc('part.content')); ?>
		</div>
	</div>
