<script id="content-tpl" type="text/x-handlebars-template">
	{{#if notes}}
		<div class="notes-grid">
			{{#each notes}}
				<?php print_unescaped($this->inc('part.note')); ?>
			{{/each}}
		</div>
		<?php print_unescaped($this->inc('part.note-modal-editable')); ?>
	{{else}}
		<div class="emptycontent">
			<div class="icon-folder"></div>
			<?php p($l->t('Nothing here. Take your quick notes.')); ?>
		</div>
	{{/if}}
</script>
<div id="div-content"></div>
