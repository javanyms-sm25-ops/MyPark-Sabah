<?php

// Local XAMPP defaults. For other accounts, supply credentials via the PHP environment.
return [
    'host' => getenv('MYPARK_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('MYPARK_DB_PORT') ?: '3306',
    'name' => getenv('MYPARK_DB_NAME') ?: 'mypark_sabah',
    'user' => getenv('MYPARK_DB_USER') ?: 'root',
    'password' => (string)getenv('MYPARK_DB_PASSWORD'),
];
