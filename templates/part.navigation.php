<!-- translation strings -->
<div style="display:none" id="new-note-string"><?php p($l->t('New note')); ?></div>

<script id="navigation-tpl" type="text/x-handlebars-template">
	<li id="all-notes"><a href="#" class="icon-home svg"><?php p($l->t('All notes')); ?></a></li>
	<li id="new-note"><a href="#" class="icon-add svg"><?php p($l->t('Add note')); ?></a></li>

	<li class="collapsible open">
		<button class="collapse"></button>
		<a href="#" class="icon-search svg">Colors</a>
		<ul>
			<li style="display: flex; justify-content: center;">
				<button class="circle-toolbar" style="background: transparent none; border: 1px solid;"></button>
				{{#each colors}}
					<button class="circle-toolbar" style="background-color: {{color}} "></button>
				{{/each}}
			</li>
		</ul>
	</li>

	<li class="collapsible open">
		<button class="collapse"></button>
		<a href="#" class="icon-folder svg">Notes</a>
		<ul>
			{{#each notes}}
				<li class="note with-menu {{#if active}}active{{/if}}"  data-id="{{ id }}">
					<a href="#">{{{ title }}}</a>
					<div class="app-navigation-entry-utils">
						<ul>
							<li class="app-navigation-entry-utils-menu-button svg"><button></button></li>
						</ul>
					</div>
					<div class="app-navigation-entry-menu">
						<ul>
							<li><button class="delete icon-delete svg" title="delete"></button></li>
						</ul>
					</div>
				</li>
			{{/each}}
		</ul>
	</li>
</script>

<ul class="with-icon"></ul>