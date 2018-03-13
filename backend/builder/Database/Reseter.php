<?php namespace Builder\Database;

use Carbon\Carbon;
use Builder\Install\Seeder;
use Illuminate\Database\Capsule\Manager as DB;
use Symfony\Component\HttpFoundation\Response;

class Reseter {

	/**
	 * Application instance.
	 * 
	 * @var Silex\Application;
	 */
	private $app;

	/**
	 * Database seeder instance.
	 * 
	 * @var Builder\Install\Seeder
	 */
	private $seeder;

	/**
	 * Absolute path to a file that stores last database reset timestamp.
	 * 
	 * @var string
	 */
	private $lastResetPath;

	/**
	 * Themes that shouldn't be deleted.
	 * 
	 * @var array
	 */
	private $skip = array('cerulean', 'cyborg', 'cosmo', 'darkly', 'default', 'flatly', 'yeti', 'journal', 'lumen', 'paper', 'readable', 'sandstone', 
		'simplex', 'slate', 'spacelab', 'superhero', 'united'
	);

	/**
	 * Create new Reseter instance.
	 * 
	 * @param Silex\Application $app
	 * @param Seeder $seeder
	 */
	public function __construct($app, Seeder $seeder)
	{
		$this->app = $app;
		$this->seeder = $seeder;
		$this->lastResetPath = $app['base_dir'].'/backend/config/lastReset.php';
	}

	/**
	 * Reset database by truncating tables and re-seeding.
	 * 
	 * @return Response
	 */
	public function reset()
	{
		if ($_SERVER['REMOTE_ADDR'] !== $_SERVER['SERVER_ADDR']) {
    		return new Response('You don\'t have permissions to do that.', 403);
		}

		DB::table('folders')->truncate();
		DB::table('groups')->truncate();
		DB::table('images')->truncate();
		DB::table('images_folders')->truncate();
		DB::table('libraries')->truncate();
		DB::table('pages')->truncate();
		DB::table('pages_libraries')->truncate();
		DB::table('projects')->truncate();
		DB::table('templates')->truncate();
		DB::table('themes')->truncate();
		DB::table('users_projects')->truncate();

		$this->emptyFolders();

		$this->seeder->seed();
		$this->seeder->seedDemoProjects();

		file_put_contents($this->lastResetPath, '<?php return '.Carbon::now()->timestamp.';');

		return new Response('Reset database successfully.', 200);
	}

	private function emptyFolders()
	{
		$paths = array('/assets/images/projects', '/assets/images/thumbnails/templates', '/assets/images/uploads', '/storage/exports', '/themes');
		
		foreach ($paths as $path) {

			if ( ! is_dir($this->app['base_dir'].$path)) {
				mkdir($this->app['base_dir'].$path, 0777, true);
			}
			
			$files = new \RecursiveIteratorIterator(
			    new \RecursiveDirectoryIterator($this->app['base_dir'].$path, \RecursiveDirectoryIterator::SKIP_DOTS),
			    \RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($files as $fileinfo) {

				if ($this->shouldSkip($path, $fileinfo)) {
					continue;
				}

			    if ($fileinfo->isDir()) {
			    	rmdir($fileinfo->getRealPath());
			    } else {
			    	unlink($fileinfo->getRealPath());
			    }
			}

			unset($files);
		}
	}

	private function shouldSkip($path, $fileinfo)
	{
		if ( ! str_contains($path, 'themes')) return false;

		foreach ($this->skip as $theme) {
			if (str_contains($fileinfo->getRealPath(), $theme)) {
				return true;
			}
		}
	}

	/**
	 * Return time (in milliseconds) until next database reset on demo site.
	 * 
	 * @return integer
	 */
	public function timeUntilReset()
	{
		$stamp = require_once($this->lastResetPath);

		$now  = Carbon::now();
		$last = Carbon::createFromTimeStamp($stamp);
		
		$timeLeft = $this->app['reset_interval'] - $now->diffInMinutes($last);

		while ($timeLeft <= 0) {
			$timeLeft += $this->app['reset_interval'];
		}
		
		return $timeLeft * 60 * 1000;
	}
}