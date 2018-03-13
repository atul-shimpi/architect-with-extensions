<?php namespace Builder\Filesystem\Images;

use Exception;
use Illuminate\Database\Eloquent\Model as Eloquent;

class FolderModel extends Eloquent {

	protected $guarded = array('id');

	protected $table = 'folders';

 	public function user()
    {
        return $this->belongsTo('Builder\Users\UserModel');
    }

    public function images()
    {
        return $this->belongsToMany('Builder\Filesystem\Images\ImageModel', 'images_folders', 'image_id', 'folder_id');
    }
}