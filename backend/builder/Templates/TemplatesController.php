<?php namespace Builder\Templates;

use Sentry;
use Silex\Application;
use Builder\Filesystem\Images\ImagesSaver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TemplatesController {

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
	 * ImagesSaver instance.
	 * 
	 * @var Builder\Filesystem\Images\ImagesSaver
	 */
	private $imagesSaver;

	/**
	 * Create new ProjectsController instance.
	 * 
	 * @param Application $app    
	 * @param Request     $request
	 */
	public function __construct($app, Request $request, TemplateModel $model, ImagesSaver $imagesSaver)
	{	
		$this->app = $app;
		$this->model = $model;
		$this->request = $request;
		$this->input = $request->request;
		$this->imagesSaver = $imagesSaver;
	}

	/**
	 * Return all available templates.
	 * 
	 * @return Collection
	 */
	public function index()
	{
		return $this->model->with('pages')->orderBy('name', 'desc')->get();
	}

	/**
	 * Save templates thumbnail to filesystem.
	 * 
	 * @param  string/int $id
	 * @return Response
	 */
	public function saveImage($id)
	{
		$path = 'assets/images/thumbnails/templates/template-'.$id.'.png';

		$this->imagesSaver->saveFromString($this->request->getContent(), $path);

		return new Response($this->app['base_url'].'/'.$path, 201);
	}

	/**
	 * Update template with given id.
	 * 
	 * @param  string/int $id
	 * @return Response
	 */
	public function update($id)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('templates.update')) {
			return new Response($this->app['translator']->trans('noPermTemplateUpdate'), 403);
		}

		$template = $this->model->with('pages')->find($id);

		if (Sentry::getUser()->id != $template->user_id) {
			return new Response($this->app['translator']->trans('noPermissionsToModifyTemplate'), 400);
		}
	
		$rand = str_random(10);

		$template->name = $this->input->get('name');		
		$template->color = $this->input->get('color');
		$template->category = $this->input->get('category');
		$template->thumbnail = 'assets/images/thumbnails/templates/template-'.$rand.'.png';

		if ($template->save() && $this->input->has('pages')) {

			foreach ($this->input->get('pages') as $k => $page) {
				$pModel = new \Builder\Projects\PageModel;

				foreach ($page as $name => $value) {
					$pModel->$name = is_array($value) ? json_encode($value) : $value;
				}

				$template->pages()->save($pModel);
			}
		}

		$template->thumbId = $rand;
		return $template;
	}

	/**
	 * Save a new template to database.
	 * 
	 * @return Builder\Themes\ThemesModel
	 */
	public function store()
	{			
		if ( ! $this->app['sentry']->getUser()->hasAccess('templates.create')) {
			return new Response($this->app['translator']->trans('noPermTemplateCreate'), 403);
		}

		if ( ! $this->input->get('name')) {
			return new Response($this->app['translator']->trans('enterNameForTemplate'), 400);
		}

		$exists = $this->model->where('user_id', Sentry::getUser()->id)
							  ->where('name', $this->input->get('name'))
							  ->first();
		
		if ($exists) {
			return new Response($this->app['translator']->trans('templateWithNameExists'), 400);
		}

		$rand = str_random(10);

		$this->model->user_id = Sentry::getUser()->id;
		$this->model->name = $this->input->get('name');		
		$this->model->color = $this->input->get('color');
		$this->model->category = $this->input->get('category');
		$this->model->thumbnail = 'assets/images/thumbnails/templates/template-'.$rand.'.png';

		if ($this->model->save()) {

			foreach ($this->input->get('pages') as $k => $page) {
				$pModel = new \Builder\Projects\PageModel;

				foreach ($page as $name => $value) {
					$pModel->$name = is_array($value) ? json_encode($value) : $value;
				}

				$this->model->pages()->save($pModel);
			}


			$model = $this->model->with('pages')->find($this->model->id);
			$model->thumbId = $rand;
			return $model;
		}
	}

	/**
	 * Delete template with given id from database.
	 * 
	 * @param  string/int $id
	 * @return Response
	 */
	public function delete($id)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('templates.delete')) {
			return new Response($this->app['translator']->trans('noPermTemplateDelete'), 403);
		}

		$template = $this->model->where('id', $id)->first();

		if ( ! $template) {
			return new Response($this->app['translator']->trans('noTemplateWithId'), 400);
		}

		if ($template->type == 'base' || $template->user_id != Sentry::getUser()->id) {
			return new Response($this->app['translator']->trans('noPermissionsToDeleteTemplate'), 403);
		}

		$template->pages()->delete();
		$template->destroy($id);

		return new Response($this->app['translator']->trans('templateDeleteSuccess'), 204);
	}

	/**
	 * Import template from a zip file.
	 * 
	 * @return void
	 */
	public function import()
	{
		foreach ($this->request->files as $key => $file) {
			$finfo = new \finfo(FILEINFO_MIME);

			if ($finfo->file($file) == 'application/zip; charset=binary') {
				$path = $this->app['base_dir'].'/templates/bla/';
				$name = 'temp.'.$file->guessExtension();
				$extractPath = $this->app['base_dir'].'/templates/bla/';

				//save the zip
				$file->move($path, $name);

				//unzip
				$zip = new \ZipArchive();
				$res = $zip->open($path.$name);

				if ($res) {
  					$zip->extractTo($extractPath);
 					$zip->close();
				}			

				$this->sanitizeFolder($extractPath);
			}
		}
	}

	private function sanitizeFolder($directory, $force = false)
	{
		$allowedDirs = array('css', 'img', 'images', 'js', 'javascript', 'fonts');
		$allowedFiles = array('png', 'css', 'jpg', 'js', 'gif', 'html');

		$items = new \FilesystemIterator($directory);
		
		foreach ($items as $item)
		{
			if ($item->isDir() && ($force || ! $this->isAllowed($item->getRealPath(), $allowedDirs)))
			{
				$this->sanitizeFolder($item->getPathname(), true);
			}
			else
			{
				if ($force || ! $this->isAllowed($item->getPathname(), $allowedFiles)) {
					@unlink($item->getPathname());
				}
			}
		}

		@rmdir($directory);

		return true;	
	}

	private function isAllowed($path, $allowed)
	{
		$bootstrapFiles = array('bootstrap.js', 'bootstrap.min.js', 'bootstrap.css', 'bootstrap.min.css');

		if ($path == realpath($this->app['base_dir'].'/templates/bla/')) {
			return true;
		}

		foreach ($bootstrapFiles as $bsf) {
			var_dump($bsf); var_dump($path);
			if (str_contains($path, $bsf)) {
				return false;
			}
		}

		foreach ($allowed as $key => $value) {
			if (str_contains($path, $value)) {
				return true;
			}
		}
		
		return false;
	}
}