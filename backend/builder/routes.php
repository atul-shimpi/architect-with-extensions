<?php

//bind controllers into the container
$app['pages.controller'] = $app->share(function() use ($app) { 
	return new Builder\Pages\PagesController($app, $app['request'], new Builder\Pages\PageModel); 
});
$app['projects.controller'] = $app->share(function() use ($app) { 
	return new Builder\Projects\ProjectsController($app['request'], $app['projects.creator'], $app['projects.repository'], new Builder\Exports\Exporter($app, new Builder\Projects\PageModel, new Builder\Projects\ProjectModel, new Builder\Themes\ThemeModel), $app); 
});
$app['home.controller'] = $app->share(function() use ($app) { 
	return new Builder\Home\HomeController($app, $app['sentry'], $app['finder']); 
});
$app['users.controller'] = $app->share(function() use ($app) { 
	return new Builder\Users\UsersController($app, $app['request'], $app['sentry'], new Builder\Validation\UserValidator($app, $app['validator']), new Builder\Users\UserCreator($app));
});
$app['images.controller'] = $app->share(function() use ($app) { 
	return new Builder\Filesystem\Images\ImagesController($app, $app['request'], new Builder\Filesystem\Images\ImageModel, $app['filesystem']);
});
$app['folders.controller'] = $app->share(function() use ($app) { 
	return new Builder\Filesystem\Images\FoldersController($app, $app['request'], new Builder\Filesystem\Images\FolderModel);
});
$app['themes.controller'] = $app->share(function() use ($app) {
	$maker = new Builder\Themes\ThemeMaker(new Builder\Themes\Less($app), new Builder\Themes\ThemeModel, $app['filesystem'], $app);
	return new Builder\Themes\ThemesController($app, $app['request'], new Builder\Themes\ThemeModel, $maker);
});
$app['exports.controller'] = $app->share(function() use ($app) {
	$exporter = new Builder\Exports\Exporter($app, new Builder\Projects\PageModel, new Builder\Projects\ProjectModel, new Builder\Themes\ThemeModel);
	return new Builder\Exports\ExportsController($app, $app['request'], $exporter);
});
$app['templates.controller'] = $app->share(function() use ($app) {
	return new Builder\Templates\TemplatesController($app, $app['request'], new Builder\Templates\TemplateModel, $app['imagesSaver']);
});
$app['libraries.controller'] = $app->share(function() use ($app) {
	return new Builder\Libraries\LibrariesController($app, $app['request'], new Builder\Libraries\LibraryModel);
});
$app['update.controller'] = $app->share(function() use ($app) {
	return new Builder\Install\UpdateController($app);
});
$app['settings.controller'] = $app->share(function() use ($app) {
	return new Builder\Settings\SettingsController($app, $app['request'], new Builder\Settings\SettingModel);
});

//home
$app->get('/', 'home.controller:index');
$app->get('/custom-elements', 'home.controller:customElements')->before($loggedInFilter);

//users
$app->get('/users', 'users.controller:index')->before($loggedInFilter);
$app->post('/users/login', 'users.controller:login');
$app->delete('/users/{id}', 'users.controller:delete')->before($loggedInFilter);
$app->post('/users/register', 'users.controller:store');
$app->post('/users/{id}/modify-permissions', 'users.controller:modifyPermissions')->before($loggedInFilter);
$app->post('/users/logout', 'users.controller:logout')->before($loggedInFilter);
$app->post('/assign-permissions-to-all', 'users.controller:assignPermissionsToAll')->before($loggedInFilter);

//projects
$app->get('/projects/{id}/render/{name}', "projects.controller:render")->value('name', 'index');
$app->get('/projects', "projects.controller:index")->before($loggedInFilter);
$app->get('/projects/{id}', "projects.controller:show")->before($loggedInFilter);
$app->get('/projects/{id}/preview', "projects.controller:preview")->before($loggedInFilter);
$app->post('/projects', "projects.controller:store")->before($loggedInFilter);
$app->put('/projects/{id}', "projects.controller:update")->before($loggedInFilter);
$app->post('/projects/{id}/save-image', 'projects.controller:saveImage')->before($loggedInFilter);
$app->delete('/projects/{id}', "projects.controller:delete")->before($loggedInFilter);
$app->delete('/projects/delete-page/{id}', "projects.controller:deletePage")->before($loggedInFilter);
$app->delete('/projects/{id}/delete-all-pages', "projects.controller:deleteAllPages")->before($loggedInFilter);
$app->post('/projects/{id}/publish', 'projects.controller:publish')->before($loggedInFilter);
$app->post('/projects/{id}/unpublish', 'projects.controller:unpublish')->before($loggedInFilter);

//pages
$app->post('/pages', "pages.controller:store")->before($loggedInFilter);
$app->get('/pages', "pages.controller:index")->before($loggedInFilter);

//images
$app->post('/images/', "images.controller:store")->before($loggedInFilter);
$app->get('/images/', "images.controller:index")->before($loggedInFilter);
$app->delete('/images/{id}', "images.controller:delete")->before($loggedInFilter);
$app->post('/images/delete', "images.controller:deleteMultiple")->before($loggedInFilter);

//image folders
$app->post('/folders', "folders.controller:store")->before($loggedInFilter);
$app->get('/folders', "folders.controller:index")->before($loggedInFilter);
$app->delete('/folders/{id}', "folders.controller:delete")->before($loggedInFilter);

//themes
$app->get('/pr-themes/', "themes.controller:index")->before($loggedInFilter);
$app->get('/pr-themes/bootstrap-vars', "themes.controller:bootstrapVars")->before($loggedInFilter);
$app->post('/pr-themes/', 'themes.controller:store')->before($loggedInFilter);
$app->post('/pr-themes/save-image/', 'themes.controller:saveImage')->before($loggedInFilter);
$app->delete('/pr-themes/{id}', "themes.controller:delete")->before($loggedInFilter);

//exports
$app->get('/export/theme/{name}', 'exports.controller:exportTheme')->before($demoFilter)->before($loggedInFilter);
$app->get('/export/page/{id}', 'exports.controller:exportPage')->before($demoFilter)->before($loggedInFilter);
$app->get('/export/project/{id}', 'exports.controller:exportProject')->before($demoFilter)->before($loggedInFilter);
$app->get('/export/image/{path}', 'exports.controller:exportImage')->before($demoFilter)->before($loggedInFilter);
$app->post('/export/project-ftp/{id}', 'exports.controller:exportProjectToFtp')->before($demoFilter)->before($loggedInFilter);

//templates
$app->post('/pr-templates/', 'templates.controller:store')->before($loggedInFilter);
$app->get('/pr-templates/', "templates.controller:index")->before($loggedInFilter);
$app->post('/pr-templates/{id}/save-image', 'templates.controller:saveImage')->before($loggedInFilter);
$app->delete('/pr-templates/{id}', 'templates.controller:delete')->before($loggedInFilter);
$app->put('/pr-templates/{id}', 'templates.controller:update')->before($loggedInFilter);

//libraries
$app->get('/libraries', 'libraries.controller:index')->before($loggedInFilter);
$app->post('/libraries', 'libraries.controller:store')->before($loggedInFilter);
$app->put('/libraries/{id}', 'libraries.controller:update')->before($loggedInFilter);
$app->post('/libraries/attach/{page}/{library}', 'libraries.controller:attachToPage')->before($loggedInFilter);
$app->post('/libraries/detach/{page}/{library}', 'libraries.controller:detachFromPage')->before($loggedInFilter);
$app->delete('/libraries/{id}', 'libraries.controller:delete')->before($loggedInFilter);

//update
$app->get('update', 'update.controller:index');
$app->post('run-update', 'update.controller:runUpdate');

//settings
$app->post('save-settings', 'settings.controller:update');
$app->get('settings', 'settings.controller:index');

//translations
$app->get('/trans-messages', function() use($app) {
	$messages = $app['translator']->getMessages($app['request']->query->get('lang'));
	return json_encode($messages['messages']);
})->before($loggedInFilter);

if ($app['is_demo']) {

	$app->get('reset', function() use($app) {
		$r = new Builder\Database\Reseter($app, new Builder\Install\Seeder($app, $app['projects.creator']));
		return $r->reset();
	});

	$app->get('time-until-reset', function() use($app) {
		$r = new Builder\Database\Reseter($app, new Builder\Install\Seeder($app, $app['projects.creator']));
		return $r->timeUntilReset();
	});
}

// $app->get('seed-templates', function() use($app) {
// 	$s = new Builder\Install\Seeder($app, $app['projects.creator']);
//
//     $s->seedTemplates();
// });