<?php namespace Builder\Filesystem\Images;

use Exception;
use Illuminate\Database\Eloquent\Model as Eloquent;

class NameAlreadyExistsException extends Exception {}

class ImageModel extends Eloquent {

	protected $guarded = array('id');

	protected $table = 'images';

	public function folders()
    {
        return $this->belongsToMany('Builder\Filesystem\Images\FolderModel', 'images_folders', 'image_id', 'folder_id');
    }
}