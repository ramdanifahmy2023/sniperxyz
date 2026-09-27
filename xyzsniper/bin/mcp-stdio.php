#!/usr/bin/env php
<?php
/**
 * Stdio adapter for local MCP clients.
 * It proxies one JSON-RPC message per stdin line to the HTTP MCP endpoint.
 */

$url = rtrim((string) (getenv('SNIPER_MCP_URL') ?: 'http://127.0.0.1:8000/mcp'), '/');
$token = trim((string) getenv('SNIPER_MCP_TOKEN'));
$tokenFile = trim((string) getenv('SNIPER_MCP_TOKEN_FILE'));
if ($token === '' && $tokenFile !== '' && is_readable($tokenFile)) {
  $token = trim((string) file_get_contents($tokenFile));
}

if ($token === '') {
  fwrite(STDERR, "SNIPER_MCP_TOKEN or SNIPER_MCP_TOKEN_FILE is required.\n");
  exit(2);
}

while (($line = fgets(STDIN)) !== false) {
  $line = trim($line);
  if ($line === '') continue;
  $request = json_decode($line, true);
  if (!is_array($request)) {
    fwrite(STDERR, "Invalid JSON-RPC input.\n");
    echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Invalid JSON.']]) . "\n";
    fflush(STDOUT);
    continue;
  }

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $token,
      'Content-Type: application/json',
      'Accept: application/json, text/event-stream',
      'MCP-Protocol-Version: 2025-11-25'
    ],
    CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 120
  ]);
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($response === false || $error !== '') {
    fwrite(STDERR, "MCP HTTP proxy failed ($status): $error\n");
    if (array_key_exists('id', $request)) {
      echo json_encode(['jsonrpc' => '2.0', 'id' => $request['id'], 'error' => ['code' => -32603, 'message' => 'MCP HTTP proxy failed.']]) . "\n";
      fflush(STDOUT);
    }
    continue;
  }

  // Notifications receive HTTP 202 with an empty body and must stay silent on stdout.
  if (trim($response) !== '') {
    echo trim($response) . "\n";
    fflush(STDOUT);
  }
}
