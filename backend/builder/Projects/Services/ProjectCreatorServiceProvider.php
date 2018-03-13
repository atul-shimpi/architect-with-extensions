<?php namespace Builder\Projects\Services;

use Sentry;
use Silex\Application;
use Silex\ServiceProviderInterface;

class ProjectCreatorServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['projects.creator'] = $app->share(function ($app) {

        	$project = new \Builder\Projects\ProjectModel;
        	$page = new \Builder\Projects\PageModel;
        	$fs = new \Symfony\Component\Filesystem\Filesystem;

            return new ProjectCreator($app, $fs, $project, $page, $app['sentry']);
        });
    }

    public function boot(Application $app) {}
}