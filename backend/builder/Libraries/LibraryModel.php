<?php namespace Builder\Libraries;

use Illuminate\Database\Eloquent\Model as Eloquent;

class LibraryModel extends Eloquent {

	protected $fillable = array('name', 'path', 'type', 'user_id');

	protected $table = 'libraries';

	public function pages()
    {
        return $this->belongsToMany('Builder\Projects\PageModel', 'pages_libraries', 'library_id', 'page_id');
    }

}