<?php namespace Builder\Projects;

use Silex\Application;
use Builder\Projects\ProjectModel as Project;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Builder\Projects\Services\ProjectCreator;
use Builder\Projects\Services\ProjectRepository;

class ProjectsController {

	/**
	 * Silex application instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;

	/**
	 * Exporter instance.
	 * 
	 * @var Builder\Exports\Exporter
	 */
	private $exporter;

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
	 * ProjectCreator instance.
	 * 
	 * @var Builder\Projects\Services\ProjectCreator
	 */
	private $creator;

	/**
	 * ProjectRepository instance.
	 * 
	 * @var Builder\Projects\Services\ProjectRepository
	 */
	private $repository;

	/**
	 * Create new ProjectsController instance.
	 * 
	 * @param Application $app    
	 * @param Request     $request
	 */
	public function __construct(Request $request, ProjectCreator $creator, ProjectRepository $repo, $exporter, $app)
	{	
		$this->app = $app;
		$this->repo = $repo;
		$this->creator = $creator;
		$this->request = $request;
		$this->exporter = $exporter;
		$this->input = $request->request;	
	}

	/**
	 * Render and display project assets as a site.
	 * 
	 * @param  int|string $id
	 * @return Response
	 */
	public function render($id, $name)
	{
        $project = $this->repo->find((int)$id);

		if ( ! $project || ! $project->published) {
			return $this->app['twig']->render('404.twig.html');
		}

		$path = $this->exporter->project((int) $id, false);

		if ( ! $path) {
            return $this->app['twig']->render('404.twig.html');
		}
		
		$base = str_replace($this->app['base_dir'], $this->app['base_url'], $path);

		return $this->app->redirect($base.$name.'.html');
	}

	/**
	 * Create a new project.
	 * 
	 * @return Response
	 */
	public function store()
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('projects.create')) {
			return new Response($this->app['translator']->trans('noPermProjectCreate'), 403);
		}

		if ( ! $this->input->has('name')) {
			return new Response($this->app['translator']->trans('projectNameRequired'), 400);
		}

		if ($this->repo->find($this->input->get('name'))) {
			return new Response($this->app['translator']->trans('projectWithNameExists'), 400);
		}

		return new Response($this->creator->create($this->input->all()), 201);
	}

	/**
	 * Update an existing project.
	 * 
	 * @param  sting/int $id
	 * @return Response
	 */
	public function update($id)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('projects.update')) {
			return new Response($this->app['translator']->trans('noPermProjectUpdate'), 403);
		}

		$p = $this->creator->update($this->input->all());

		if ( ! $p) {
			return new Response($this->app['translator']->trans('problemUpdatingProject'), 500);
		}

		return new Response(json_encode($p), 200);
	}

	/**
	 * Publish project with given id.
	 * 
	 * @param  string|int $id
	 * @return Response
	 */
	public function publish($id)
	{
		if ($project = $this->repo->find((int)$id)) {
			$project->published = 1;
			$project->save();
		}

		return new Response($this->app['translator']->trans('projectPublishSuccess'), 200);
	}

	/**
	 * Unpublish project with given id.
	 * 
	 * @param  string|int $id
	 * @return Response
	 */
	public function unpublish($id)
	{
		if ($project = $this->repo->find((int)$id)) {
			$project->published = 0;
			$project->save();
		}

		return new Response($this->app['translator']->trans('projectUnpublishSuccess'), 200);
	}

	public function saveImage($id)
	{	
		$this->app['imagesSaver']->saveFromString(
			$this->request->getContent(), 
			'assets/images/projects/project-'.$id.'.png', 
			false
		);

		return new Response($this->app['base_url'].'/assets/images/projects/project-'.$id.'.png', 200);
	}

	public function index()
	{
		return new Response($this->repo->all());
	}

	/**
	 * Find a project by name or id.
	 * 
	 * @param  int/string $id
	 * @return Response
	 */
	public function show($id)
	{
		$project = $this->repo->find($id);

		if ($project) {
			return new Response($project, 200);
		}
	
		return new Response($this->app['translator']->trans('noProjectWithName'), 400);
	}

	/**
	 * Delete project with given id.
	 * 
	 * @param  int|string $id
	 * @return Response
	 */
	public function delete($id)
	{
		if ($this->app['is_demo'] && $this->app['sentry']->getUser()->email == 'demo@demo.com') {
			return new Response('You can\'t delete projects on demo account.', 403); 
		}

		if ( ! $this->app['sentry']->getUser()->hasAccess('projects.delete')) {
			return new Response($this->app['translator']->trans('noPermProjectDelete'), 403);
		}

		$project = $this->repo->find((int)$id);

		if ($project->public) {
			return new Response($this->app['translator']->trans('noPermissionsToDeleteProject'), 403);
		}

		if ($project && $project->pages()->delete() && $project->delete()) {
			return new Response($this->app['translator']->trans('projectDeleteSuccess'), 204);
		}
	}

	/**
	 * Delete a page with given id.
	 * 
	 * @param  string/int $id
	 * @return Response
	 */
	public function deletePage($id)
	{
		return new Response($this->repo->deletePage($id), 204);
	}

	public function deleteAllPages($id)
	{
		return new Response($this->repo->deleteAllPages($id), 200);
	}
}