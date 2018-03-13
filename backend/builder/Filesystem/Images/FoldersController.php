<?php namespace Builder\Filesystem\Images;

use Sentry;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FoldersController {

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
	 * Application instance
	 * 
	 * @var Silex\Application
	 */
	private $app;

	/**
	 * Folder model instance.
	 * 
	 * @var Builder\Filesystem\Images\FolderModel
	 */
	private $model;

	public function __construct(Application $app, Request $request, FolderModel $model)
	{
		$this->app = $app;
		$this->model = $model;
		$this->request = $request;
		$this->input = $request->request;
	}

	/**
	 * Return all folders user has access to.
	 * 
	 * @return array
	 */
	public function index()
	{
		return $this->model->where('user_id', Sentry::getUser()->id)->get();
	}

	/**
	 * Create a new folder.
	 * 
	 * @return Response
	 */
	public function store()
	{
		$folder = $this->model->create(array(
			'user_id' => Sentry::getUser()->id,
			'name' => $this->input->get('name')
		));
	
		return new Response($folder, 201);
	}

	/**
	 * Delete a folder with given id.
	 * 
	 * @param  int/string $id
	 * @return Response
	 */
	public function delete($id)
	{
		return $this->model->destroy($id);
	}

}