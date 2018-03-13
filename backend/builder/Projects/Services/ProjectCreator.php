<?php namespace Builder\Projects\Services;

use Builder\Projects\PageModel as Page;
use Builder\Projects\ProjectModel as Project;
use Builder\Templates\TemplateModel as Template;
use Builder\Libraries\LibraryModel as Library;

class ProjectCreator {

	/**
	 * Filesystem instance.
	 * 
	 * @var Symfony\Component\Filesystem\Filesystem
	 */
	private $fs;

	/**
	 * Application instance.
	 * 
	 * @var Silex\Application
	 */
	private $app;

	/**
	 * Page model instance.
	 * 
	 * @var  Builder\Pages\PageModel 
	 */
	private $page;

	/**
	 * Project model instance.
	 * 
	 * @var  Builder\Projects\ProjectModel 
	 */
	private $project;

	/**
	 * Sentry instance.
	 * 
	 * @var  Cartalyst\Sentry\Sentry
	 */
	private $sentry;

	/**
	 * Create new ProjectCreator instance.
	 * 
	 * @param Builder\Projects\ProjectModel $project
	 * @param Builder\Pages\PageModel 	    $page 
	 * @param Cartalyst\Sentry\Sentry       $sentry
	 */
	public function __construct($app, $fs, Project $project, Page $page, $sentry)
	{
		$this->fs = $fs;
		$this->app = $app;
		$this->page = $page;
		$this->sentry = $sentry;
		$this->project = $project;
	}

	/**
	 * Create a new project.
	 * 
	 * @param  array $input
	 * @return ProjectModel
	 */
	public function create(array $input)
	{
		$public  = isset($input['public']) ? (int) $input['public'] : 0;
		$project = $this->project->create(array('name' => $input['name'], 'public' => $public));

		//create a placeholder image for project
		$this->fs->copy($this->app['base_dir'].'/assets/images/image_not_found.png', $this->app['base_dir'].'/assets/images/projects/project-'.$project->id.'.png');
		
		if (isset($input['template'])) {
			$project = $this->useTemplate($project, $input['template'], $public);
		} else {
			$page = new Page(array('name' => 'index', 'theme' => 'yeti'));
			$project->pages()->save($page);
		}

		if ( ! $public) {
			$this->sentry->getUser()->projects()->attach($project->id);
		}
		
		return $project;
	}

	/**
	 * Use given template for given project.
	 * 
	 * @param  ProjectModel $project    
	 * @param  integer      $templateId 
	 * 
	 * @return ProjectModel
	 */
	private function useTemplate(Project $project, $templateId)
	{
		$template = Template::with('pages')->find($templateId);

		$pages = array();

		foreach ($template->pages as $page) {
			$pages[] = new Page(array_except($page->toArray(), array('id', 'pageable_id', 'pageable_type', 'created_at', 'updated_at')));
		}

		$project->pages()->saveMany($pages);

		$this->attachLibraries($project);

		//copy thumbnail from template to project
		$path = $this->app['base_dir'].'/'.$template->thumbnail;

		if ( ! file_exists($path)) {
			$path = $this->app['base_dir'].'/assets/images/image_not_found.png';
		}

		$this->fs->copy($path, $this->app['base_dir'].'/assets/images/projects/project-'.$project->id.'.png', true);

		return $project;
	}

	/**
	 * Attach libraries from given template pages to given projects pages.
	 * 
	 * @param  Project  $project
	 * @param  Template $template
	 * 
	 * @return void
	 */
	private function attachLibraries(Project $project)
	{
		foreach ($project->pages as $page) {
			if ($page->libraries && $libs = json_decode($page->libraries, true)) {
				$ids = Library::whereIn('name', $libs)->lists('id');
				
				$page->libraries()->attach($ids);
			}
		}
	}

	/**
	 * Update given project in database.
	 * 
	 * @param  array $project
	 * @return Project|boolean
	 */
	public function update(array $input)
	{
		$project = $input['project'];

		//if project with this id doesn't exist - bail
		if ( ! $this->project->find($project['id'])) {
			return false;
		}

		if (isset($input['template'])) {

			$this->deletePages($project['id']);
			
			$project = $this->useTemplate(Project::find($project['id']), $input['template']);
		} else {
			$this->updatePages($project);
		}

		$p = $this->project->with('pages.libraries')->find($project['id']);
		$p->touch();

		return $p->toArray();
	}

	/**
	 * Delete projects pages and any attached libraries.
	 * 
	 * @param  int|string $id
	 * @return void
	 */
	private function deletePages($id)
	{
		$pages = Page::where('pageable_id', $id)->where('pageable_type', 'Project')->get(array('id'));

		foreach ($pages as $page) {
			$page->libraries()->detach();
			$page->delete();
		}
	}

	/**
	 * Update project pages with given data.
	 * 
	 * @param  array  $project
	 * @return void
	 */
	private function updatePages(array $project)
	{
		foreach ($project['pages'] as $page) {

			//find page in db or create a new one
			if (isset($page['id'])) {
				$m = $this->page->find($page['id']);
			} else {
				$m = $this->page->newInstance();
			}
				
			//update the model values with the ones from input
			foreach ($page as $k => $v) {
				$m->$k = is_array($v) ? json_encode($v) : $v;
			}

			$m->save();
		}
	}
}