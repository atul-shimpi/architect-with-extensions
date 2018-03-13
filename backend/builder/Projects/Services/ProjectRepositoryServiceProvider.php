<?php namespace Builder\Projects\Services;

use Silex\Application;
use Builder\Projects\PageModel;
use Builder\Projects\ProjectModel;
use Silex\ServiceProviderInterface;

class ProjectRepositoryServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['projects.repository'] = $app->share(function ($app) {
            return new ProjectRepository($app['sentry'], new ProjectModel, new PageModel, $app);
        });
    }

    public function boot(Application $app) {}
}