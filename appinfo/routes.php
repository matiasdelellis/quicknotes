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
		// Share
		[
			'name' => 'share#forget',
			'url' => '/share/{noteId}',
			'verb' => 'DELETE'
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
		// Upload attachments
		[
			'name' => 'AttachmentApi#upload',
			'url' => '/api/v1/attachments',
			'verb' => 'POST'
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
