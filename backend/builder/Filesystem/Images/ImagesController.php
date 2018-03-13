<?php namespace Builder\Filesystem\Images;

use Sentry;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ImagesController {

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
	 * Image model instance.
	 * 
	 * @var Builder\Filesystem\Images\ImagesModel
	 */
	private $model;

	private $fs;

	private $validExtensions = ['jpeg', 'png', 'gif', 'bmp', 'svg'];

	public function __construct(Application $app, Request $request, ImageModel $model, $filesystem)
	{
		$this->app = $app;
		$this->model = $model;
		$this->fs = $filesystem;
		$this->request = $request;
		$this->input = $request->request;
	}

	/**
	 * Return all images user has access to.
	 * 
	 * @return Array
	 */
	public function index()
	{
		return $this->model->where('user_id', Sentry::getUser()->id)->with('folders')->get();
	}

	/**
	 * Save image to filesystem and reference to db.
	 * 
	 * @return Response
	 */
	public function store()
	{
		//replace old image modified by aviary api
		if ($this->input->get('url'))
		{
			$dir = $this->app['base_dir'].'/assets/images/uploads/';

			$contents = file_get_contents($this->input->get('url'));

			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$extension = explode('/', $finfo->buffer($contents))[1];

			if ($this->input->get('oldUrl')) {
				$arr  =  explode('/', $this->input->get('oldUrl'));
				$path = $dir.end($arr);
			} else {
				$path = $dir.str_random(10).'.'.$extension;
			}

			file_put_contents($path, $contents);
			
			$file = new \Symfony\Component\HttpFoundation\File\File($path);

			if ( ! in_array($file->guessExtension(), $this->validExtensions)) {
				unlink($path);
				return new Response('', 403);
			}

			return str_replace($dir, $this->app['base_url'].'/assets/images/uploads/', $path);
		} 

		$imgs = array();
		$userId = Sentry::getUser()->id;

		foreach($this->request->files as $file) {
			if ( ! in_array($file->guessExtension(), $this->validExtensions)) continue;

			//compile names
			$name = pathinfo($file->getClientOriginalName());
			$rand = str_random(15).'.'.$file->guessExtension();

			//if display name already exists add a random number to it
			if ($this->model->where('user_id', $userId)->where('display_name', $name['basename'])->first())
			{
				$name['basename'] = str_replace('.', rand(100, 999).'.', $name['basename']);
			}
			
			//save the image
			$file->move($this->app['base_dir'].'/assets/images/uploads', $rand);
			
			//save reference to image in db
			$img = $this->model->create(array(
				'file_name' => $rand,
				'user_id' => $userId,
				'display_name' => $name['basename']
			));
			
			//attach to folder
			if ($this->input->get('folderId')) {
				$img->folders()->attach($this->input->get('folderId'));
			}

			$img->folder = $this->input->get('folderName');
			$imgs[] = $img->toArray();
			
		}

		return new Response(json_encode($imgs), 201);
	}

	/**
	 * Delete image with given id from database and filesystem.
	 * 
	 * @param  int/string $id
	 * @return int
	 */
	public function delete($id)
	{	
		$img = $this->model->find($id);
		$this->fs->remove($this->app['base_dir'].'/assets/images/uploads/'.$img->file_name);

		return $this->model->destroy($id);
	}

	/**
	 * Delete all images by passed in ids.
	 * 
	 * @return Response
	 */
	public function deleteMultiple()
	{
		if ($this->input->has('ids')) {
			foreach ($this->input->get('ids') as $id) {
				if ($img = $this->model->find($id)) {
					$this->fs->remove($this->app['base_dir'].'/assets/images/uploads/'.$img->file_name);
					$this->model->destroy($id);
				}
			}
		}

		return new Response(json_encode($this->input->get('ids')), 200);
	}

}