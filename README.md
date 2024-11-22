# Express adapter for wsmallnews system delivery modules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wsmallnews/express.svg?style=flat-square)](https://packagist.org/packages/wsmallnews/express)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/wsmallnews/express/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/wsmallnews/express/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/wsmallnews/express/fix-php-code-styling.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/wsmallnews/express/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wsmallnews/express.svg?style=flat-square)](https://packagist.org/packages/wsmallnews/express)



This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require wsmallnews/express
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="express-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="express-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="express-views"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$express = new Wsmallnews\Express();
echo $express->echoPhrase('Hello, Wsmallnews!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [smallnews](https://github.com/Wsmallnews)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
