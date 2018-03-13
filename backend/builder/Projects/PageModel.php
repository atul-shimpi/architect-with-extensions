<?php namespace Builder\Projects;

use Illuminate\Database\Eloquent\Model as Eloquent;

class PageModel extends Eloquent {

    protected $guarded = array('id');
    
	protected $table = 'pages';

    public function libraries()
    {
        return $this->belongsToMany('Builder\Libraries\LibraryModel', 'pages_libraries', 'page_id', 'library_id');
    }

   	public function pageable()
    {
        return $this->morphTo();
    }
}