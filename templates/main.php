<?php
vendor_script('quicknotes', 'handlebars');
script('quicknotes', 'templates');
vendor_script('quicknotes', 'web-animations');
vendor_script('quicknotes', 'hammer');
vendor_script('quicknotes', 'muuri');
vendor_script('quicknotes', 'medium-editor');
vendor_style('quicknotes', 'medium-editor');
vendor_style('quicknotes', 'beagle');
vendor_script('quicknotes', 'autolist');
script('quicknotes', 'script');
style('quicknotes', 'style');
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
