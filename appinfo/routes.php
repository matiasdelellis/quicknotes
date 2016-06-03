<?php
return [
    'resources' => [
        'note' => ['url' => '/notes'],
        'note_api' => ['url' => '/api/0.1/notes']
    ],
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'note_api#preflighted_cors', 'url' => '/api/0.1/{path}',
         'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
        ['name' => 'note#get_user_groups_and_users', 'url' => '/api/0.1/getusergroups', 'verb' => 'GET'],
        ['name' => 'note#add_group_share', 'url' => '/api/0.1/groups/addshare', 'verb' => 'POST'],
        ['name' => 'note#remove_group_share', 'url' => '/api/0.1/groups/removeshare', 'verb' => 'POST'],
        ['name' => 'note#add_user_share', 'url' => '/api/0.1/users/addshare', 'verb' => 'POST'],
        ['name' => 'note#remove_user_share', 'url' => '/api/0.1/users/removeshare', 'verb' => 'POST']
    ]
];
