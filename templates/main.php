<?php
vendor_script('quicknotes', 'handlebars');
vendor_script('quicknotes', 'isotope.pkgd');
vendor_script('quicknotes', 'medium-editor');
vendor_style('quicknotes', 'medium-editor');
vendor_style('quicknotes', 'beagle');
vendor_script('quicknotes', 'autolist');
script('quicknotes', 'script');
style('quicknotes', 'style');
?>

<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('part.navigation')); ?>
		<?php print_unescaped($this->inc('part.settings')); ?>
	</div>

	<div id="app-content">
		<div id="app-content-wrapper">
			<?php print_unescaped($this->inc('part.content')); ?>
		</div>
	</div>
</div>
