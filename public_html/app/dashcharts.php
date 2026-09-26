<?php
// Dependency-free SVG charts for the staff dashboard (no Chart.js needed).
declare(strict_types=1);

function trend_of(float $cur, float $prev): array
{
    if ($prev <= 0) {
        return ['dir' => $cur > 0 ? 'up' : 'flat', 'pct' => null];
    }
    $pct = ($cur - $prev) / $prev * 100;
    return ['dir' => $pct > 0.5 ? 'up' : ($pct < -0.5 ? 'down' : 'flat'), 'pct' => $pct];
}

function trend_badge(?array $t): string
{
    if (!$t || $t['pct'] === null) {
        return '<span class="trend flat">new</span>';
    }
    $arrow = $t['dir'] === 'up' ? '▲' : ($t['dir'] === 'down' ? '▼' : '●');
    return '<span class="trend ' . $t['dir'] . '">' . $arrow . ' ' . number_format(abs($t['pct']), 0) . '%</span>';
}

function svg_sparkline(array $vals, int $w = 110, int $h = 30, string $color = '#DE7FB8'): string
{
    $n = count($vals);
    if ($n < 2) {
        return '';
    }
    $max = max($vals);
    $min = min($vals);
    $span = max(0.0001, $max - $min);
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = round($i / ($n - 1) * ($w - 4) + 2, 1);
        $y = round($h - 3 - (($v - $min) / $span) * ($h - 8), 1);
        $pts[] = "$x,$y";
    }
    $area = "2,$h " . implode(' ', $pts) . ' ' . ($w - 2) . ",$h";
    return '<svg class="spark" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
        . '<polygon points="' . $area . '" fill="' . $color . '22"/>'
        . '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round"/></svg>';
}

/** $days: list of ['label' => '26 Sep', 'value' => 123]. */
function svg_area_chart(array $days): string
{
    $w = 620;
    $h = 170;
    $padL = 8;
    $padB = 20;
    $vals = array_column($days, 'value');
    $n = count($days);
    if (!$n) {
        return '<p class="mut">No sales yet.</p>';
    }
    $max = max(1, max($vals));
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = round($padL + $i / max(1, $n - 1) * ($w - $padL - 8), 1);
        $y = round($h - $padB - ($v / $max) * ($h - $padB - 12), 1);
        $pts[] = [$x, $y];
    }
    $sp = array_map(fn($p) => $p[0] . ',' . $p[1], $pts);
    $area = $padL . ',' . ($h - $padB) . ' ' . implode(' ', $sp) . ' ' . ($w - 8) . ',' . ($h - $padB);
    return '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" style="width:100%;height:auto;display:block" role="img">'
        . '<defs><linearGradient id="revfill" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="#DE7FB8" stop-opacity=".45"/><stop offset="1" stop-color="#DE7FB8" stop-opacity=".05"/>'
        . '</linearGradient></defs>'
        . '<polygon points="' . $area . '" fill="url(#revfill)"/>'
        . '<polyline points="' . implode(' ', $sp) . '" fill="none" stroke="#DE7FB8" stroke-width="2.5"/>'
        . '<text x="' . $padL . '" y="' . ($h - 5) . '" font-size="10" fill="#8A7584">' . e($days[0]['label']) . '</text>'
        . '<text x="' . ($w - 8) . '" y="' . ($h - 5) . '" font-size="10" fill="#8A7584" text-anchor="end">' . e($days[$n - 1]['label']) . '</text></svg>';
}

/** $parts: list of ['label' =>, 'value' =>, 'color' =>]. */
function svg_donut(array $parts): string
{
    $total = max(0.0001, array_sum(array_column($parts, 'value')));
    $r = 54;
    $c = 2 * M_PI * $r;
    $off = 0;
    $segs = '';
    foreach ($parts as $p) {
        $frac = $p['value'] / $total;
        if ($frac <= 0) {
            continue;
        }
        $len = $frac * $c;
        $segs .= '<circle cx="70" cy="70" r="' . $r . '" fill="none" stroke="' . $p['color'] . '" stroke-width="22" '
            . 'stroke-dasharray="' . round($len, 1) . ' ' . round($c - $len, 1) . '" stroke-dashoffset="' . round(-$off, 1) . '" transform="rotate(-90 70 70)"/>';
        $off += $len;
    }
    $leg = '';
    foreach ($parts as $p) {
        if ($p['value'] <= 0) {
            continue;
        }
        $leg .= '<li><span class="dot" style="background:' . $p['color'] . '"></span>' . e($p['label'])
            . ' <strong>' . round($p['value'] / $total * 100) . '%</strong></li>';
    }
    $big = $total >= 1000000 ? round($total / 1000000, 1) . 'M' : round($total / 1000) . 'k';
    return '<div class="donut-top"><svg width="150" height="150" viewBox="0 0 140 140" role="img">' . $segs
        . '<text x="70" y="77" text-anchor="middle" font-size="18" font-weight="700" fill="#241A21">' . $big . '</text></svg></div>'
        . '<ul class="legend-row">' . $leg . '</ul>';
}
