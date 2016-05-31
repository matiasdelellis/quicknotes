<div class="note-grid-item">
	<div class="quicknote noselect {{#if active}}note-active{{/if}} {{#if isshared}}shared{{/if}}" style="background-color: {{color}}" data-id="{{ id }}" data-timestamp="{{ timestamp }}" >
		{{#if isshared}}
		<div class='icon-share shared-title' title="shared with you by {{ userid }}"></div><div id='title' class='note-title'>{{{ title }}}</div>
		<div id='content' class='note-content'>{{{ content }}}</div>
		{{else}}
		<div id='title-editable' class='note-title'>{{{ title }}}</div>
		<button class="icon-delete hide-delete-icon icon-delete-note" title="Delete"></button>
		<div id='content-editable' class='note-content'>{{{ content }}}</div>
		{{/if}}
	</div>
</div>
