<?php
// Global backend configuration (server + localhost)
// - Centralize secrets and runtime options here.
// - Do NOT expose this file to the frontend directly.

// CORS defaults
if (!defined('API_ALLOW_ORIGIN')) {
  define('API_ALLOW_ORIGIN', '*');
}
if (!defined('API_ALLOW_METHODS')) {
  define('API_ALLOW_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
}
if (!defined('API_ALLOW_HEADERS')) {
  define('API_ALLOW_HEADERS', 'Content-Type, Authorization');
}
if (!defined('API_MAX_AGE')) {
  define('API_MAX_AGE', '86400');
}

// OpenAI or other third-party API keys (read from environment or fallback)
if (!defined('OPENAI_API_KEY')) {
  define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY');
}
if (!defined('OPENAI_BASE_URL')) {
  define('OPENAI_BASE_URL', getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1');
}
if (!defined('OPENAI_MODEL')) {
  define('OPENAI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-3.5-turbo');
}

// Common helpers
function api_send_cors_headers(): void {
  header('Access-Control-Allow-Origin: ' . API_ALLOW_ORIGIN);
  header('Access-Control-Allow-Methods: ' . API_ALLOW_METHODS);
  header('Access-Control-Allow-Headers: ' . API_ALLOW_HEADERS);
  header('Access-Control-Max-Age: ' . API_MAX_AGE);
  header('Content-Type: application/json; charset=utf-8');
}

function api_handle_options_and_exit(): void {
  if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
  }
}

// Safe JSON output (avoid stray output under HTTP/2)
function api_json_response(array $payload, int $status = 200): void {
  http_response_code($status);
  // Clean buffers if any
  while (ob_get_level() > 0) { ob_end_clean(); }
  echo json_encode($payload);
  exit;
}

// Toggle PHP error display for production safety
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
