<?php namespace Builder\Projects;

use Exception;
use Builder\Pages\PageModel as Page;
use Illuminate\Database\Eloquent\Model as Eloquent;

class NameAlreadyExistsException extends Exception {}

class ProjectModel extends Eloquent {

	protected $fillable = array('name', 'public');

	protected $table = 'projects';

	protected $morphClass = 'Project';

	public function pages()
    {
        return $this->morphMany('Builder\Projects\PageModel', 'pageable');
    }

    public function users()
    {
        return $this->belongsToMany('Builder\Users\UserModel', 'users_projects', 'project_id', 'user_id');
    }
    
	public function attachNewPage(array $data, $id)
	{
		$page = new Page($data);

		return $this->find($id)->pages()->save($page);
	}

}