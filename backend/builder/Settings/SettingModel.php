<?php namespace Builder\Settings;

use Illuminate\Database\Eloquent\Model as Eloquent;

class SettingModel extends Eloquent {

    protected $guarded = array('id');
    
	protected $table = 'settings';
}