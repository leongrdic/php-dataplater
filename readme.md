# Dataplater

PHP template engine that uses data-attributes and keeps HTML templates valid and clean. Scroll down to see a usage example.

## Install
```
composer require leongrdic/dataplater
```

### Requirements

- PHP 8.0 +
- XML extension
- DOM extension

## Example

`template.html`:
```html
<table>
    <template data-var-foreach="users; row">
    <tr>
        <template data-var-foreach="row; cell">
        <td data-var-text="cell"></td>
        </template>
    </tr>
    </template>
</table>
<span>table has <var data-var-text="f/count, users"></var> rows</span>

<div>
    <template data-var-foreach="links; index; link">
    <a data-var-href="link.url" data-var-text='f/text.concat, index, v/" ", link.name'></a><br>
    </template>
</div>

<div data-var-if-text='f/compare.equal, balance, v/0 ; lang.balance_empty'>Your current balance is <var data-var-text="balance"></var> dollars</div>

<ul>
    <template data-var-foreach='f/text.explode, v/" ", sentence, v/4 ; word'>
    <li data-var-text="word"></li>
    </template>
</ul>
```

PHP:
```php
<?php
require_once 'vendor/autoload.php';

$dp = new \Le\Dataplater('template.html');
echo $dp->render([
    'users' => [
        ['Demo', 'User', '01.01.2020.'],
        ['John', 'Doe', '31.12.2021.'],
    ],
    'links' => [
        ['name' => 'Google', 'url' => 'https://google.com/'],
        ['name' => 'YouTube', 'url' => 'https://youtube.com/'],
        ['name' => 'Facebook', 'url' => 'https://facebook.com/']
    ],
    'items' => [
        'first',
        'second',
        'third',
        'fourth'
    ],
    'balance' => 10,
    'sentence' => 'split this sentence by spaces',
    'lang' => function(){
        return [
            'balance_empty' => 'Your balance is empty.',
            'some_text' => 'Translation'
        ];
    }
]);
```

Output:
```html
<table>
    <tr>
        <td>Demo</td>
        <td>User</td>
        <td>01.01.2020.</td>
    </tr>
    <tr>
        <td>John</td>
        <td>Doe</td>
        <td>31.12.2021.</td>
    </tr>
</table>
<span>table has <var>2</var> rows</span>

<div>
    <a href="https://google.com/">0 Google</a><br>
    <a href="https://youtube.com/">1 YouTube</a><br>
    <a href="https://facebook.com/">2 Facebook</a><br>
</div>

<div>Your current balance is <var>10</var> dollars</div>

<ul>
    <li>split</li>
    <li>this</li>
    <li>sentence</li>
    <li>by spaces</li>
</ul>
```
(not as nicely formatted as here)


## Attributes

| **attribute name**                                                    | **attribute value**                                                                                                                                                          | result                                                                                                       |
|-----------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| `data-var-[text / html]`                                              | `value expression`                                                                                                                                                           | inserts or replaces (html) content with supplied value                                                       |
| `data-var-[id / class / title / alt / value / href / src / style]`    | `value expression`                                                                                                                                                           | inserts or replaces attribute value with supplied value                                                      |
| `data-var-attr`                                                       | `attribute name ; value expression`                                                                                                                                          | adds or replaces attribute value for a specified attribute                                                   |
| `data-var-foreach`                                                    | `expression to iterate through ; variable name for value`<br/>or<br/>`expression to iterate through ; variable name for key ; variable name for value`                       | iterates through var and writes value and/or key variables                                                   |
| `data-var-if`                                                         | `condition expression`                                                                                                                                                       | removes block if condition not true                                                                          |
| `data-var-if-[text/html]`                                             | `condition expression ; value expression if true`<br/>or<br/>`condition expression ; value expression if true ; value expression if false`                                   | inserts or replaces (html) content with value expression depending on the condition expressions result       |
| `data-var-if-[id / class / title / alt / value / href / src / style]` | `condition expression ; value expression if true`<br/>or<br/>`condition expression ; value expression if true ; value expression if false`                                   | adds or replaces attribute value for the attribute depending on the condition expressions result             |
| `data-var-if-attr`                                                    | `attribute name ; condition expression ; value expression if true`<br/>or<br/>`attribute name ; condition expression ; value expression if true ; value expression if false` | adds or replaces attribute value for a specified attribute name depending on the condition expression result |


## Expression syntax:

### `variableName`
returns content of the variable

if variable is a closure, executes it and returns the result

variable names shouldn't contain characters like dots or spaces, but ideally they should only contain letters, numbers, `_` and `-`

### `variableName.arrayElement.objectProperty`
access element of array or object property when array/object is in variable

also supports closures at any depth

### `v/json`
decodes json after `/`, useful for literal values like: `v/true`, `v/false`, `v/0` or even strings, e.g. `v/"string"`

### `f/variableName, param, ...`
calls the callable in `variableName` and passed provided params (if any)

params can be encapsulated into `|` characters if your expression contains `,`


## Built-in functions

| **function**                             | **php equivalent**                | **notes**                             |
|------------------------------------------|-----------------------------------|---------------------------------------|
| `logic.and, a, b`                        | `$a && $b`                        |                                       |
| `logic.or, a, b`                         | `$a \|\| $b`                      |                                       |
| `logic.not, a`                           | `!$a`                             |                                       |
| `compare.equal, a, b`                    | `$a == $b`                        |                                       |
| `compare.different, a, b`                | `$a != $b`                        |                                       |
| `compare.exact, a, b`                    | `$a === $b`                       |                                       |
| `compare.notExact, a, b`                 | `$a !== $b`                       |                                       |
| `compare.larger, a, b`                   | `$a > $b`                         |                                       |
| `compare.largerEqual, a, b`              | `$a >= $b`                        |                                       |
| `compare.smaller, a, b`                  | `$a < $b`                         |                                       |
| `compare.smallerEqual, a, b`             | `$a <= $b`                        |                                       |
| `array.count, var`                       | `count($var)`                     | var can be a countable array or object|
| `array.reverse, var`                     | `array_reverse($var)`             |                                       |
| `text.length, string`                    | `strlen($string)`                 |                                       |
| `text.concat, string1, string2, ...`     | `implode('', $strings)`           |                                       |
| `text.implode, separator, array`         | `implode($separator, $array)`     |                                       |
| `text.explode, separator, string, limit` | `explode($separator, $string, $limit)` | limit is optional                |
| `json.decode, string`                    | `json_decode($string, true)`      |                                       |
| `json.encode, var`                       | `json_encode($var)`               |                                       |


## Class

```php
new Le\Dataplater\Dataplater(string $template, array $vars)
```

when creating an object, pass your template filename as the first constructor parameter. the second parameter is optional and can hold global variables that will be made available to each render of the template.

```php
$dataplater->render(array $vars);
```

this method renders the template using global vars together with vars optionally passed as parameter and returns the rendered HTML string. you can call it multiple times on the same dataplater object to render multiple different pages.

## Disclaimer

Use on your own responsibility, this may not be suitable for production - I honestly haven't benchmarked it.
