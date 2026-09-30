<?php

final class CaptureInput {
  public const BODY_LIMIT = 4 * 1024 * 1024;

  public static function host($value): string {
    $value = trim((string) $value);
    if (!preg_match('#^https?://#i', $value)) $value = 'https://' . $value;
    $host = strtolower(rtrim((string) parse_url($value, PHP_URL_HOST), '.'));
    $port = parse_url($value, PHP_URL_PORT);
    return preg_replace('/^www\./', '', $host) . ($port ? ':' . $port : '');
  }

  public static function matches(string $host, string $domain): bool {
    return $domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain));
  }

  public static function sensitive(string $key): bool {
    return (bool) preg_match('/password|passwd|authorization|cookie|token|secret|api[-_]?key|csrf|otp/i', $key);
  }

  public static function redact($value, int $depth = 0) {
    if ($depth > 30) return '[depth limit]';
    if (is_array($value)) {
      foreach ($value as $key => &$child) $child = self::sensitive((string) $key) ? '[REDACTED]' : self::redact($child, $depth + 1);
      return $value;
    }
    if (is_string($value)) {
      $parsed = json_decode($value, true);
      if (is_array($parsed)) return json_encode(self::redact($parsed, $depth + 1), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return preg_replace('/((?:password|passwd|token|secret|api_key|csrf|otp)=)[^&\s]*/i', '$1[REDACTED]', $value);
    }
    return $value;
  }

  public static function date($value): ?string {
    if ($value === null || $value === '') return null;
    try {
      $date = is_numeric($value) ? new DateTimeImmutable('@' . sprintf('%.3F', (float) $value / 1000)) : new DateTimeImmutable((string) $value);
      return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    } catch (Throwable $e) { return null; }
  }

  public static function normalize(array $input, array $projects): array {
    $pageUrl = (string) ($input['pageUrl'] ?? $input['referer'] ?? '');
    $url = (string) ($input['request_url'] ?? $input['url'] ?? $pageUrl);
    if (!preg_match('#^https?://#i', $url) || strlen($url) > 16384 || strlen($pageUrl) > 16384) throw new InvalidArgumentException('URL tidak valid.');
    $project = null; $score = -1; $tie = false;
    foreach ($projects as $candidate) {
      if (!empty($input['project_id']) && (int) $candidate['id'] !== (int) $input['project_id']) continue;
      $domain = self::host($candidate['domain']);
      if (!self::matches(self::host($pageUrl), $domain)) continue;
      $candidateScore = strlen($domain);
      if ($candidateScore > $score) { $project = $candidate; $score = $candidateScore; $tie = false; }
      elseif ($candidateScore === $score) $tie = true;
    }
    if (!$project || $tie) throw new InvalidArgumentException('Project atau domain halaman tidak cocok/ambigu.');
    $eventId = (string) ($input['event_id'] ?? $input['eventId'] ?? bin2hex(random_bytes(16)));
    if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $eventId)) throw new InvalidArgumentException('event_id tidak valid.');
    $session = (string) ($input['session_id'] ?? $input['sessionId'] ?? '');
    if ($session !== '' && !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $session)) throw new InvalidArgumentException('session_id tidak valid.');
    $method = strtoupper((string) ($input['method'] ?? 'GET'));
    if (!preg_match('/^[A-Z]{1,16}$/', $method)) throw new InvalidArgumentException('Method tidak valid.');
    $source = (string) ($input['source_type'] ?? $input['type'] ?? 'unknown');
    if (!in_array($source, ['fetch','xhr','snapshot','interaction','websocket','eventsource','unknown'], true)) throw new InvalidArgumentException('Source tidak didukung.');
    $meta = ['collector_version' => $input['collector_version'] ?? 'legacy', 'source_trust' => 'page', 'redaction' => 'sensitive_keys'];
    foreach (['requestBodyFormat','responseBodyFormat','error','response_url','frame_url','request_content_type','page_title','snapshot_context','stream_id','chunk_index','capture_reason','original_response_size'] as $key) {
      if (isset($input[$key])) $meta[$key] = self::redact($input[$key]);
    }
    $bodies = [];
    foreach (['requestBody','responseBody'] as $key) {
      $body = self::redact($input[$key] ?? null);
      $body = $body === null ? null : (is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
      if ($body !== null && strlen($body) > self::BODY_LIMIT) throw new InvalidArgumentException($key . ' melebihi 4 MiB.');
      $bodies[$key] = $body;
    }
    $headers = self::redact(is_array($input['requestHeaders'] ?? null) ? $input['requestHeaders'] : []);
    $responseHeaders = self::redact(is_array($input['responseHeaders'] ?? null) ? $input['responseHeaders'] : []);
    if (strlen(json_encode([$headers,$responseHeaders,$meta])) > 256 * 1024) throw new InvalidArgumentException('Metadata terlalu besar.');
    $contentType = (string) ($input['content_type'] ?? '');
    foreach ($responseHeaders as $key => $value) if (strtolower($key) === 'content-type') $contentType = (string) $value;
    $originHost = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
    $originPort = parse_url($url, PHP_URL_PORT);
    $origin = strtolower((string) parse_url($url, PHP_URL_SCHEME)) . '://' . $originHost . ($originPort ? ':' . $originPort : '');
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $methodKey = strtolower($method) === 'options' ? 'option' : strtolower($method);
    return [
      'project_id' => (int) $project['id'], 'path' => $path, 'origin' => $origin,
      'endpoint_key' => hash('sha256', $origin . '|' . $method . '|' . $path),
      'methods' => [$methodKey => 1], 'event_id' => $eventId, 'session_id' => $session,
      'session_title' => $input['session_title'] ?? null, 'session_started_at' => self::date($input['session_started_at'] ?? null),
      'sequence_no' => max(0, (int) ($input['sequence_no'] ?? 0)),
      'tab_id' => $input['tab_id'] ?? null, 'frame_id' => $input['frame_id'] ?? null,
      'navigation_id' => $input['navigation_id'] ?? null, 'source_type' => $source,
      'occurred_at' => self::date($input['occurred_at'] ?? $input['started_at'] ?? null),
      'request_url' => self::redact($url), 'host' => self::host($url), 'content_type' => $contentType,
      'request_headers' => $headers, 'response_headers' => $responseHeaders,
      'request_body' => $bodies['requestBody'], 'response_body' => $bodies['responseBody'],
      'request_size' => strlen($bodies['requestBody'] ?? ''), 'response_size' => strlen($bodies['responseBody'] ?? ''),
      'truncated' => !empty($input['truncated']), 'duration_ms' => isset($input['duration_ms']) ? max(0, (int) $input['duration_ms']) : null,
      'query_params' => self::redact((string) (parse_url($url, PHP_URL_QUERY) ?? '')),
      'status_code' => isset($input['status']) ? max(0, (int) $input['status']) : null,
      'http_method' => $method, 'referer' => self::redact($pageUrl),
      'payload_hash' => hash('sha256', $method . '|' . $url . '|' . ($bodies['requestBody'] ?? '')),
      'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'capture_version' => 2
    ];
  }
}
