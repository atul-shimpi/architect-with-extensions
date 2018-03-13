<?php namespace Builder\Install;

use Silex\Application;
use DirectoryIterator;
use Builder\Settings\SettingModel;
use Builder\Projects\Services\ProjectCreator;
use Illuminate\Database\Capsule\Manager as DB;
use Builder\Templates\TemplateModel as Template;

class Seeder {

	/**
	 * Silex application instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;

	/**
     * Project creator instance.
     * 
     * @var Builder\Projects\Services\ProjectCreator
     */
    private $projectCreator;

	public function __construct($app, ProjectCreator $projectCreator)
	{
		$this->app = $app;
		$this->projectCreator = $projectCreator;
	}

	public function seed()
	{
		$this->seedLibraries();
		$this->seedTemplates();
		$this->seedThemes();
        $this->seedSettings();
	}

    public function seedSettings()
    {
    	try {
    		SettingModel::insert(array(
    			array('name' => 'enable_registration', 'value' => 1),
    			array('name' => 'update_version', 'value' => isset($this->app['version']) ? $this->app['version'] : '1.7'),
    			array('name' => 'permissions', 'value' => '{"templates.delete":1,"templates.update":1,"templates.create":1,"users.delete":0,"users.update":0,"export":1,"publish":1,"projects.create":1,"projects.update":1,"projects.delete":1,"themes.create":1,"themes.update":1,"themes.delete":1,"users.create":0}')
    		));
    	} catch(\Illuminate\Database\QueryException $e) {
            //catch only duplicate value error
    		if ($e->errorInfo[1] !== 1062) {
    			throw $e;
    		}
    	}
    }

	public function seedDemoProjects()
	{
		$templates = Template::whereIn('name', [
			'Wonder',
			'Freelancer',
			'Product Launch',
			'Stanley',
			'Pratt',
			'Grayscale',
			'Flatty',
			'Bolt'
		])->get();

		foreach ($templates as $k => $template) {
			$name = 'Demo-'.($k+1);

			$this->projectCreator->create(array(
				'name' => $name, 'template' => $template->id, 'public' => 1
			));
		}
	}

	public function seedLibraries()
	{
        $libraries = array(
            array('name' => 'Google Maps', 'path' => 'https://maps.googleapis.com/maps/api/js'),
            array('name' => 'Jquery Easing', 'path' => 'assets/js/vendor/jquery-easing.js'),
            array('name' => 'Mustache', 'path' => 'http://cdnjs.cloudflare.com/ajax/libs/mustache.js/0.7.2/mustache.min.js'),
            array('name' => 'Github Activity', 'path' => 'http://caseyscarborough.github.io/github-activity/github-activity-0.1.0.min.js'),
            array('name' => 'Jquery Rss', 'path' => 'assets/js/vendor/jquery-rss.min.js'),
            array('name' => 'Classie', 'path' => 'assets/js/vendor/classy.js'),
            array('name' => 'Bootstrap Validation', 'path' => 'assets/js/vendor/bootstrap-validation.js'),
            array('name' => 'WoW', 'path' => 'assets/js/vendor/wow.js'),
            array('name' => 'Scroll To', 'path' => 'assets/js/vendor/scroll-to.js'),
            array('name' => 'Animated Header', 'path' => 'assets/js/vendor/animated-header.js'),
            array('name' => 'Chart', 'path' => 'assets/js/vendor/chart.js'),
        );

        foreach($libraries as $library) {
            $exists = DB::table('libraries')->where('name', $library['name'])->first();

            if ( ! $exists) {
                DB::table('libraries')->insert($library);
            }
        }
	}

	public function seedThemes()
	{
		$dir = new DirectoryIterator($this->app['base_dir'].'/themes');

		foreach ($dir as $item) {
			if ($item->isDir() && ! $item->isDot()) {

				$name = $this->nameFromPath($item->getRealPath());

				//check if theme doesn't already exist in db
				$theme = DB::table('themes')->where('name', $name)->first();

				if ( ! $theme) {

					DB::table('themes')->insert(array(
						'name' => $name,
						'path' => "themes/$name/stylesheet.css",
						'type' => 'public',
						'source' => $name == 'default' ? 'Bootstrap' : 'Bootswatch',
					));
				}
			}
		}
	}

	public function seedTemplates()
	{
		$css = ''; $js = '';

		$dir = new DirectoryIterator($this->app['base_dir'].'/templates');

		foreach ($dir as $item) {
			if ($item->isDir() && ! $item->isDot()) {

				$name = $this->nameFromPath($item->getRealPath());

				//check if template doesn't already exist in db
				$template = DB::table('templates')->where('name', $name)->first();

				if ( ! $template) {

					//if no config file exists for template continue
					if ( ! file_exists($item->getRealPath().'/config.php')) continue;

					$config = require $item->getRealPath().'/config.php';

					//create a new template in db
					DB::table('templates')->insert(array(
						'name' => $config['name'],
						'color' => $config['color'],
						'category' => $config['category'],
						'thumbnail' => 'templates/'.strtolower(str_replace(' ', '-', $config['name'])).'/thumbnail.png'
					));

					$id = DB::getPdo()->lastInsertId();

					//get css
					if (file_exists($item->getRealPath().'/css/styles.css')) {
						$css = file_get_contents($item->getRealPath().'/css/styles.css');
					}

					//get js
					if (file_exists($item->getRealPath().'/js/scripts.js')) {
						$js = file_get_contents($item->getRealPath().'/js/scripts.js');
					}

					//loop trough all .html files and create a page in db for each one
					$items = new \DirectoryIterator($item->getRealPath());

					foreach ($items as $file) {
						if (pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'html') {
							DB::table('pages')->insert(array(
								'name' => str_replace('.'.pathinfo($file->getFilename(), PATHINFO_EXTENSION), '', $file->getFileName()),
								'html' => file_get_contents($file->getRealPath()),
								'css'  => $css,
								'js'   => $js,
								'libraries' => isset($config['libraries']) ? json_encode($config['libraries']) : '',
								'theme' => isset($config['theme']) ? $config['theme'] : 'default',
								'pageable_id' => $id,
								'pageable_type' => 'Template',
							));
						}
					}
				}
			}
		}
	}

	/**
	 * Get resource name from given filesystem path.
	 * 
	 * @param  string $path
	 * @return string
	 */
	public function nameFromPath($path)
	{
		$array = explode(DIRECTORY_SEPARATOR, $path);
		$name  = str_replace('-', ' ', end($array));

		return $name;
	}

}