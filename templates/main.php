<?php
vendor_script('quicknotes', 'handlebars');
script('quicknotes', 'templates');
vendor_script('quicknotes', 'isotope.pkgd');
vendor_script('quicknotes', 'medium-editor');
vendor_style('quicknotes', 'medium-editor');
vendor_script('quicknotes', 'autolist');
vendor_script('quicknotes', 'lozad');
script('quicknotes', 'qn-dialogs');
script('quicknotes', 'qn-colorpick');
script('quicknotes', 'script');
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
