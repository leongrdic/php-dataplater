<?php

require_once __DIR__ . '/vendor/autoload.php';

function stripHtml(string $html): string
{
    $search = [
        '/(\n|^)(\x20+|\t)/',
        '/(\n|^)\/\/(.*?)(\n|$)/',
        '/\n/',
        '/(\x20+|\t)/', // delete multi space (without \n)
        '/>\s+</', // strip whitespaces between tags
        '/(\"|\')\s+>/', // strip whitespaces between quotes ("') and end tags
        '/=\s+(\"|\')/' // strip whitespaces between = and quotes ("')
    ];

    $replace = [
        "\n",
        "\n",
        " ",
        " ",
        "><",
        "$1>",
        "=$1"
    ];

    return preg_replace($search, $replace, $html);
}

$dp = new Le\Dataplater\Dataplater(template: <<<END
<div data-dp-include=include.html></div>
<ul>
    <template data-dp-foreach=['first','second','third'] data-dp-var=site data-dp-key=number>
        <li data-dp=number+1~'-'~site></li>
        <li data-dp=number+1~'-2'~site></li>
    </template>
    <li data-dp-if=false>this will disappear</li>
</ul>
<div data-dp=name></div>
<span data-dp=test?:'custom'>default</span>
<var data-dp=1+3></var>
<div data-dp=php.implode(',',php.explode('-','this-is-a-sentence'))></div>
<span data-dp-html=element></span>
END);

$result = $dp->render([
    'name' => 'John',
    'age' => 30,
    'test' => '',
    'element' => '<div data-dp=age>this is some string</div>',
]);

echo $result;
return;
echo stripHtml($result);