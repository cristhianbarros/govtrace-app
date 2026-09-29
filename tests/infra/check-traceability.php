<?php

/*
 * Trazabilidad (CLAUDE.md, R-TST-04): todo Escenario de features/ tiene un
 * test con su nombre — un Pest o un Vitest ("Escenario: lo que prueba"),
 * un test del contrato en Rust (en snake_case) o una sección de un
 * script de tests/infra. make trace-check; corre en el contenedor app.
 */

$root = dirname(__DIR__, 2);

$normalize = fn (string $text) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
$snake = fn (string $text) => trim(preg_replace('/[^\p{L}\p{N}]+/u', '_', $normalize($text)), '_');

$sources = [];
foreach (['tests', 'resources/js', 'contracts/sealing/src', 'tools/verify/test'] as $dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = $file->getPathname();
        $isTest = match ($file->getExtension()) {
            'php' => str_starts_with($path, "{$root}/tests/"),
            'js', 'mjs' => (bool) preg_match('/\.(test|spec)\.m?js$/', $path),
            'rs', 'sh' => true,
            default => false,
        };
        if ($isTest && ! str_contains($path, 'node_modules') && ! str_ends_with($path, 'check-traceability.php')) {
            $sources[] = $normalize(file_get_contents($path));
        }
    }
}
$haystack = implode("\n", $sources);

$total = 0;
$missing = [];
foreach (glob("{$root}/features/*.feature") as $feature) {
    if (basename($feature) === 'ejemplo_formato.feature') {
        continue;
    }
    foreach (file($feature) as $line) {
        if (! preg_match('/^\s*(Escenario|Esquema del escenario):\s*(.+)$/u', $line, $match)) {
            continue;
        }
        $total++;
        $title = $normalize($match[2]);
        if (! str_contains($haystack, $title) && ! str_contains($haystack, $snake($match[2]))) {
            $missing[] = basename($feature).': '.trim($match[2]);
        }
    }
}

foreach ($missing as $scenario) {
    echo "FAIL  sin un test con su nombre — {$scenario}\n";
}
echo ($missing === [] ? 'PASS' : 'FAIL')."  {$total} escenarios, ".($total - count($missing))." con un test que lleva su nombre\n";

exit($missing === [] ? 0 : 1);
