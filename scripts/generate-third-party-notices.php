<?php

/**
 * Regenerate docs/THIRD_PARTY_NOTICES_COMPOSER_APPENDIX.md from composer.lock.
 * Run: php scripts/generate-third-party-notices.php
 */

$root = dirname(__DIR__);
$lock = json_decode(file_get_contents($root.'/composer.lock'), true);
$composer = json_decode(file_get_contents($root.'/composer.json'), true);
$req = array_keys($composer['require'] ?? []);
$reqDev = array_keys($composer['require-dev'] ?? []);

function package_source(array $pkg): string
{
    if (! empty($pkg['homepage']) && is_string($pkg['homepage'])) {
        return $pkg['homepage'];
    }
    if (! empty($pkg['source']['url'])) {
        return $pkg['source']['url'];
    }
    $s = $pkg['support']['source'] ?? '';
    if (is_string($s) && str_starts_with($s, 'git@github.com:')) {
        return 'https://github.com/'.substr($s, 15);
    }

    return is_string($s) ? $s : '';
}

function md_row(array $pkg): string
{
    $lic = implode(' / ', $pkg['license'] ?? ['unknown']);
    $src = package_source($pkg);
    $src = $src !== '' ? $src : '—';

    return '| `'.$pkg['name'].'` | '.($pkg['version'] ?? '').' | '.$lic.' | '.$src.' |';
}

$runtimeTransitive = [];
$devTransitive = [];

foreach ($lock['packages'] ?? [] as $pkg) {
    if ($pkg['name'] === 'php') {
        continue;
    }
    if (in_array($pkg['name'], $req, true) || in_array($pkg['name'], $reqDev, true)) {
        continue;
    }
    $runtimeTransitive[] = $pkg;
}

foreach ($lock['packages-dev'] ?? [] as $pkg) {
    if (in_array($pkg['name'], $req, true) || in_array($pkg['name'], $reqDev, true)) {
        continue;
    }
    $devTransitive[] = $pkg;
}

usort($runtimeTransitive, fn ($a, $b) => strcmp($a['name'], $b['name']));
usort($devTransitive, fn ($a, $b) => strcmp($a['name'], $b['name']));

$out = "# Composer dependency appendix\n\n";
$out .= "Generated from `composer.lock`. Regenerate with:\n\n";
$out .= "```bash\nphp scripts/generate-third-party-notices.php\n```\n\n";
$out .= "## PHP runtime transitive dependencies\n\n";
$out .= "| Package | Version | License | Source |\n|---------|---------|---------|--------|\n";
foreach ($runtimeTransitive as $p) {
    $out .= md_row($p)."\n";
}
$out .= "\n## PHP development transitive dependencies\n\n";
$out .= "| Package | Version | License | Source |\n|---------|---------|---------|--------|\n";
foreach ($devTransitive as $p) {
    $out .= md_row($p)."\n";
}

$path = $root.'/docs/THIRD_PARTY_NOTICES_COMPOSER_APPENDIX.md';
file_put_contents($path, $out);
echo "Wrote {$path} (".strlen($out)." bytes)\n";
