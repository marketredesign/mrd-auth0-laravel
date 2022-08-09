# MRD Auth0 Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/marketredesign/mrd-auth0-laravel.svg?style=flat-square)](https://packagist.org/packages/marketredesign/mrd-auth0-laravel)
[![Build Status](https://img.shields.io/azure-devops/build/marketredesign/f65f3c91-a76b-44db-b3a7-3815b9938e01/19/master?style=flat-square)](https://dev.azure.com/marketredesign/Public%20Packages/_build?definitionId=19&_a=summary)
[![Code Coverage](https://img.shields.io/codecov/c/gh/marketredesign/mrd-auth0-laravel/master.svg?style=flat-square)](https://codecov.io/gh/marketredesign/mrd-auth0-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/marketredesign/mrd-auth0-laravel.svg?style=flat-square)](https://packagist.org/packages/marketredesign/mrd-auth0-laravel)

Wrapper to easily configure Auth0 with a Laravel application.

Also includes a logger for NewRelic.

## Getting Started

### Prerequisites

* PHP 7.4 or higher
* PHP JSON extension
* PHP mbstring extension
* PHP XML extension
* PHP Curl extension
* Laravel 6 or higher

### Installing

You can install the package via composer:

```bash
composer require marketredesign/mrd-auth0-laravel
```

For configuration, the default config files can be published using the following command.
```bash
php artisan vendor:publish
```
Select the option for `Marketredesign\MrdAuth0Laravel\MrdAuth0LaravelServiceProvider`. This creates the config file
`config/mrd-auth0.php`.

## Upgrade to v2
See the [UPGRADE](UPGRADE.md) guide for instructions when updating an application that uses v1 to v2.

## Usage
See [laravel-auth0](https://github.com/auth0/laravel-auth0) for instructions on how to configure 
authentication / authorization of users.

### Authorizing dataset access
Add the `dataset.access` middleware to the API route. Then, make sure the dataset ID is specified using either 
`dataset_id` or `datasetId`. It can be part of the route itself or part of the request data (query param, 
request body, etc.) 

### Requesting machine-to-machine tokens from Auth0
Use `Auth0` facade. Can be used to retrieve a machine-to-machine token, only when running in console (e.g. from async
job). The tokens are automatically cached for half their expiration time.
When testing a function that retrieves a m2m token, execute `Auth0::fake()` to use a mocked Auth0Repository which does
not make any API calls to Auth0. The fake repository can be influenced using the `Auth0::fake...()` functions.

### User repository
Use `Users` facade. Can be used to retrieve a single user, or multiple users, by ID.
Also includes functionality to retrieve multiple users by email addresses.
When testing a function that uses the UserRepository (or Facade), execute `Users::fake()` to use a mocked UserRepository
which does not make any API calls to Auth0. The fake repository can be influenced using `Users::fake...()` methods.

### Dataset repository
Use `Datasets` facade. Can be used to retrieve authorized datasets for the current user making the API request.
When testing a function that uses the DatasetRepository (or Datasets facade), execute `Datasets::fake()` to use a mocked
version of the DatasetRepository that does not make any API calls to the underlying user tool API. The fake repository
can be influenced using the `Datasets::fake...()` methods.

### Logging to NewRelic
Create a new logger in the `config/logging.php` file (example code below) and make sure that the `NEWRELIC_LICENSE_KEY`
is set. Then, this logger can be selected as any other logger, for example by setting the `LOG_CHANNEL` to `'newrelic'`.

This logger looks for optional `app.repository` and `app.version` config values to log along with every 
logged message, so add those fields to the `config/app.php` file if you want this to be included. 

<details>
<summary>Example code</summary>

Logger for in `config/logging.php`:
```php
'newrelic' => [
    'driver' => 'custom',
    'via' => \Marketredesign\MrdAuth0Laravel\Logging\NewRelicLogger::class,
    'license_key' => env('NEWRELIC_LICENSE_KEY'),
],
```

Optional `app.repository` and `app.version` config values for in `config/app.php`:
```php
    /*
    |--------------------------------------------------------------------------
    | Repository Name
    |--------------------------------------------------------------------------
    |
    | The name of the repository this application is an instance of.
    | Used for example when logging to NewRelic.
    |
    */
    'repository' => 'your-repository-name-here',
    
    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | Version name of the code currently. When developing, this will be local.
    | When the code is being built, a version.txt document at the root should
    | be created containing the version number (or other build specification
    | such as the commit hash), which is then loaded into this config variable.
    |
    */
    'version' => file_exists('../version.txt') ? file('../version.txt')[0] : 'local',
```
</details>

## Running the tests

Simply run:

```bash
vendor/bin/phpunit
```

## Authors

* **Marijn van der Horst** - *Initial work*

See also the list of [contributors](https://github.com/marketredesign/mrd-auth0-laravel/contributors) who participated in this project.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
