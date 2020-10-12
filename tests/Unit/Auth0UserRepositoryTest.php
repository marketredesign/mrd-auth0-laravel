<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Unit;

use Auth0\Login\Auth0JWTUser;
use Auth0\Login\Auth0User;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Marketredesign\MrdAuth0Laravel\Repository\Auth0UserRepository;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;
use Mockery;

class Auth0UserRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mockery::getConfiguration()->disableReflectionCache();
    }


    /**
     * Verifies that our implementation of the User Repository is bound in the service container.
     */
    public function testServiceBinding()
    {
        // Make repository using service container.
        $repository = App::make(\Auth0\Login\Contract\Auth0UserRepository::class);

        // Verify it is indeed our instance.
        $this->assertInstanceOf(\Marketredesign\MrdAuth0Laravel\Repository\Auth0UserRepository::class, $repository);
    }

    /**
     * Verifies that the user model config value is used when creating a new instance.
     */
    public function testUserModelFromConfig()
    {
        foreach (['TestJwt', '\TestJwt', 'App\JwtFoo', '\App\JwtBar'] as $fakeClass) {
            // Create new class, extending Auth0User.
            $mock = Mockery::namedMock($fakeClass, Auth0User::class);

            // Try both the class name itself as the class definition.
            foreach ([$fakeClass, get_class($mock)] as $value) {
                // Set config value to fake class.
                Config::set('mrd-auth0.model', $value);

                // Get repository from service container.
                $repository = App::make(\Auth0\Login\Contract\Auth0UserRepository::class);

                // Verify that the repository indeed has the correct model.
                self::assertStringEndsWith($fakeClass, $repository->getModel());
                self::assertStringStartsWith('\\', $repository->getModel());
            }

            Mockery::close();
        }
    }

    /**
     * Verifies that the JWT model config value is used when creating a new instance.
     */
    public function testJwtModelFromConfig()
    {
        foreach (['Test', '\Test', 'App\Foo', '\App\Bar'] as $fakeClass) {
            // Create new class, extending Auth0User.
            $mock = Mockery::namedMock($fakeClass, Auth0JWTUser::class);

            // Try both the class name itself as the class definition.
            foreach ([$fakeClass, get_class($mock)] as $value) {
                // Set config value to fake class.
                Config::set('mrd-auth0.jwt-model', $value);

                // Get repository from service container.
                $repository = App::make(\Auth0\Login\Contract\Auth0UserRepository::class);

                // Verify that the repository indeed has the correct model.
                self::assertStringEndsWith($fakeClass, $repository->getJwtModel());
                self::assertStringStartsWith('\\', $repository->getJwtModel());
            }

            Mockery::close();
        }
    }

    /**
     * Verify that the getUserByUserInfo returns the correct instance.
     */
    public function testGetUserByUserInfo()
    {
        $repository = new Auth0UserRepository(Auth0User::class, Auth0JWTUser::class);

        $user = $repository->getUserByUserInfo(['profile' => [], 'accessToken' => []]);

        self::assertInstanceOf(Auth0User::class, $user);
    }

    /**
     * Verify that the getUserByDecodedJWT returns the correct instance.
     */
    public function testGetUserByDecodedJWT()
    {
        $repository = new Auth0UserRepository(Auth0User::class, Auth0JWTUser::class);

        $user = $repository->getUserByDecodedJWT([]);

        self::assertInstanceOf(Auth0JWTUser::class, $user);
    }

    /**
     * Verify an InvalidArgumentException is thrown when the given user class does not extend Auth0User.
     */
    public function testUserClassDoesNotExtendAuth0()
    {
        $fakeClass = 'Something';
        Mockery::namedMock($fakeClass);

        try {
            new Auth0UserRepository($fakeClass, Auth0JWTUser::class);
            self::fail('Expected an exception to be thrown.');
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidArgumentException::class, $e);
            self::assertEquals(
                'Given user model (Something) should be a subclass of ' . Auth0User::class,
                $e->getMessage(),
            );
        }
    }

    /**
     * Verify an InvalidArgumentException is thrown when the given JWT class does not extend Auth0JWTUser.
     */
    public function testJwtClassDoesNotExtendAuth0()
    {
        $fakeClass = 'JwtSomething';
        Mockery::namedMock($fakeClass);

        try {
            new Auth0UserRepository(Auth0User::class, $fakeClass);
            self::fail('Expected an exception to be thrown.');
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidArgumentException::class, $e);
            self::assertEquals(
                'Given JWT model (JwtSomething) should be a subclass of ' . Auth0JwtUser::class,
                $e->getMessage(),
            );
        }
    }
}
