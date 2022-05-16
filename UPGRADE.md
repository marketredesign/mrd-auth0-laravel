# Upgrade Guide
## v2 Migration Guide
In mrd-auth0-laravel v2, the underlying [auth0/login](https://github.com/auth0/laravel-auth0) package has been updated
to v7. With that, the Auth0-PHP SDK has been updated to v8 as well. The main changes are:

* Support for Laravel 9
* Use native login/logout routes from auth0/login package.
* Use native JWT authorization middleware from auth0/login package.

This major release comes with breaking changes. Review this upgrade guide to understand the changes required.

### Summary
* Namespace of underlying auth0/login package was updated from `Auth0\Login` to `Auth0\Laravel`.
* The Auth0-PHP SDK dependency has been updated from V7 to V8, which
[may introduce breaking API changes](https://github.com/auth0/auth0-PHP/blob/main/UPGRADE.md) that will require 
further changes in the application outside the scope of this package.

### Required changes
#### Update configuration schema
- Configuration filename of auth0/login package is now `config/auth0.php` (instead of `config/laravel-auth0.php`).
- Configuration format of auth0/login package has been updated to support Auth0-PHP SDK 8.

1. Delete any previous laravel-auth0 configuration files present in your application.
2. Use `php artisan vendor:publish --tag=auth0-config` to generate an updated config file.
3. Review new configuration instructions in the 
[README](https://github.com/auth0/laravel-auth0/blob/main/README.md#configuration-the-sdk).

#### Use new `auth0.authenticate` middleware (regualar web apps)
The new middleware can be used for regular web apps to verify that users are authenticated (using sessions) and redirect
them to the login page where necessary.

#### Use new `auth0.authorize` middleware instead of `jwt` middleware (APIs)
The previous `jwt` was provided by this package. A native middleware for this is now included in the underlying
auth0/login package. Use that one instead.

#### Update Auth0-PHP dependency from v7 to v8
Only required when the Auth0-PHP is listed as explicit dependency in the `composer.json`. If the application uses
the Auth0-PHP SDK directly, the calls to that SDK probably need to be updated as well. See the upgrade guide
[here](https://github.com/auth0/auth0-PHP/blob/main/UPGRADE.md).

#### Update references to `Request::user()`, where necessary
Previously, the `Request::user()` method would return the user info as provided by the `/userinfo` endpoint within
Auth0. That response contained some additional information that is not included in the user's access token (JWT), like
their name and email address. In this new version, the contents from the access token (JWT) are returned instead.

Thus, applications relying on detailed user information from `Request::user()` should instead make a call to retrieve
user information using the `Users` facade in this package (or the `UserRepository` in this package directly).

#### Update AUTH0_DOMAIN in the `.env`
Applications that make use of the Auth0 Management SDK (e.g. to retrieve user information) should store the tenant
sub-domain from Auth0 in the `AUTH0_DOMAIN` variable, instead of the custom domain. An additional environment variable, 
`AUTH0_CUSTOM_DOMAIN`, can be used to configure the custom Auth0 domain 
(which was previously used in the `AUTH0_DOMAIN` variable instead).

So, the domain that was previously configured in `AUTH0_DOMAIN` should now be stored in the `AUTH0_CUSTOM_DOMAIN`
environment variable. Subsequently, the Auth0 tenant domain (found in Auth0's Application settings page, has the form
`[tenant].auth0.com`) should now be stored in the `AUTH0_DOMAIN` environment variable.
