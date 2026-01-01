<?php

$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db'   => 'hope_nurse_exam',
    'base_url' => 'http://localhost/hope-nurse-exam/',
];

$conn = new mysqli(
    $config['host'],
    $config['user'],
    $config['pass'],
    $config['db']
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
