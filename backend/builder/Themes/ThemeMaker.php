<?php namespace Builder\Themes;

use Sentry;

class ThemeMaker {

	/**
	 * Less parser/compiler instance.
	 * 
	 * @var Builder\Themes\Less
	 */
	private $less;

	/**
	 * Theme model instance.
	 * 
	 * @var Builder\Themes\ThemesModel
	 */
	private $model;

	/**
	 * Filesystem instance.
	 * 
	 * @var Symfony\Component\Filesystem
	 */
	private $fs;

	/**
	 * Create new theme maker instance.
	 * 
	 * @param Less       $less 
	 * @param ThemeModel $model
	 */
	public function __construct(Less $less, ThemeModel $model, $fs, $app)
	{
		$this->fs = $fs;
		$this->app = $app;
		$this->less = $less;
		$this->model = $model;
	}

	/**
	 * Create a new theme.
	 * 
	 * @param  array $data 
	 * 
	 * @return ThemeModel
	 */
	public function create(array $data)
	{
		$css = $this->less->parse('bootstrap', $data['vars'], $data['custom']);
		
		$path = $this->saveStylesheet($data['name'], $css);

		//save theme to database	
		$this->model->path = $path;
		$this->model->name = $data['name'];
		$this->model->type = $data['type'];
		$this->model->custom_less = $data['custom'];
		$this->model->user_id = Sentry::getUser()->id;
		
		if ( ! empty($vars)) {
			$this->model->modified_vars = json_encode($vars);
		}

		$this->model->save();

		return $this->model;
	}

	/**
	 * Update already existing theme.
	 * 
	 * @param  ThemeModel $theme
	 * @param  array      $data
	 * 
	 * @return ThemeModel
	 */
	public function update(ThemeModel $theme, array $data)
	{
		if ( ! empty($data)) {
			$old = json_decode($theme->modified_vars, true);

			//merge newly changed vars and the ones saved in database
			$m = array_merge(is_array($old) ? $old : array(), $data['vars']);
			$css = $this->less->parse('bootstrap', $m, $data['custom']);

			if ($theme->name != $data['name']) {
				$this->fs->remove('themes/'.$theme->name);
			}
	
			$theme->modified_vars = json_encode($m);
			$theme->name = $data['name'];
			$theme->type = $data['type'];
			$theme->path = $this->saveStylesheet($theme->name, $css);
			$theme->custom_less = $data['custom'];
			$theme->save();	
		}

		return $theme;
	}

	/**
	 * Return default bootstrap less variables.
	 * 
	 * @return json
	 */
	public function getDefaultVars()
	{
		return json_encode($this->less->compileBootstrapLessToArray());
	}

	/**
	 * Save css stylesheet on the disk.
	 * 
	 * @param  string $name
	 * @param  string $css
	 * 
	 * @return string
	 */
	private function saveStylesheet($name, $css)
	{
		$path = 'themes/'.$name.'/';

		//create theme directory if it doesn't exist
		if ( ! is_dir($path)) mkdir($path);

		//save compiled stylesheet
		file_put_contents($path.'stylesheet.css', $css);

		return $path.'stylesheet.css';
	}
}