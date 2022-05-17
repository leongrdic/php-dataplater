<?php

require_once __DIR__ . '/vendor/autoload.php';

$tests = [
    [
        'Hello <span data-dp=name></span>!',
        ['name' => 'John'],
        'Hello <span>John</span>!'
    ],
    [
        '<span data-dp=emoji></span> <span data-dp-html=emoji></span>!',
        ['emoji' => '👽'],
        '<span>&#128125;</span> <span>&#128125;</span>!'
    ],
    [
        '<div data-dp-if=false></div> <div data-dp-if=true>stays</div>',
        [],
        '<div>stays</div>'
    ],
    [
        '<div data-dp="foo ? \'hey\'"></div> <div data-dp="!foo ? \'hey\'"></div>',
        ['foo' => true],
        '<div>hey</div> <div></div>'
    ],
    [
        '<span data-dp="condition ? \'pass\' : \'fail\'"></span> <span data-dp="condition === true ? \'pass\' : \'fail\'"></span>',
        ['condition' => 'this evaluates to true but it is not a boolean'],
        '<span>pass</span> <span>fail</span>'
    ],
    [
        '<ul><template data-dp-foreach=list data-dp-var=item><li data-dp=item></li></template></ul>',
        ['list' => ['first', 'second', 'third']],
        '<ul><li>first</li><li>second</li><li>third</li></ul>'
    ],
    [
        '<input type="text" data-dp-value=inputValue> <span data-dp-attr="data-custom ; bar"></span>',
        ['inputValue' => 'some value', 'bar' => 'foo'],
        '<input type="text" value="some value"> <span data-custom="foo"></span>'
    ],
    [
        '<b data-dp-html="part[0] ~ varName ~ part[1]"></b>',
        ["part" => ['<var data-dp=', '></var>'], 'varName' => 'bob', 'bob' => 'yay!'],
        '<b><var>yay!</var></b>'
    ],
    [
        '<i data-dp=wrap("nice")></i>',
        ['wrap' => fn($a) => "before: $a :after"],
        '<i>before: nice :after</i>'
    ]
];

foreach($tests as $index => $test){
    echo "test $index: ";
    $dp = new Le\Dataplater\Dataplater(template: $test[0], vars: $test[1]);

    $result = stripHtml($dp->render());
    $expect = stripHtml($test[2]);

    if($result !== $expect){
        echo "\033[31m❌ FAIL\033[0m: " . $test[0] . "\n";
        echo "    expected: " . $expect . "\n";
        echo "    got: " . $result . "\n";
    }else{
        echo "\033[32m✅ PASS\033[0m\n";
    }
}

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