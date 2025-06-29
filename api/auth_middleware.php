<?php

function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$authHeader) {
        throw new Exception("Authorization header missing");
    }

    
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        throw new Exception("Invalid authorization header format");
    }

    $token = $matches[1];
    $decoded = validate_jwt($token);

    if (!$decoded) {
        throw new Exception("Invalid or expired token");
    }

    return $decoded;
}