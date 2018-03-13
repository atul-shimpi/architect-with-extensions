<?php namespace Builder\Filesystem;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class FileSystemServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
       	$app['filesystem'] = $app->share(function ($app) {
            return new Filesystem();
        });

        $app['finder'] = $app->share(function ($app) {
            return new Finder();
        });
    }

    public function boot(Application $app){}
}