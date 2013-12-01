<?php
require_once('lib/http.php');
require_once('lib/string.php');


class Thumbnail extends Imagick
{
  private static $sharpenMatrix = array(
      -1, -1, -1,
      -1, 16, -1,
      -1, -1, -1
    );


  public function __construct( $path = null, $index = 0 )
  {
    if (!empty($path)) {
      assert($path[strlen($path)-1] !== ']');
      assert(is_int($index));
      $path .= "[$index]";
    }

    parent::__construct($path);
  }


  private static function newException()
  {
    $class = new ReflectionClass(
      class_exists('ImageUploadException') ?
        'ImageUploadException' :
        'RuntimeException');
    return $class->newInstanceArgs(func_get_args());
  }


  public function getImageTypeName()
  {
    $type = $this->getImageType();
    $class = new ReflectionClass(__CLASS__);
    foreach ($class->getConstants() as $const => $value) {
      if ($value === $type && starts_with($const, 'IMGTYPE_'))
        return $const;
    }
    return false;
  }


  public function prepareThumbnail( $format = 'JPEG', $quality = null )
  {
    switch ($this->getImageType()) {
      case Imagick::IMGTYPE_BILEVEL:
        assert($this->getNumberImages() === 1);
      case Imagick::IMGTYPE_PALETTE:
      case Imagick::IMGTYPE_PALETTEMATTE:
      case Imagick::IMGTYPE_TRUECOLORMATTE:
        $this->setImageType(Imagick::IMGTYPE_TRUECOLOR);
      case Imagick::IMGTYPE_TRUECOLOR:
        break;

      case Imagick::IMGTYPE_GRAYSCALEMATTE:
        $this->setImageType(Imagick::IMGTYPE_GRAYSCALE);
      case Imagick::IMGTYPE_GRAYSCALE:
        break;

      case Imagick::IMGTYPE_COLORSEPARATIONMATTE:
        $this->setImageType(Imagick::IMGTYPE_COLORSEPARATION);
      case Imagick::IMGTYPE_COLORSEPARATION:
        break;

      default:
        throw self::newException(
          'Unsupported Imagick image type ' . $this->getImageTypeName(),
          HTTPStatusCodes::UNSUPPORTED_MEDIA_TYPE);
    }

    if (!empty($format))
      $this->setImageFormat($format);

    if (is_null($quality) && class_exists('Config') && isset(Config::$images))
      $quality = @Config::$images['jpegQuality'];
    if (!is_null($quality))
      $this->setImageCompressionQuality($quality);
  }


  public function writeThumbnail( $width, $height, $crop, $prepare = true,
    $sharpenMatrix = null )
  {
    if ($prepare)
      $this->prepareThumbnail();

    if ($width < $this->getImageWidth() || $height < $this->getImageHeight()) {
      if ($crop) {
        $this->cropThumbnailImage($width, $height);
      } else {
        $this->thumbnailImage($width, $height, true);
      }

      if (is_null($sharpenMatrix))
        $sharpenMatrix = self::$sharpenMatrix;
      if (!empty($sharpenMatrix))
        $this->convolveImage($sharpenMatrix);
    }

    $this->setImageColorSpace(
      ($this->getImageType() === Imagick::IMGTYPE_GRAYSCALE) ?
        Imagick::COLORSPACE_GRAY :
        Imagick::COLORSPACE_RGB);
    $this->stripImage();
    $this->writeImage();
  }


  private static function getGridClassDimensions( $gridInfo, $gridClass )
  {
    $gridSize = $gridInfo['size'];
    $borderWidth = $gridInfo['borderWidth'];
    return array(
        $gridClass['width'] * $gridSize - $borderWidth,
        $gridClass['height'] * $gridSize - $borderWidth
      );
  }


  public function writeThumbnails( $gridInfo, $pathSuffix, $pathPrefix, $crop,
    $prepare = true )
  {
    $filename = basename($pathSuffix);
    assert(!empty($filename));
    $pathSuffix = '/' . substr($pathSuffix, 0, max(strlen($pathSuffix) - strlen($filename) - 1, 0));
    if (!empty($pathPrefix) && $pathPrefix[strlen($pathPrefix)-1] !== '/')
      $pathPrefix .= '/';
    //var_dump($pathPrefix, $pathSuffix, $filename);

    if ($prepare)
      $this->prepareThumbnail();

    $createdFiles = array();
    foreach ($gridInfo['classes'] as $gc)
    {
      $dir = $pathPrefix . $gc['dir'] . $pathSuffix;
      if (!Filesystem::mkdirr($dir))
        throw self::newException('Internal error',
          HTTPStatusCodes::INTERNAL_SERVER_ERROR);

      list($width, $height) =
        self::getGridClassDimensions($gridInfo, $gridClass);
      $path = "$dir/$filename.jpg";
      $thumb = clone $this;
      $thumb->setImageFilename($path);
      $thumb->writeThumbnail($width, $height, $crop, false);
      $thumb->destroy();

      $createdFiles[] = $path;
    }

    return $createdFiles;
  }

}

?>
