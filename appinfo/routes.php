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
