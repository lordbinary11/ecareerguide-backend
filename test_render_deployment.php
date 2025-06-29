<?php
// Test script for Render deployment
// This file helps verify that everything is working correctly

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$tests = [];

// Test 1: PHP Version
$tests['php_version'] = [
    'test' => 'PHP Version Check',
    'result' => PHP_VERSION,
    'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'PASS' : 'FAIL'
];

// Test 2: Required Extensions
$required_extensions = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'curl'];
$tests['extensions'] = [
    'test' => 'Required Extensions',
    'result' => [],
    'status' => 'PASS'
];

foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $tests['extensions']['result'][$ext] = $loaded ? 'LOADED' : 'MISSING';
    if (!$loaded) {
        $tests['extensions']['status'] = 'FAIL';
    }
}

// Test 3: Database Connection
$tests['database'] = [
    'test' => 'Database Connection',
    'result' => 'Not tested',
    'status' => 'SKIP'
];

try {
    require_once 'db_connect.php';
    if (isset($pdo) && $pdo) {
        $tests['database']['result'] = 'Connected successfully';
        $tests['database']['status'] = 'PASS';
    } else {
        $tests['database']['result'] = 'Connection failed';
        $tests['database']['status'] = 'FAIL';
    }
} catch (Exception $e) {
    $tests['database']['result'] = 'Error: ' . $e->getMessage();
    $tests['database']['status'] = 'FAIL';
}

// Test 4: Environment Variables
$tests['environment'] = [
    'test' => 'Environment Variables',
    'result' => [],
    'status' => 'PASS'
];

$env_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DATABASE_URL'];
foreach ($env_vars as $var) {
    $value = getenv($var);
    $tests['environment']['result'][$var] = $value ? 'SET' : 'NOT SET';
    if (!$value && in_array($var, ['DB_HOST', 'DB_NAME'])) {
        $tests['environment']['status'] = 'WARN';
    }
}

// Test 5: File Permissions
$tests['permissions'] = [
    'test' => 'File Permissions',
    'result' => [],
    'status' => 'PASS'
];

$directories = ['api', 'public_html', 'vendor'];
foreach ($directories as $dir) {
    $readable = is_readable($dir);
    $tests['permissions']['result'][$dir] = $readable ? 'READABLE' : 'NOT READABLE';
    if (!$readable) {
        $tests['permissions']['status'] = 'FAIL';
    }
}

// Test 6: API Files
$tests['api_files'] = [
    'test' => 'API Files Check',
    'result' => [],
    'status' => 'PASS'
];

$api_files = ['api/login.php', 'api/register.php', 'api/profile.php'];
foreach ($api_files as $file) {
    $exists = file_exists($file);
    $tests['api_files']['result'][$file] = $exists ? 'EXISTS' : 'MISSING';
    if (!$exists) {
        $tests['api_files']['status'] = 'FAIL';
    }
}

// Calculate overall status
$overall_status = 'PASS';
foreach ($tests as $test) {
    if ($test['status'] === 'FAIL') {
        $overall_status = 'FAIL';
        break;
    } elseif ($test['status'] === 'WARN' && $overall_status !== 'FAIL') {
        $overall_status = 'WARN';
    }
}

$response = [
    'status' => $overall_status,
    'message' => 'Render deployment test results',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ],
    'tests' => $tests
];

echo json_encode($response, JSON_PRETTY_PRINT);
?> 