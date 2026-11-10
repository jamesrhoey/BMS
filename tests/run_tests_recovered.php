<?php
$file = 'C:\\Users\\COR\\.gemini\\antigravity\\brain\\d7f4d299-bfee-48d4-94b5-bb33c1c11c86\\.system_generated\\logs\\transcript_full.jsonl';
$fp = fopen($file, 'r');
if (!$fp) {
    die("Cannot open transcript_full.jsonl\n");
}

$lastRunTestsContent = null;
$lastStep = null;

$lineNum = 0;
while (($line = fgets($fp)) !== false) {
    $lineNum++;
    if (strpos($line, 'run_tests.php') !== false) {
        $json = json_decode($line, true);
        if ($json && isset($json['tool_calls'])) {
            foreach ($json['tool_calls'] as $tc) {
                if ($tc['name'] === 'write_file' && str_contains($tc['args']['Path'] ?? '', 'run_tests.php')) {
                    $lastRunTestsContent = $tc['args']['Content'];
                    $lastStep = $json['step_index'] ?? $lineNum;
                    echo "Found write_file on run_tests.php at line {$lineNum}, step {$lastStep}, length " . strlen($lastRunTestsContent) . "\n";
                }
            }
        }
    }
}
fclose($fp);

if ($lastRunTestsContent) {
    file_put_contents('C:\\xampp\\htdocs\\BMS\\tests\\run_tests_recovered.php', $lastRunTestsContent);
    echo "Saved recovered run_tests to tests/run_tests_recovered.php (step {$lastStep})\n";
} else {
    echo "No write_file found in transcript_full.jsonl\n";
}
