<div class="note-grid-item">
	<div class="quicknote noselect {{#if active}}note-active{{/if}} {{#if isshared}}shared{{/if}} {{#if sharedwith}}shareowner{{/if}}" style="background-color: {{color}}" data-id="{{ id }}" data-timestamp="{{ timestamp }}" >
		{{#if isshared}}
			<div class='icon-share shared-title' title="Shared by {{ userid }}"></div><div id='title' class='note-title'>{{{ title }}}</div>
			<div id='content' class='note-content'>{{{ content }}}</div>
		{{else}}
			<div class="icon-delete hide-delete-icon icon-delete-note" title="Delete"></div>
			{{#if sharedwith}}
				<div class='icon-share shared-title-owner' title="Shared with {{ sharedwith }}"></div>
			{{/if}}
			<div id='title-editable' class='note-title'>{{{ title }}}</div>
			<div id='content-editable' class='note-content'>{{{ content }}}</div>
		{{/if}}
	</div>
</div>
