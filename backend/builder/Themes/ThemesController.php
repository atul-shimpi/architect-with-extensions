<?php namespace Builder\Themes;

use Sentry;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ThemesController {

	/**
	 * Application instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;

	/**
	 * Request instance.
	 * 
	 * @var Symfony\Component\HttpFoundation\Request
	 */
	private $request;

	/**
	 * Paramater bag instance.
	 * 
	 * @var Symfony\Component\HttpFoundation\ParameterBag
	 */
	private $input;

	/**
	 * Theme model instance.
	 * 
	 * @var Builder\Themes\ThemesModel
	 */
	private $model;

	/**
	 * Theme make instance.
	 * 
	 * @var Builder\Themes\ThemeMaker
	 */
	private $theme;

	/**
	 * Create new ProjectsController instance.
	 * 
	 * @param Application $app    
	 * @param Request     $request
	 */
	public function __construct($app, Request $request, ThemeModel $model, ThemeMaker $theme)
	{	
		$this->app = $app;
		$this->theme = $theme;
		$this->model = $model;
		$this->request = $request;
		$this->input = $request->request;
	}

	/**
	 * Return all themes current user has access to.
	 * 
	 * @return Collection
	 */
	public function index()
	{
		return $this->model->where('type', 'public')->orWhere('user_id', Sentry::getUser()->id)->orderBy('name', 'asc')->get();
	}

	/**
	 * Return compiled bootstrap less variables as json.
	 * 
	 * @return Response
	 */
	public function bootstrapVars()
	{
		return new Response($this->theme->getDefaultVars(), 200);
	}

	public function saveImage()
	{
		$path = $this->app['base_dir'].'/themes/'.$this->input->get('theme').'/image.png';

		return $this->app['imagesSaver']->saveFromString($this->input->get('image'), $path, false, 449, 269);
	}

	/**
	 * Save a new theme.
	 * 
	 * @return void
	 */
	public function store()
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('themes.create')) {
			return new Response($this->app['translator']->trans('noPermThemeCreate'), 403);
		}

		$name = $this->input->get('name');
		$data = $this->input->get('theme');
		$vars = $this->input->get('vars', array());

		//make sure we got a name passed in
		if ( ! $name) return new Response($this->app['translator']->trans('enterNameForTheme'), 400);

		$byName = $this->model->where('name', $name)->first();

		//if we have an id it means we're gonna need to edit an existing theme
		if (isset($data['id']))
		{
			$byId = $this->model->find($data['id']);

			if ($byName && $byName->name != $byId->name) {
				return new Response($this->app['translator']->trans('themeWithNameExists'), 400);
			}
			
			if ($byId && Sentry::getUser()->id == $byId->user_id) {
				return new Response($this->theme->update($byId, $this->input->all()));
			}
		}

		//otherwise we'll need to check if we're creating a new one or editing
		//an existing one by fetching a theme by name and comparing user ids
		else
		{
			//update if theme is created by currently logged in user or return an error
			if ($byName && Sentry::getUser()->id == $byName->user_id) {
				return new Response($this->theme->update($byName, $this->input->all()));
			} elseif ($byName) {
				return new Response($this->app['translator']->trans('themeWithNameExists'), 400);
			}
		}
		
		//if we didn't return by this point we'll just create a new theme with given data
		try {
			$this->theme->create($this->input->all());
		} catch (\Less_Exception_Compiler $e) {
			return new Response($this->app['translator']->trans('errorInTheme'), 400);
		}
	
		return new Response($this->model, 201);
	}

	public function delete($id)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('themes.delete')) {
			return new Response($this->app['translator']->trans('noPermThemeDelete'), 403);
		}

		return new Response($this->model->destroy($id), 204);
	}
}