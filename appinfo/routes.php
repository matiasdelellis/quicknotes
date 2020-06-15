<?php
return ['resources' =>
	[
		'note' => ['url' => '/notes'],
		'noteApi' => ['url' => '/api/v1/notes']
	],
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET']
	]
];
