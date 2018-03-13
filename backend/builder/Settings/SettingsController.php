<?php namespace Builder\Settings;

use Exception;
use Builder\Settings\SettingModel as Setting;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Capsule\Manager as Capsule;

class SettingsController
{	
	/**
	 * Silex application instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;

	/**
	 * Request input data.
	 * 
	 * @var Input
	 */
	private $input;

	/**
	 * Setting model
	 * 
	 * @var Builder\Settings\SettingModel
	 */
	private $setting;

	public function __construct($app, $request, Setting $setting)
	{
		$this->app = $app;
		$this->setting = $setting;
		$this->input = $request->request;
	}

	public function index()
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('superuser') && ! $this->app['is_demo']) {
			return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
		}

		return $this->setting->all();
	}

	public function update()
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('superuser')) {
			return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
		}

		foreach ($this->input->all() as $name => $value)
		{
			$option = $this->setting->where('name', $name)->first();

			if ($option) {
				$this->setting->where('name', $name)->update(array('value' => $value));
			} else {
				$this->setting->insert(array('name' => $name, 'value' => $value));
			}
		}

		return new Response($this->app['translator']->trans('settingsUpdated'), 201);
	}
}