<?php namespace Builder\Templates;

use Illuminate\Database\Eloquent\Model as Eloquent;

class TemplateModel extends Eloquent {

	protected $fillable = array('name');

	protected $table = 'templates';

	protected $morphClass = 'Template';

	public function pages()
    {
        return $this->morphMany('Builder\Projects\PageModel', 'pageable');
    }
}