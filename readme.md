# Dataplater

Dataplater is a templating engine written in PHP that uses HTML data-attributes and keeps templates valid and clean.
This makes Dataplater perfect for creating document templates like invoices, contracts, emails, etc. which can be previewed in the browser before rendering.

## Features
- 💻 make HTML templates that look great even before rendering
- 💾 use data-attributes to pass data into your template
- ⚡ powerful expression language ([SMPLang](https://github.com/leongrdic/php-smplang))
- 📩 automatic escaping
- 👍 UTF-8 support
- 🧮 access to php functions in expressions
- 🔗 include HTML files
- ➿ nested foreach loops
- 🔤 set inner HTML or custom attributes of elements
- 🗑️ delete elements based on conditions


## Examples

```html
This is <var data-dp=php.strrev(`emosewa`)></var>
<!-- line above becomes: -->
This is <var>awesome</var>
```


I actually created an [invoice template](https://leongrdic.github.io/php-dataplater/invoice.html) for Dataplater that's used in production.
The template's source code is [here](examples/invoice.html).

## Install
```shell
composer require leongrdic/dataplater
```

### Requirements

- PHP 8.1+
- DOM & XML extensions

## Usage

```php
$dp = new Le\Dataplater\Dataplater(
    filename: 'template.html', // path relative to baseDir or an absolute path
    // or
    template: '<html>...</html>', // doesn't have to be a full HTML document
    
    // optional:
    vars: [ 'var' => 'value', ],
    baseDir: 'app/templates/' // used for includes (defaults to '.')
);
```

When creating an object, pass your template filename as the first constructor parameter.

If you instead want to load template HTML from a string, skip the first param using named arguments and provide only the `template` param.

To define global vars that will be accessible in all renders, pass them to the `vars` param.

If you want to use a different base directory for includes, pass it to the `baseDir` param.

You can now reuse this `Le\Dataplater\Dataplater` object to render multiple documents using different vars.

```php
$html = $dp->render([
    'var' => 'local value',
    'function' => fn () => 'some value',
]);
```

The `render()` method renders the template and returns the rendered HTML as a string.

If any variables are passed, they will be used to render the template and override any global vars (passed in the object constructor).

You can call this method multiple times on the same object with different vars to render multiple different documents.


## Attribute reference

All following `data-dp` attributes are removed from the template after rendering.

### `data-dp-include`
**Value**: filename of the HTML template to include.

Content of the included file will be inserted into the template replacing the element with the `data-dp-include` attribute.

```html
<template data-dp-include="include.html"></template>
```

### `data-dp-if`
**Value**: SMPL expression

If the expression evaluates to `true`, the element will be rendered, otherwise it will be removed.

```html
<div data-dp-if=false>this element will always be removed</div>

<div data-dp-if="id > 1">this will be rendered if the condition is met</div>
```

### `data-dp-foreach`
**Value**: SMPL expression

**Additional attributes**:
- `data-dp-key`: variable name to use for the current element key (optional)
- `data-dp-value`: variable name to use for the current element value 

The expression must evaluate to an array or an iterable object.
Children of the element with the `data-dp-foreach` attribute will be copied for each iteration and the `data-dp-key` and `data-dp-value` variables will be set to the current element key and value to be used in those child elements.

The element containing the `data-dp-foreach` attribute will be removed after the loop.

```html
<ul>
    <template data-dp-foreach=['google','youtube'] data-dp-var=name data-dp-key=id>
        <li data-dp="id+1 ~ '. ' ~ name"></li>
    </template>
</ul>

<template data-dp-foreach=users data-dp-var=user>
    <a data-dp-href=user.url data-dp=user.name></a>
</template>
```

### `data-dp-html`
**Value**: SMPL expression

If the expression evaluates to `null`, no action will be taken (the element will be rendered without any modifications).

The expression otherwise must evaluate to a string. The string will be inserted into the element as HTML replacing the element's content.

The inserted HTML will have only `data-dp`, `data-dp-attr` and attribute shortcuts rendered which means that other attributes like `data-dp-if` or `data-dp-foreach` will be ignored.

```html
<div data-dp-html="'<b>' ~ name ~ '</b>'"></div>

<div data-dp-html="'<b data-dp=name></b>'"></div>
```
Note the quotes around the expression in the second example - the outer quotes are defining content of the HTML attribute and the inner quotes are defining a string within the SMPL expression.

### `data-dp`
**Value**: SMPL expression

If the expression evaluates to `null`, no action will be taken (the element will be rendered without any modifications).

The expression otherwise must evaluate to a string. The string will be inserted into the element as escaped text replacing the element's content.

```html
<span data-dp=message></span>

<var data-dp="balance > 0 ? balance : 'empty balance'"></var>

<label data-dp=`cool`></label>
```

Note: HTML interprets the attribute from the last example as: <code>data-dp="\`cool\`"</code>, and SMPLang will interpret <code>\`cool\`</code> as a string literal. If you wrote `data-dp="cool"` instead, SMPLang would look for a variable called `cool` and return its value.

### `data-dp-attr`
**Value**: `attribute name ; SMPL expression`

If the expression evaluates to `null`, the attribute won't be set, and it's value will be left as is (if any).

The expression otherwise must evaluate to a string. The string will be escaped and inserted as the attribute value for the desired attribute.

Dataplater also provides a few shortcut for the following attributes: `id` `class` `title` `alt` `value` `href` `src` `style` using the syntax: **data-dp-`attribute`**.

```html
<span data-dp-attr="title ; message"></span>
<!-- or -->
<var data-dp-title=message></var>

<div data-dp-class="!hidden ? 'show'"></div>
```

## Expression reference

Dataplater uses the [SMPLang](https://github.com/leongrdic/php-smplang) expression language. Refer to the SMPLang readme for more information about the syntax and supported operators.

Vars from Dataplater are accessible as variables in the SMPL expression.

Dataplater also provides a global object `php` which is basically a proxy to all php functions. Use it to access any PHP function from within your expressions:

```
php.count(users) > 0

php.array_reverse(links)

php.implode('-', php.explode(' ', someText))
```

Keep in mind you can still pass your own closure variables and use them in your templates.

When the expression returns a closure, Dataplater will attempt to call it (without any params) and use the result instead of the closure.

Dataplater wraps all SMPL exceptions in a `Le\Dataplater\ParseException` which gives you access to the line number and the causing HTML element.

## Rendering order

1. Includes are inserted (single level only)
2. `if` checks are performed (except for those in loops)
3. Loops are performed (recursively) and their contents are fully rendered (which allows for nested loops; including `if` checks)
4. HTML insertions are performed
5. Text and attribute insertions are performed

## Notes

Dataplater heavily relies on the PHP DOM extension and all escaping is done by the DOM extension natively, so passing user-generated data into Dataplater vars should be safe, although if you do use data from untrusted sources, make sure proper validations is done.

Also keep in mind that DOM extension sometimes reformats HTML (like adds attribute brackets, removes closing tags if unnecessary, etc.) and that the code coming out of Dataplater is not going to be indented well.

Any contributions to this project are welcome!
