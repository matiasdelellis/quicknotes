<?php
return [
    'resources' => [
        'note' => ['url' => '/notes'],
        'note_api' => ['url' => '/api/0.1/notes']
    ],
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'note_api#preflighted_cors', 'url' => '/api/0.1/{path}',
         'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']]
    ]
];