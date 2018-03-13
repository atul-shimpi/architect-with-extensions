<?php namespace Builder\Install;

use Exception;
use Builder\Settings\SettingModel;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Capsule\Manager as Capsule;

class UpdateController
{	
	/**
	 * Silex application instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;

	public function __construct($app)
	{
		$this->app = $app;
	}

	public function index()
	{
		if (Capsule::schema()->hasTable('settings')) {
			$updateVersion = SettingModel::where('name', 'update_version')->first();

			if ($updateVersion && $updateVersion->value == $this->app['version']) {
				return $this->app->redirect('/');
			}
		}

		return $this->app['twig']->render('install/update.html');
	}

	public function runUpdate()
	{
		if (Capsule::schema()->hasTable('settings')) {
			$updateVersion = SettingModel::where('name', 'update_version')->first();

			if ($updateVersion && $updateVersion->value == $this->app['version']) {
				return $this->app->redirect('/');
			}
		}

        $s = new Seeder($this->app, $this->app['projects.creator']);
        $s->seedTemplates();
        $s->seedLibraries();

        if (SettingModel::where('name', 'update_version')->first()) {
            SettingModel::where('name', 'update_version')->update(array('value' => $this->app['version']));
        } else {
            SettingModel::insert(array('name' => 'update_version', 'value' => $this->app['version']));
        }

		return $this->app->redirect('/');
	}
}