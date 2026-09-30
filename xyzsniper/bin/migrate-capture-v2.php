<?php
// Additive migration; take a database backup before running with --apply.
require_once __DIR__ . '/../config/define.php';
$options = getopt('', ['apply','database:']);
$name = $options['database'] ?? DB_NAME;
if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new RuntimeException('Invalid database name');
$pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . $name . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$columns = [
  'endpoints' => ['origin' => "VARCHAR(512) NOT NULL DEFAULT ''", 'endpoint_key' => 'CHAR(64) NULL'],
  'payloads' => ['account_id' => 'INT UNSIGNED NULL', 'request_header' => 'LONGTEXT NULL', 'occurred_at' => 'DATETIME(3) NULL', 'meta_json' => 'LONGTEXT NULL', 'capture_version' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1']
];
$queries = [];
foreach ($columns as $table => $defs) {
  $existing = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
  foreach ($defs as $column => $definition) if (!in_array($column, $existing, true)) $queries[] = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
}
foreach (['endpoints' => ['uq_endpoints_project_path'], 'payloads' => ['uq_payloads_endpoint_hash','uq_payload_event_id']] as $table => $indexes) {
  $existing = array_column($pdo->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
  foreach ($indexes as $index) if (in_array($index, $existing, true)) $queries[] = "ALTER TABLE `$table` DROP INDEX `$index`";
}
$queries[] = 'UPDATE payloads p JOIN endpoints e ON e.id=p.endpoint_id JOIN projects pr ON pr.id=e.project_id SET p.account_id=pr.account_id WHERE p.account_id IS NULL';
foreach (['endpoints' => ['uq_endpoint_key' => 'UNIQUE INDEX uq_endpoint_key (project_id,endpoint_key)'], 'payloads' => ['uq_account_event' => 'UNIQUE INDEX uq_account_event (account_id,event_id)', 'idx_event_time' => 'INDEX idx_event_time (session_id,occurred_at,id)']] as $table => $indexes) {
  $existing = array_column($pdo->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
  foreach ($indexes as $index => $definition) if (!in_array($index, $existing, true)) $queries[] = "ALTER TABLE `$table` ADD $definition";
}
foreach ($queries as $sql) {
  if (isset($options['apply'])) $pdo->exec($sql);
  echo (isset($options['apply']) ? 'OK ' : 'PLAN ') . $sql . PHP_EOL;
}
echo isset($options['apply']) ? "Capture v2 migration complete.\n" : "Dry run. Back up the database, then use --apply.\n";
