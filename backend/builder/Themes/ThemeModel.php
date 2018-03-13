<?php namespace Builder\Themes;

use Illuminate\Database\Eloquent\Model as Eloquent;

class ThemeModel extends Eloquent {

	protected $fillable = array('name');

	protected $table = 'themes';
}