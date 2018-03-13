<?php namespace Builder\Exports;

use Sentry;
use Silex\Application;
use Builder\Exports\Exporter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportsController {

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
	 * Application instance.
	 * 
	 * @var Sillex\Application
	 */
	private $app;

	/**
	 * Exporter instance.
	 * 
	 * @var Builder\Exports\Exporter
	 */
	private $export;

	/**
	 * Create new ExportsController isntance.
	 * 
	 * @param Application $app     
	 * @param Request     $request
	 */
	public function __construct(Application $app, Request $request, Exporter $exporter)
	{
		$this->app = $app;
		$this->export = $exporter;
		$this->request = $request;
		$this->input = $request->request;
	}

	/**
	 * Return a file download response for given theme.
	 * 
	 * @return Response(download)
	 */
	public function exportTheme($name)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('export')) {
			return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
		}

		$path = $this->export->makeThemePath($name);

		if ( ! $this->export->canDownloadTheme($name)) {
			return new Response($this->app['translator']->trans('noAccessTheme'), 403);
		}

		if ( ! file_exists($path)) {
			return new Response($this->app['translator']->trans('noThemeWithName'), 404);
		}

		return $this->app->sendFile($path, 200, array('Content-type' => 'text/css'), 'attachment');
	}

	/**
	 * Export a project with given id.
	 * 
	 * @param  string/int $id
	 * @return Response
	 */
	public function exportProject($id)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('export')) {
			return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
		}

		if ($path = $this->export->project($id)) {
			return $this->app->sendFile($path, 200, array('Content-type' => 'application/zip'), 'attachment');
		}

		return new Response($this->app['translator']->trans('exportProblem'), 500);
	}

	/**
	 * Export a page with given id.
	 * 
	 * @param  string/int $id
	 * @return Response
	 */
	public function exportPage($id)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('export')) {
			return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
		}

		if ($path = $this->export->page($id)) {
			return $this->app->sendFile($path, 200, array('Content-type' => 'application/zip'), 'attachment');
		}

		return new Response($this->app['translator']->trans('pageExportProblem'), 500);
	}

	/**
	 * Export image at given path.
	 * 
	 * @param  string $path
	 * @return Response
	 */
	public function exportImage($path)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('export')) {
			return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
		}

		$url = $this->export->decodeImageUrl($path);

		return $this->app->sendFile($url, 200, array('Content-type' => 'application/image'), 'attachment');
	}

	/**
	 * Export project to remote ftp.
	 * 
	 * @param  string|int $id
	 * @return Response
	 */
	public function exportProjectToFtp($id)
	{
		if ( ! $this->app['sentry']->getUser()->hasAccess('publish')) {
			return new Response($this->app['translator']->trans('noPermissionsGeneric'), 403);
		}

		if ( ! $this->input->get('host')) {
			return new Response($this->app['translator']->trans('ftpNoHost'), 400);
		}

		if ( ! $this->input->get('user')) {
			return new Response($this->app['translator']->trans('ftpNoUsername'), 400);
		}

		if ( ! $this->input->get('password')) {
			return new Response($this->app['translator']->trans('ftpNoPassword'), 400);
		}

		if ( ! $this->input->get('root')) {
			return new Response($this->app['translator']->trans('ftpNoFolder'), 400);
		}

		try {
			@$this->export->projectToFtp($id, $this->input->all());
		} catch (\Exception $e) {
			return new Response($e->getMessage(), 400);
		}

		return new Response($this->app['translator']->trans('projectExportSuccess'), 200);
	}
}