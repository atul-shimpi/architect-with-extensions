<?php namespace Builder\Libraries;

use Sentry;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LibrariesController {

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
	 * Library model instance.
	 * 
	 * @var Builder\Libraries\LibraryModel
	 */
	private $model;

	/**
	 * Create new ProjectsController instance.
	 * 
	 * @param Application $app    
	 * @param Request     $request
	 */
	public function __construct($app, Request $request, LibraryModel $model)
	{	
		$this->app = $app;
		$this->model = $model;
		$this->request = $request;
		$this->input = $request->request;
	}

	/**
	 * Return all libraries user has access to.
	 * 
	 * @return Collection
	 */
	public function index()
	{
		return $this->model->where('type', 'public')->orWhere('user_id', Sentry::getUser()->id)->get();
	}

	/**
	 * Save a new library to database.
	 * 
	 * @return LibraryModel
	 */
	public function store()
	{
		if ( ! $this->input->get('name') || ! $this->input->get('path')) {
			return new Response($this->app['translator']->trans('fillInAllFields'), 400);
		}

		if ($this->model->where('name', $this->input->get('name'))->first()) {
			return new Response($this->app['translator']->trans('libraryExists'), 400);
		}

		$this->model->fill(array(
			'name' => $this->input->get('name'),
			'path' => $this->input->get('path'),
			'type' => 'private',
			'user_id' => Sentry::getUser()->id,
		))->save();

		return $this->model;
	}

	/**
	 * Update existing library.
	 * 
	 * @return LibraryModel
	 */
	public function update($id)
	{
		if ( ! $this->input->get('name') || ! $this->input->get('path')) {
			return new Response($this->app['translator']->trans('fillInAllFields'), 400);
		}

		$model = $this->model->find($id);

		if ( ! $model || $model->user_id != Sentry::getUser()->id ) {
			return new Response($this->app['translator']->trans('cantEditLibrary'), 403);
		}

		$model->fill(array(
			'name' => $this->input->get('name'),
			'path' => $this->input->get('path'),
		))->save();

		return $model;
	}

	/**
	 * Attach library to given page.
	 * 
	 * @param  int/string $page    page id
	 * @param  string $library     library name
	 * 
	 * @return Response
	 */
	public function attachToPage($page, $library)
	{
		$lib = $this->model->where('name', $library)->first();

		if ($lib) {
			$lib->pages()->attach($page);

			return $lib;
		}

		return new Response($this->app['translator']->trans('cantFindLibrary'), 400);
	}

	/**
	 * Detach library from a given page.
	 * 
	 * @param  int/string $page    page id
	 * @param  string $library     library name
	 * 
	 * @return Response
	 */
	public function detachFromPage($page, $library)
	{
		$lib = $this->model->where('name', $library)->first();

		if ($lib) {
			$lib->pages()->detach($page);

			return $lib;
		}

		return new Response($this->app['translator']->trans('cantFindLibrary'), 400);
	}

	/**
	 * Delete library from database.
	 * 
	 * @param  int|string $id
	 * @return Response
	 */
	public function delete($id)
	{
		$lib = $this->model->find($id);

		if ($lib && $lib->user_id = Sentry::getUser()->id) {
			return $this->model->destroy($id);
		}

		return new Response($this->app['translator']->trans('libraryDeleteFail'), 500);
	}
}