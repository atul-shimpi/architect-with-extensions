<?php namespace Builder\Users;

use Sentry;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Cartalyst\Sentry\Hashing\BcryptHasher;
use Cartalyst\Sentry\Users\Eloquent\Provider;

class SentryServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        class_alias('Cartalyst\Sentry\Facades\Native\Sentry', 'Sentry');

        $app['sentry'] = $app->share(function ($app) {

        	$provider = new Provider(new BcryptHasher(), 'Builder\Users\UserModel');
        	
            return Sentry::createSentry($provider);
        });
    }

    public function boot(Application $app) {}
}