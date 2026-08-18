<?php
return ['resources' =>
	[
		'note' => ['url' => '/notes'],
		'noteApi' => ['url' => '/api/v1/notes']
	],
	'routes' => [
		// Main page
		[
			'name' => 'page#index',
			'url' => '/',
			'verb' => 'GET'
		],
		// Dashboard
		[
			'name' => 'note#dashboard',
			'url' => '/notes/dashboard',
			'verb' => 'GET',
		],
		// Emptying the trash. Declared here rather than as another action of
		// the note resource: the explicit routes are matched first, so this
		// answers before `note#destroy` reads "trash" as a note id.
		[
			'name' => 'note#emptyTrash',
			'url' => '/notes/trash',
			'verb' => 'DELETE',
		],
		[
			'name' => 'noteApi#emptyTrash',
			'url' => '/api/v1/notes/trash',
			'verb' => 'DELETE',
		],
		// Shares of a note. Their own resource since 0.9.1: they used to be
		// a list inside the note payload, applied on save, which meant a
		// share existed only once the note was saved and that an old browser
		// tab could revoke what a newer one had just shared.
		[
			'name' => 'share#index',
			'url' => '/notes/{noteId}/shares',
			'verb' => 'GET'
		],
		[
			'name' => 'shareApi#index',
			'url' => '/api/v1/notes/{noteId}/shares',
			'verb' => 'GET'
		],
		[
			'name' => 'share#create',
			'url' => '/notes/{noteId}/shares',
			'verb' => 'POST'
		],
		[
			'name' => 'shareApi#create',
			'url' => '/api/v1/notes/{noteId}/shares',
			'verb' => 'POST'
		],
		// Leaving a note somebody shared with you. Before the shares of a
		// note, so `self` is not read as a share id.
		[
			'name' => 'share#leave',
			'url' => '/notes/{noteId}/shares/self',
			'verb' => 'DELETE'
		],
		[
			'name' => 'shareApi#leave',
			'url' => '/api/v1/notes/{noteId}/shares/self',
			'verb' => 'DELETE'
		],
		[
			'name' => 'share#update',
			'url' => '/shares/{shareId}',
			'verb' => 'PUT'
		],
		[
			'name' => 'shareApi#update',
			'url' => '/api/v1/shares/{shareId}',
			'verb' => 'PUT'
		],
		[
			'name' => 'share#destroy',
			'url' => '/shares/{shareId}',
			'verb' => 'DELETE'
		],
		[
			'name' => 'shareApi#destroy',
			'url' => '/api/v1/shares/{shareId}',
			'verb' => 'DELETE'
		],
		// Who a note could still be shared with. Goes through the
		// collaborator search of the server, so the admin settings about
		// user enumeration are honoured.
		[
			'name' => 'share#sharees',
			'url' => '/notes/{noteId}/sharees',
			'verb' => 'GET'
		],
		[
			'name' => 'shareApi#sharees',
			'url' => '/api/v1/notes/{noteId}/sharees',
			'verb' => 'GET'
		],
		// Archive
		[
			'name' => 'note#archive',
			'url' => '/notes/{id}/archive',
			'verb' => 'POST'
		],
		[
			'name' => 'noteApi#archive',
			'url' => '/api/v1/notes/{id}/archive',
			'verb' => 'POST'
		],
		// Soft delete (move to trash)
		[
			'name' => 'note#trash',
			'url' => '/notes/{id}/trash',
			'verb' => 'POST'
		],
		[
			'name' => 'noteApi#trash',
			'url' => '/api/v1/notes/{id}/trash',
			'verb' => 'POST'
		],
		// Unarchive
		[
			'name' => 'note#unarchive',
			'url' => '/notes/{id}/unarchive',
			'verb' => 'POST'
		],
		[
			'name' => 'noteApi#unarchive',
			'url' => '/api/v1/notes/{id}/unarchive',
			'verb' => 'POST'
		],
		// Restore from trash
		[
			'name' => 'note#restore',
			'url' => '/notes/{id}/restore',
			'verb' => 'POST'
		],
		[
			'name' => 'noteApi#restore',
			'url' => '/api/v1/notes/{id}/restore',
			'verb' => 'POST'
		],
		// Set / move / cancel the reminder of a note. A null reminderAt
		// cancels it, so one route covers both.
		[
			'name' => 'note#reminder',
			'url' => '/notes/{id}/reminder',
			'verb' => 'PUT'
		],
		[
			'name' => 'noteApi#reminder',
			'url' => '/api/v1/notes/{id}/reminder',
			'verb' => 'PUT'
		],
		// Upload attachments
		[
			'name' => 'AttachmentApi#upload',
			'url' => '/api/v1/attachments',
			'verb' => 'POST'
		],
		// Describe an existing file to attach it to a note
		[
			'name' => 'AttachmentApi#info',
			'url' => '/api/v1/attachments/info',
			'verb' => 'GET'
		],
		// Serve an attachment to whoever can see the note it hangs off. The
		// note id is in the path because it is what access is checked
		// against: the file itself is never shared in Files.
		[
			'name' => 'AttachmentApi#preview',
			'url' => '/api/v1/notes/{noteId}/attachments/{fileId}/preview',
			'verb' => 'GET'
		],
		[
			'name' => 'AttachmentApi#download',
			'url' => '/api/v1/notes/{noteId}/attachments/{fileId}/download',
			'verb' => 'GET'
		],
		// User Settings
		[
			'name' => 'settings#setUserValue',
			'url' => '/setuservalue',
			'verb' => 'POST'
		],
		[
			'name' => 'settings#getUserValue',
			'url' => '/getuservalue',
			'verb' => 'GET'
		],
		[
			'name' => 'noteApi#preflighted_cors',
			'url' => '/api/v1/{path}',
			'verb' => 'OPTIONS',
			'requirements' => ['path' => '.+']
		]
	]
];
