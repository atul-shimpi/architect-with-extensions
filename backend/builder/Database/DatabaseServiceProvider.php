<?php namespace Builder\Database;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Illuminate\Database\Capsule\Manager;

class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {

        if (file_exists($app['base_dir'].'/backend/.env')) {
            $app['illuminate.db.default_options'] = array(
                'driver'    => 'mysql',
                'host'      => getenv('DB_HOST'),
                'database'  => getenv('DB_DATABASE'),
                'username'  => getenv('DB_USERNAME'),
                'password'  => getenv('DB_PASSWORD'),
                'charset'   => 'utf8',
                'prefix'    => getenv('DB_PREFIX'),
                'collation' => 'utf8_unicode_ci',
            );
        } else {
            $app['illuminate.db.default_options'] = require $app['base_dir'].'/backend/config/database.php';
        }

        $app['illuminate.db'] = $app->share(function ($app) {
            return $app['illuminate.capsule']->getConnection($app['illuminate.db.default_connection']);
        });

        $app['illuminate.db.options.initializer'] = $app->protect(function () use ($app) {
            if (!isset($app['illuminate.db.options'])) {
                $app['illuminate.db.options'] = array(
                    'default' => array()
                );
            }

            $tmp = $app['illuminate.db.options'];
            foreach ($tmp as $name => &$options) {
                $options = array_replace($app['illuminate.db.default_options'], $options);

                if (!isset($app['illuminate.db.default_connection'])) {
                    $app['illuminate.db.default_connection'] = $name;
                }
            }
            $app['illuminate.db.options'] = $tmp;
        });

        $app['illuminate.capsule'] = $app->share(function ($app) {
            $app['illuminate.db.options.initializer']();

            $capsule = new Manager();

            foreach ($app['illuminate.db.options'] as $name => $options) {
                $capsule->addConnection($options, $name);
            }

            return $capsule;
        });
    }

    public function boot(Application $app)
    {
        $app['illuminate.capsule']->bootEloquent();
        $app['illuminate.capsule']->setAsGlobal();
    }
}