<?php namespace Builder\Filesystem\Images;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class ImagesSaverServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
       	$app['imagesSaver'] = $app->share(function ($app) {
            return new ImagesSaver(new \Intervention\Image\ImageManager(array('driver' => 'gd')));
        });
    }

    public function boot(Application $app){}
}