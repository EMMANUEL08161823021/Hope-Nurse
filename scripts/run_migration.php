<?php


$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'hope_nurse_exam';
$SQL_FILE = __DIR__ . '/../migrations/hope_nurse_exam.sql';

if (!file_exists($SQL_FILE)) {
    echo "Migration file not found: $SQL_FILE\n";
    exit(1);
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $dsnNoDb = "mysql:host={$DB_HOST};port={$DB_PORT};charset=utf8mb4";
    $pdo = new PDO($dsnNoDb, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    echo "ERROR: Could not connect to MySQL: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database `{$DB_NAME}` ensured.\n";
} catch (PDOException $e) {
    echo "ERROR: Could not create database: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    echo "ERROR: Could not connect to database {$DB_NAME}: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$sql = file_get_contents($SQL_FILE);
if ($sql === false) {
    echo "ERROR: Failed to read SQL file.\n";
    exit(1);
}


try {

    $pdo->exec($sql);
    echo "Migration executed successfully (single exec).\n";
} catch (PDOException $e) {
    echo "Single exec failed, trying statement-by-statement. Reason: " . $e->getMessage() . PHP_EOL;
    $stmts = preg_split('/;\\s*\\n/', $sql);
    $count = 0;
    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
            $count++;
        } catch (PDOException $ex) {
            echo "Failed statement: " . substr($stmt,0,200) . "...\n";
            echo "Error: " . $ex->getMessage() . "\n";
            exit(1);
        }
    }
    echo "Executed {$count} statements.\n";
}

echo "Done.\n";
exit(0);
