<?php
script('quicknotes', 'templates');
\OCP\Util::addScript('quicknotes', 'vendor/handlebars');
\OCP\Util::addScript('quicknotes', 'vendor/isotope.pkgd');
\OCP\Util::addScript('quicknotes', 'vendor/medium-editor');
\OCP\Util::addScript('quicknotes', 'vendor/autolist');
\OCP\Util::addScript('quicknotes', 'vendor/lozad');
\OCP\Util::addScript('quicknotes', 'qn-dialogs');
\OCP\Util::addScript('quicknotes', 'qn-colorpick');
\OCP\Util::addScript('quicknotes', 'script');
style('quicknotes', 'not-vue');
style('quicknotes', 'style');
style('quicknotes', 'medium');
style('quicknotes', 'qn-colorpick');
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
