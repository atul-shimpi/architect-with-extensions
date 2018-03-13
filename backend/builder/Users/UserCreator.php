<?php namespace Builder\Users;


class UserCreator {

    private $app;
    private $sentry;

    /**
     * Create new UserCreator instance.
     *
     * @param $app
     */
    public function __construct($app) {
        $this->app = $app;
        $this->sentry = $app['sentry'];
    }

    /**
     * Create a new user from given credentials.
     *
     * @param array $credentials
     * @return UserModel
     */
    public function create($credentials) {
        $current     = $this->sentry->getUser();
        $permissions = isset($this->app['settings']['permissions']) ? json_decode($this->app['settings']['permissions'], true) : array();

        $user = $this->sentry->register([
            'email'       => $credentials['email'],
            'password'    => $credentials['password'],
            'permissions' => $permissions,
        ], true);

        //log new user in, if we don't have a user logged in already
        if ( ! $current) {
            $user = $this->sentry->authenticate([
                'email'    => $credentials['email'],
                'password' => $credentials['password'],
            ], true);
        }

        return $user;
    }
}