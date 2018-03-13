<?php namespace Builder\Projects\Services;

use Cartalyst\Sentry\Sentry;
use Builder\Projects\PageModel;
use Builder\Projects\ProjectModel;

class ProjectRepository {

	/**
	 * Application instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;
	
	/**
	 * Sentry Instance.
	 * 
	 * @var  Cartalyst\Sentry\Sentry
	 */
	private $sentry;

	/**
	 * Project model instance.
	 * 
	 * @var Builder\Projects\ProjetModel
	 */
	private $model;

	/**
	 * Page model instance.
	 * 
	 * @var Builder\Projects\PageModel
	 */
	private $page;

	/**
	 * Create new ProjectRepository instance.
	 * 
	 * @param Cartalyst\Sentry\Sentry $sentry
	 */
	public function __construct(Sentry $sentry, ProjectModel $model, PageModel $page, $app)
	{
		$this->app = $app;
		$this->page = $page;
		$this->model = $model;
		$this->sentry = $sentry;
	}

	/**
	 * Return all projects attached to current user.
	 * 
	 * @return mixed
	 */
	public function all()
	{
		$user = $this->sentry->getUser();

		if ($user) {

			$user   = $user->projects()->get();
			$public = $this->model->where('public', 1)->get();

			return $public->merge($user);
		}	
	}

	/**
	 * Find a project by id or name.
	 * 
	 * @param  string/int $id
	 * @return Collection
	 */
	public function find($id)
	{
		if (is_string($id)) {
			$col = 'name';
		} else {
			$col = 'id';
		}

		$user = $this->sentry->getUser();

		if ($user && strpos($id, 'Demo') === -1) {
			$p = $user->projects()->with('pages.libraries')->where($col, $id)->where('public', 1)->first();
		} else {
			$p = $this->model->with('pages.libraries')->where($col, $id)->where('public', 1)->first();
		}

		if ( ! $p) {
			
			if ($col === 'id') {
				$col = 'projects.id';
			}

			$p = $user->projects()->with('pages.libraries')->where($col, $id)->first();
		}
		
		return $p;
	}

	/**
	 * Delete a page with given id.
	 * 
	 * @param  string/int $id
	 * @return boolean
	 */
	public function deletePage($id)
	{
		return $this->page->destroy($id);
	}

	/**
	 * Delete all given projects pages.
	 * 
	 * @param  string/int $id
	 * @return boolean
	 */
	public function deleteAllPages($id)
	{
		return $this->page->where('pageable_id', $id)
						   ->where('pageable_type', 'Project')
						   ->delete();
	}
}