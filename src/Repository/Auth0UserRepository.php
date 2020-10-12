<?php


namespace Marketredesign\MrdAuth0Laravel\Repository;

use Auth0\Login\Auth0JWTUser;
use Auth0\Login\Auth0User;
use Auth0\Login\Repository\Auth0UserRepository as BaseRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

class Auth0UserRepository extends BaseRepository
{
    /**
     * The Auth0 user model.
     *
     * @var string
     */
    protected $model;

    /**
     * The Auth0 JWT model.
     *
     * @var string
     */
    protected $jwtModel;

    /**
     * Create a new Auth0 user repository.
     * @param string $model The Auth0 user model, must be a subclass of {{@code \Auth0\Login\Auth0User}}.
     */
    public function __construct(string $model, string $jwtModel)
    {
        $this->model = $this->getFullModelClass($model);
        $this->jwtModel = $this->getFullModelClass($jwtModel);

        if (!is_a($this->model, Auth0User::class, true)) {
            throw new InvalidArgumentException(
                'Given user model (' . $model . ') should be a subclass of ' . Auth0User::class,
            );
        }

        if (!is_a($this->jwtModel, Auth0JWTUser::class, true)) {
            throw new InvalidArgumentException(
                'Given JWT model (' . $jwtModel . ') should be a subclass of ' . Auth0JWTUser::class,
            );
        }
    }

    /**
     * @return string Full classifier of {@code $this->model}}.
     */
    protected function getFullModelClass($model): string
    {
        return '\\'.ltrim($model, '\\');
    }

    /**
     * @inheritDoc
     */
    public function getUserByDecodedJWT(array $decodedJwt): Authenticatable
    {
        return new $this->jwtModel($decodedJwt);
    }

    /**
     * @inheritDoc
     */
    public function getUserByUserInfo(array $userInfo): Authenticatable
    {
        return new $this->model($userInfo['profile'], $userInfo['accessToken']);
    }

    /**
     * @return string The Auth0 user model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return string The Auth0 JWT model.
     */
    public function getJwtModel(): string
    {
        return $this->jwtModel;
    }
}
