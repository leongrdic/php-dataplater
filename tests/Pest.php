<?php

function stripHtml(string $html): string
{
    $rules = [
        '/(\n|^)(\x20+|\t)/' => "\n",
        '/(\n|^)\/\/(.*?)(\n|$)/' => "\n",
        '/\n/' => " ",
        '/(\x20+|\t)/' => " ", // delete multi space (without \n)
        '/>\s+</' => "><", // strip whitespaces between tags
        '/(\"|\')\s+>/' => "$1>", // strip whitespaces between quotes ("') and end tags
        '/=\s+(\"|\')/' => "=$1", // strip whitespaces between = and quotes ("')
    ];

    return trim(preg_replace(array_keys($rules), array_values($rules), $html));
}
