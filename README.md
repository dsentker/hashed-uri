# DSentker\Uri

This small PHP >7.0 library allows developers to build and validate self-hashing urls to prevent the modification of URL parts. This is a fork from [psecio/uri](https://github.com/psecio/uri).   

## Motivation
A common attack method that pentesters and actual attackers will use is to capture a URL with "id" values in it (like `/user/view?id=1234`) and manually change this value to try to bypass authorization checks. While an application should always have some kind of auth check when the URL is called, there's another step that can help to prevent URL changes: a signature value.

This signature value is built using the contents of the current URL along with a "secret" value unique to the application. This signature is then appended to the URL and can be used directly in links. When the URL is used and the request is received, the signature is then checked against the current URL values. If there's no match, the check fails.

## Installation
Installing via [Composer](https://getcomposer.org) is simple:

## Usage

`composer require dsentker/uri`  (WIP!)

This package only has one dependency, PHPUnit, and that's only a development dependency.

### Signing URLs

```php
<?php
require_once 'vendor/autoload.php';

use DSentker\Uri\Builder;

// Secret is loaded from a configuration outside of the library
$secret = $_ENV['LINK_SECRET'];
$uri = new Builder($secret);

$data = [
    'foo' => 'this is a test'
];
$url = $uri->create('http://example.com', $data);
// http://example.com?foo=this+is+a+test&_signature=90b7ac10b261213f71faaf8ce4008fdbdd037bab7192041de8d54d93a158467f
```

In this example we've created a new `Builder` instance with the secret value, and using it to create the URL based on the data and URL provided. The `$url` result has the `signature` value appended to the Query String.

You can also add a signature to a currently existing URL that already has URL parameters using the same `create` method:

```php
<?php
$url = $uri->create('http://example.com/user?test=1');
```

### Verifying URLs
The other half of the equation is the verification of a URL. The library provides the `validate` method to help with that:

```php
<?php
$requestUrl = 'http://example.com?foo=this+is+a+test&_signature=90b7ac10b261213f71faaf8ce4008fdbdd037bab7192041de8d54d93a158467f';

$valid = $uri->verify($requestUrl);
var_dump($valid); // true
```

### Expiring URLs
The library also provides the ability to create URLs that will fail validation because they've expired. To make use of this, simply pass in a third value for the `create` method call. This value should either be the number of seconds or a relative string (parsable by PHP's [strtotime](https://php.net/strtotime)) of the amount of time to add:
```php
<?php
$data = [
    'foo' => 'bar'
];
$expire = '+10 seconds';
$url = $uri->create('https://example.com', $data, $expire);
// https://example.com?foo=bar&_expires=1521661473&_signature=009e2d70add85d79e19979434e3750e682d40a3d1403ee92458fe30aece2c826

```

You'll notice the addition of a new URL parameter, the `_expires` value. This value is automatically read when the `validate` call is made to ensure the URL hasn't timed out. If it has, even if the rest of the data is correct, the result will be `false`.

Even if the attacker tries to update the `_expires` date to extend the length of the URL, the validation will fail as that's not the `_expires` value it was originally hashed with.

## Credits
The library was created by [psecio](https://github.com/psecio) and forked by [dsentker](https://github.com/dsentker) (thats me 😁) to upgrade the code for PHP 7.x applications. The implementation of a Symfony Bundle is planned.

## Submitting bugs and feature requests
Bugs and feature request are tracked on GitHub.

## TODO
* Hash not only the query string, but the entire PATH part of the URL.
* Allow query params in $base AND, additionally, in $data array - merge query parameters.
* Split the create() and verify() methods to different classes (separation of concerns)
* Make a small symfony bundle to use this with Twig and the request object.  

## Testing
`./vendor/bin/phpunit tests`