<?php

namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Facile\JoseVerifier\AbstractTokenVerifier;
use Facile\JoseVerifier\TokenVerifierInterface;
use Firebase\JWT\BeforeValidException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\AssertableJsonString;
use Jose\Component\Checker\InvalidClaimException;
use Marketredesign\MrdAuth0Laravel\Tests\OidcTestingValues;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class JwtVerifierTest extends TestCase
{
    use OidcTestingValues;

    /** @var string The OpenID Connect Audience that should be considered valid by the JWT verifier. */
    private const AUDIENCE = 'https://some_audience.test/api';

    protected AbstractTokenVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pricecypher-oidc.audience', self::AUDIENCE);

        $this->verifier = App::make(TokenVerifierInterface::class);
    }

    /**
     * Verify that the given "actual" decoded JWT is as "expected".
     * The `iss` claim (matching {@code $this->oidcIssuer}), and `sub` claim are always expected to be included.
     */
    protected function checkDecoded(array $expected, array $actual): AssertableJsonString
    {
        self::assertIsArray($actual);
        self::assertFalse(array_is_list($actual));

        return (new AssertableJsonString($actual))->assertFragment([
            'iss' => $this->oidcIssuer,
            'sub' => 'test_user',
            ...$expected,
        ]);
    }

    /**
     * Verifies that a valid JWT can be decoded into an associative array as expected.
     */
    public function testValidToken()
    {
        $valid = $this->encJwt('some_user_sub', ['extra' => 'test']);

        $dec = $this->verifier->verify($valid);

        $this->checkDecoded(['sub' => 'some_user_sub', 'extra' => 'test'], $dec);
    }

    /**
     * Verifies that a token without 'aud' claim can be valid.
     */
    public function testNoAudience()
    {
        $valid = $this->encJwt();
        $dec = $this->verifier->verify($valid);

        $this->checkDecoded([], $dec)->assertMissingPath('aud');
    }

    /**
     * Verifies that a token with an 'aud' claim equal to {@code null} is not valid.
     */
    public function testNullAudience()
    {
        $valid = $this->encJwt('some_user_sub', ['aud' => null]);

        $this->expectException(InvalidClaimException::class);
        $this->expectExceptionMessage('audience');

        $this->verifier->verify($valid);
    }

    /**
     * Verifies that a JWT with invalid 'aud' claim does not pass verification.
     */
    public function testInvalidAudience()
    {
        $invalid = $this->encJwt('invalid_aud', ['aud' => 'https://wrong-audience.test/pricecypher']);

        $this->expectException(InvalidClaimException::class);
        $this->expectExceptionMessage('audience');

        $this->verifier->verify($invalid);

        self::fail('Verify should not return since an exception is expected.');
    }

    /**
     * Verifies that a JWT with a valid 'aud' claim can be considered valid.
     */
    public function testValidAudience()
    {
        $invalid = $this->encJwt('valid_aud', ['aud' => self::AUDIENCE]);

        $dec = $this->verifier->verify($invalid);

        $this->checkDecoded(['sub' => 'valid_aud', 'aud' => self::AUDIENCE], $dec);
    }

    /**
     * Verifies that a JWT with invalid 'iss' claim does not pass validation.
     */
    public function testInvalidIssuer()
    {
        $invalid = $this->encJwt('invalid_iss', ['iss' => 'https://some_other_issuer.test/invalid']);

        $this->expectException(InvalidClaimException::class);
        $this->expectExceptionMessage('issuer');

        $this->verifier->verify($invalid);

        self::fail('Verify should not return since an exception is expected.');
    }

    /**
     * Verifies that a token is used before it was issued (according to the 'iat' claim), does not pass validation.
     */
    public function testInvalidIssuedAt()
    {
        $invalid = $this->encJwt('invalid_iss', ['iat' => now()->timestamp + 1]);

        $this->expectException(BeforeValidException::class);
        $this->expectExceptionMessage('iat');

        $this->verifier->verify($invalid);

        self::fail('Verify should not return since an exception is expected.');
    }

    /**
     * Verifies that a token is only considered valid up until it is expired (according to the `exp` claim).
     */
    public function testInvalidExpirationTime()
    {
        $valid = $this->encJwt('invalid_iss', ['exp' => now()->timestamp + 100]);

        $this->travel(99)->seconds();

        self::assertNotEmpty($this->verifier->verify($valid));

        $this->travel(2)->seconds();

        $this->expectException(InvalidClaimException::class);
        $this->expectExceptionMessage('token expired');

        $this->verifier->verify($valid);

        self::fail('Verify should not return since an exception is expected.');
    }

    /**
     * Verifies that a JWT used before the 'nbf' (not-before) time does not pass validation.
     */
    public function testInvalidNotBefore()
    {
        $invalid = $this->encJwt('invalid_iss', ['nbf' => now()->timestamp + 100]);

        $this->travel(50)->seconds();

        $this->expectException(BeforeValidException::class);
        $this->expectExceptionMessage('nbf');

        $this->verifier->verify($invalid);

        self::fail('Verify should not return since an exception is expected.');
    }

    /**
     * Verifies that a JWT with mismatching 'azp' claim does not pass validation.
     */
    public function testAzp()
    {
        $verifier = $this->verifier->withAzp('some_required_client_id');
        $valid = $this->encJwt('valid_azp', ['azp' => 'some_required_client_id']);

        $dec = $verifier->verify($valid);
        $this->checkDecoded(['sub' => 'valid_azp', 'azp' => 'some_required_client_id'], $dec);

        $invalid = $this->encJwt('invalid_azp', ['azp' => 'different_client_id']);

        $this->expectException(InvalidClaimException::class);
        $this->expectExceptionMessage('azp');
        $verifier->verify($invalid);
        self::fail('Verify should not return since an exception is expected.');
    }

    /**
     * Verifies that a JWT with mismatching 'nonce' claim does not pass validation.
     */
    public function testNonce()
    {
        $verifier = $this->verifier->withNonce('first_use');
        $valid = $this->encJwt('valid_nonce', ['nonce' => 'first_use']);

        $dec = $verifier->verify($valid);
        $this->checkDecoded(['sub' => 'valid_nonce', 'nonce' => 'first_use'], $dec);

        $invalid = $this->encJwt('invalid_nonce', ['nonce' => 'second_use']);

        $this->expectException(InvalidClaimException::class);
        $this->expectExceptionMessage('Nonce');
        $verifier->verify($invalid);
        self::fail('Verify should not return since an exception is expected.');
    }

    /**
     * Verifies that a maximum age of access tokens (compared to their 'auth_time' claim) can be enforced.
     */
    public function testMaxAge()
    {
        $verifier = $this->verifier->withMaxAge(100);
        $token = $this->encJwt('valid_auth_time', ['auth_time' => now()->timestamp]);

        $dec = $verifier->verify($token);
        $this->checkDecoded(['sub' => 'valid_auth_time'], $dec);

        $this->travel(105)->seconds();

        $this->expectException(InvalidClaimException::class);
        $this->expectExceptionMessage('Too much time');
        $this->expectExceptionMessage('authentication');
        $verifier->verify($token);
        self::fail('Verify should not return since an exception is expected.');
    }

    /**
     * Verifies that a JWT with non-integer value for the 'auth_time' claim does not pass validation.
     */
    public function testMaxAgeType()
    {
        $verifier = $this->verifier->withMaxAge(100);
        $invalidType = $this->encJwt('invalid_auth_time_type', ['auth_time' => 'string']);

        $this->expectException(InvalidClaimException::class);
        $this->expectExceptionMessage('auth_time');
        $this->expectExceptionMessage('integer');
        $verifier->verify($invalidType);
        self::fail('Verify should not return since an exception is expected.');
    }
}
