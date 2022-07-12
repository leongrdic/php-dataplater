<?php

namespace Le\Dataplater\Tests;

use Le\Dataplater\Dataplater;
use function PHPUnit\Framework\assertEquals;

test('empty template', function () {
    $dp = new Dataplater(template: '');
    assertEquals('', stripHtml($dp->render()));
});

test('var', function () {
    $dp = new Dataplater(
        template: 'Hello <span data-dp=name></span>!',
        vars: ['name' => 'John']
    );
    $expect = 'Hello <span>John</span>!';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('utf 8', function () {
    $dp = new Dataplater(
        template: '<span data-dp-html=emoji></span>!',
        vars: ['emoji' =>  '👽']
    );
    $expect = '<span>&#128125;</span>!';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('if', function () {
    $dp = new Dataplater(
        template: '<div data-dp-if=false>does not stay</div> <div data-dp-if=true>stays</div>'
    );
    $expect = '<div>stays</div>';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('short ternary', function () {
    $dp = new Dataplater(
        template: '<div data-dp="foo ? \'hey\'">change</div> <div data-dp="!foo ? \'hey\'">no change</div>',
        vars: ['foo' => true]
    );
    $expect = '<div>hey</div> <div>no change</div>';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('ternary with non boolean', function () {
    $dp = new Dataplater(
        template: '<span data-dp="condition ? \'pass\' : \'fail\'">change</span> <span data-dp="condition === true ? \'pass\' : \'fail\'">change</span>',
        vars: ['condition' => 'this evaluates to true but it is not a boolean']
    );
    $expect = '<span>pass</span> <span>fail</span>';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('foreach', function () {
    $dp = new Dataplater(
        template: '<ul><template data-dp-foreach=list data-dp-var=item><li data-dp=item></li></template></ul>',
        vars: ['list' => ['first', 'second', 'third']]
    );
    $expect = '<ul><li>first</li><li>second</li><li>third</li></ul>';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('attributes', function () {
    $dp = new Dataplater(
        template: '<input type="text" data-dp-value=inputValue> <span data-dp-attr="data-custom ; bar"></span>',
        vars: ['inputValue' => 'some value', 'bar' => 'foo']
    );
    $expect = '<input type="text" value="some value"> <span data-custom="foo"></span>';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('html and concat', function () {
    $dp = new Dataplater(
        template: '<b data-dp-html="part[0] ~ varName ~ part[1]"></b>',
        vars: ["part" => ['<var data-dp=', '></var>'], 'varName' => 'bob', 'bob' => 'yay!']
    );
    $expect = '<b><var>yay!</var></b>';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('closure', function () {
    $dp = new Dataplater(
        template: '<i data-dp=wrap("nice")></i>',
        vars: ['wrap' => fn ($a) => "before: $a :after"]
    );
    $expect = '<i>before: nice :after</i>';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});

test('php function', function () {
    $dp = new Dataplater(
        template: '<p data-dp=php.strrev("reverse")></p>'
    );
    $expect = '<p>esrever</p>';

    assertEquals(stripHtml($expect), stripHtml($dp->render()));
});


// @todo add tests
