<?php
// Split full dump into per-table migrations + FKs + seed inserts
$dump = __DIR__ . '/../../jobsahi_database_shared_new.sql';
$outDir = __DIR__ . '/../migrations';
if (!file_exists($dump)) die("Dump not found: $dump\n");
if (!is_dir($outDir)) mkdir($outDir, 0777, true);

$sql = file_get_contents($dump);

// Normalize line endings
$sql = str_replace("\r\n", "\n", $sql);

// Collect CREATE TABLE blocks
$createBlocks = [];
$pattern = '/CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((?:.|\n)*?\)\s*ENGINE=.*?;/i';
preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
  $table = $m[1];
  $block = $m[0];
  $createBlocks[$table] = $block;
}

// Collect ALTER TABLE FKs / indexes
$alters = [];
$alterPattern = '/ALTER\s+TABLE\s+`?([A-Za-z0-9_]+)`?.*?;/is';
preg_match_all($alterPattern, $sql, $am, PREG_SET_ORDER);
foreach ($am as $a) { $alters[] = trim($a[0]); }

// Collect INSERTS
$inserts = [];
$insertPattern = '/INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?.*?;/is';
preg_match_all($insertPattern, $sql, $im, PREG_SET_ORDER);
foreach ($im as $ins) { $inserts[] = trim($ins[0]); }

// Order tables to reduce FK issues: naive parent-first by name (good enough when FKs applied later)
ksort($createBlocks);

// Write per-table files
$idx = 1;
foreach ($createBlocks as $table => $block) {
  $fname = sprintf("%s/20251024_%04d_create_%s_table.sql", $outDir, $idx, $table);
  file_put_contents($fname, $block . "\n");
  $idx++;
}

// Write FKs
if (count($alters)) {
  $fkFile = sprintf("%s/20251024_%04d_add_foreign_keys.sql", $outDir, $idx);
  file_put_contents($fkFile, implode("\n", $alters) . "\n");
  $idx++;
}

// Write seed data
if (count($inserts)) {
  $seedFile = sprintf("%s/20251024_%04d_insert_seed_data.sql", $outDir, $idx);
  file_put_contents($seedFile, implode("\n", $inserts) . "\n");
}

// Done
echo "Created migrations in $outDir\n";
