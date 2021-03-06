# Changelog

All notable changes to `php-dataplater` will be documented in this file.

## 3.1.2 - 2022-07-11

Fix: besides string, expressions can now also return numbers and bools without having an exception thrown.

## 3.1.1 - 2022-06-29

an exception will now be thrown:

- when expression result is of wrong type
- when missing the expression in `data-dp-attr`

## 3.1.0 - 2022-06-23

you can now pass an `attr` parameter when creating a Dataplater object in order to change the default `data-dp` attribute to a custom one.

## 3.0.0 - 2022-06-13

major breaking update!

- biggest change is now using SMPLang for expression evaluation
- now using `data-dp` instead of `data-var`
- including html files now possible
- utf8 support
- not adding doctype and html tags anymore
- added tests
- more thorough documentation with every feature covered in readme
