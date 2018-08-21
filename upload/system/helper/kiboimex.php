<?php

function mrkt_parse_dimensions($line) {
    $re_num = '(?:[0-9]+(?:[,.][0-9]+)?)';
    $re_sep = '(?:\s*x\s*)';

    $re_line = "|
        ^\s*
        (?P<l>$re_num)$re_sep
        (?P<w>$re_num)$re_sep
        (?P<d>$re_num)\s*
        (?P<unit>[A-Za-z]+)\s*$
    |x";

    if(!preg_match($re_line, $line, $match))
        return;

    return array(
        'l' => mrkt_parse_num($match['l']),
        'w' => mrkt_parse_num($match['w']),
        'd' => mrkt_parse_num($match['d']),
        'unit' => $match['unit'],
    );
}

function mrkt_parse_num($n) {
    return is_numeric($n) ? floatval($n) : floatval(str_replace(',', '.', $n));
}

function mrkt_cmp($a, $b) {
    if($a == $b)
        return 0;
    return $a < $b ? -1 : 1;
}
