<?php namespace Builder\Filesystem\Images;

use Intervention\Image\ImageManager;

class ImagesSaver {

	/**
	 * Intervention image manager instance.
	 * 
	 * @var Intervention\Image\ImageManager
	 */
	private $manager;

	/**
	 * Create new image saver instance.
	 * 
	 * @param ImageManager $manager
	 */
	public function __construct(ImageManager $manager)
	{
		$this->manager = $manager;
	}

	/**
	 * Make and save image to filesystem from base64 encoded string.
	 * 
	 * @param  string  $string
	 * @param  string  $path
	 * 
	 * @return Intervention\Image\Image
	 */
	public function saveFromString($string, $path, $aspectRatio = false, $width = 320, $height = 200)
	{
		$string = preg_replace('/data:image\/.+?;base64,/', '', $string); 
		$img = imagecreatefromstring(base64_decode($string));

		if ($aspectRatio) {
			return $this->manager->make($img)->resize($width, $height, function($contstraint) {
				$contstraint->aspectRatio();
			})->save($path);
		} else {
			return $this->manager->make($img)->resize($width, $height)->save($path);
		}
	}

}