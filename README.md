# MRD Auth0 Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/marketredesign/mrd-auth0-laravel.svg?style=flat-square)](https://packagist.org/packages/marketredesign/mrd-auth0-laravel)
[![Build Status](https://img.shields.io/azure-devops/build/marketredesign/f65f3c91-a76b-44db-b3a7-3815b9938e01/19/master?style=flat-square)](https://dev.azure.com/marketredesign/Public%20Packages/_build?definitionId=19&_a=summary)
[![Code Coverage](https://img.shields.io/codecov/c/gh/marketredesign/mrd-auth0-laravel/master.svg?style=flat-square)](https://codecov.io/gh/marketredesign/mrd-auth0-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/marketredesign/mrd-auth0-laravel.svg?style=flat-square)](https://packagist.org/packages/marketredesign/mrd-auth0-laravel)

Wrapper to easily configure Auth0 with a Laravel application

## Getting Started

### Prerequisites

* PHP 7.3 or higher
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

## Usage

### Authentication
Simply redirect the user to the `/login` route (named `login`). The rest will be handled by this package. For logout
redirect to `/logout` (named `logout`).

### Authorizing API endpoints
Add the `jwt` middleware to the API route. A scope can be added by using `jwt:scope`.

### Authorizing dataset access
Add the `dataset.access` middleware to the API route. Then, make sure the dataset ID is specified using either 
`dataset_id` or `datasetId`. It can be part of the route itself or part of the request data (query param, 
request body, etc.) 

### User repository
Use `Users` facade. Can be used to retrieve a single user, or multiple users, by ID.
Also includes functionality to retrieve multiple users by email addresses.
When testing a function that uses the UserRepository (or Facade), execute `Users::fake()` to use a mocked UserRepository
which does not make any API calls to Auth0. The fake repository can be influenced using `Users::fake...()` methods.

### Dataset repository
Use `Datasets` facade. Can be used to retrieve authorized datasets for the current user making the API request.
When testing a function that uses the DatasetRepository (or Datasets face), execute `Datasets::fake()` to use a mocked
version of the DatasetRepository that does not make any API calls to the underlying user tool API. The fake repository
can be influenced using the `Datasets::fake...()` methods.

## Running the tests

Simply run:

```bash
vendor/bin/phpunit
```

## Authors

* **Marijn van der Horst** - *Initial work*

See also the list of [contributors](https://github.com/marketredesign/your_project/contributors) who participated in this project.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
