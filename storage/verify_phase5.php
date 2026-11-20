<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Core/Bootstrap.php';
\App\Core\Bootstrap::init(dirname(__DIR__));

use App\Models\Report;

foreach (array_keys(Report::definitions()) as $slug) {
    $res = Report::run($slug);
    echo "[PASS] {$slug} (" . count($res['rows']) . ' rows, ' . count($res['meta']['columns']) . " columns)\n";
}

$cases = ['=SUM(A1:A5)', '+1+2', '-10', '@HYPERLINK', "\tcmd", "\rcmd"];
foreach ($cases as $c) {
    $san = Report::sanitizeCsvCell($c);
    if ($san !== "'" . $c) {
        echo "[FAIL] CWE-1236 failed for {$c}\n";
        exit(1);
    }
}
echo "[PASS] CWE-1236 Formula Injection sanitizer verified for all 6 dangerous prefixes (=, +, -, @, \\t, \\r)\n";
