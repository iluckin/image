<?php

namespace Turbo\Foundation;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use ImagickException;
use GuzzleHttp\Client as HttpClient;

/**
 * Class Image
 * @package Turbo\Foundation
 */
class Image
{
    /**
     * Resize mode
     */
    const RESIZE_LFIT   = 1;
    const RESIZE_FIXED  = 2;

    /**
     * Imagick instance
     *
     * @var Imagick
     */
    private $imagick;

    /**
     * New an image instance
     *
     * @param $image
     * @param bool $isBlob
     * @throws ImagickException
     */
    protected function __construct($image, $isBlob = false)
    {
        $this->imagick = new Imagick;

        if ($isBlob) {
            $this->imagick->readImageBlob($image);
        } else {
            $this->imagick->readImage($image);
        }
    }

    /**
     * __call
     *
     * @param  string $method
     * @param  array  $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->imagick, $method], $args);
    }

    /**
     * Get imagick instance
     *
     * @return Imagick
     */
    public function getImagick()
    {
        return $this->imagick;
    }

    /**
     * Open an image
     *
     * @param string $path Local image path, or remote url
     * @return Image
     * @throws ImagickException
     */
    public static function open($path)
    {
        if (substr($path, 0, 4) == 'http') {
            return new self((new HttpClient)->get($path)->getBody(), true);
        }

        return new self($path, false);
    }

    /**
     * Load an image
     *
     * @param string $content Binary or base64 encoded image content
     * @param bool $base64
     * @return Image
     * @throws ImagickException
     */
    public static function load($content, $base64 = false)
    {
        if ($base64) {
            if (substr($content, 0, 5) === 'data:') {
                $content = base64_decode(substr($content, strpos($content, ',')));
            } else {
                $content = base64_decode($content);
            }
        }

        return new self($content, true);
    }

    /**
     * Scale an image
     *
     * @param int $width
     * @param int $height
     * @param int $mode
     * @return Image
     * @throws ImagickException
     */
    public function resize($width, $height, $mode = self::RESIZE_LFIT)
    {
        switch ($this->imagick->getImageMimeType()) {

            case 'image/gif':

                $this->resizeGif($width, $height);

                break;
            default:

                switch ($mode) {

                    case self::RESIZE_LFIT:
                        if (!$width && !$height) {
                            return $this;
                        }

                        list('width' => $oldWidth, 'height' => $oldHeight) = $this->imagick->getImageGeometry();

                        if (!$width && $oldHeight > $height) {
                            $ratio = $oldHeight / $height;
                        } else if (!$height && $oldWidth > $width) {
                            $ratio = $oldWidth / $width;
                        } else if ($oldWidth > $width || $oldHeight > $height) {
                            $ratio = max($oldWidth / $width, $oldHeight / $height);
                        } else {
                            $ratio = 0;
                        }

                        if ($ratio) {
                            $this->imagick->thumbnailImage(round($oldWidth / $ratio), round($oldHeight / $ratio));
                        }
                        break;

                    case self::RESIZE_FIXED:
                        $this->imagick->thumbnailImage($width, $height);
                        break;
                }

                break;

        }

        return $this;
    }

    /**
     * resize gif
     * @param $width
     * @param $height
     * @return void
     * @throws ImagickException
     */
    private function resizeGif($width, $height)
    {
        $canvas = new Imagick;
        $color_transparent = new ImagickPixel("transparent"); //透明色
        foreach ($this->imagick as $image) {
            $page = $image->getImagePage();
            $img = new Imagick;
            $img->newImage($page['width'], $page['height'], $color_transparent, 'gif');
            $img->compositeImage($image, Imagick::COMPOSITE_OVER, $page['x'], $page['y']);
            $img->thumbnailImage($width, $height, true);

            $canvas->addImage($img);
            $canvas->setImagePage($img->getImageWidth(), $img->getImageHeight(), 0, 0);
            $canvas->setImageDelay($image->getImageDelay());
            $canvas->setImageDispose($image->getImageDispose());
        }
        $this->imagick = $canvas;
    }

    /**
     * Set image quality
     *
     * @param  int $quality
     * @return Image
     */
    public function quality($quality, $force = false)
    {
        switch ($this->imagick->getImageMimeType()) {

            case 'image/jpeg':
                if ($force || $this->imagick->getImageCompressionQuality() > $quality) {
                    $this->imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $this->imagick->setImageCompressionQuality($quality);
                }
                break;

            default:
                // Ignore
                break;
        }

        return $this;
    }

    /**
     * Generate thumb image
     *
     * @param int $minSize Thumb image minimum size of with and height
     * @param int $quality Thumb image quality
     * @return Image
     * @throws ImagickException
     */
    public function thumb($minSize = 1080, $quality = 80)
    {
        if ($minSize) {
            $mime = $this->imagick->getImageMimeType();
            if ($mime == 'image/jpeg' || $mime == 'image/png') {
                list('width' => $width, 'height' => $height) = $this->imagick->getImageGeometry();
                if ( ($min = min($width, $height)) > $minSize) {
                    $ratio = $min / $minSize;
                    $this->resize(round($width / $ratio), round($height / $ratio), self::RESIZE_FIXED);
                }
            }
        }

        if ($quality) {
            $this->quality($quality);
        }

        return $this;
    }

    /**
     * Add watermark
     *
     * @param string $image
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @param bool $circle
     * @return Image
     * @throws ImagickException
     */
    public function watermark($image, $x = 0, $y = 0, $width = 0, $height = 0, $circle = false)
    {
        $mark = self::open($image);
        $draw = new ImagickDraw;

        if (!$width)  $width  = $mark->getImageWidth();
        if (!$height) $height = $mark->getImageHeight();

        if ($circle) {
            $mark->circle();
        }
        if ($this->imagick->getImageMimeType() == 'image/gif') {

            $canvas = new Imagick;
            $color_transparent = new ImagickPixel("transparent"); //透明色

            foreach ($this->imagick as $image) {
                $page = $image->getImagePage();
                $img = new Imagick;
                $img->newImage($page['width'], $page['height'], $color_transparent, 'gif');
                $img->compositeImage($image, Imagick::COMPOSITE_OVER, $page['x'], $page['y']);

                $tmp_draw = new ImagickDraw;
                $tmp_draw->composite($mark->getImageCompose(), $x, $y, $width, $height, $mark->getImagick());
                $img->drawImage($tmp_draw);

                $canvas->addImage($img);
                $canvas->setImagePage($img->getImageWidth(), $img->getImageHeight(), 0, 0);
                $canvas->setImageDelay($image->getImageDelay());
                $canvas->setImageDispose($image->getImageDispose());
            }
            $this->imagick = $canvas;
        } else {

            $draw->composite($mark->getImageCompose(), $x, $y, $width, $height, $mark->getImagick());
            $this->imagick->drawImage($draw);

        }

        return $this;
    }

    /**
     * Add text
     *
     * @param  string $text
     * @param  int    $x
     * @param  int    $y
     * @param  int    $angle
     * @param  string $font
     * @param  int    $fontSize
     * @param  int    $fontWeight
     * @param  string $fillColor
     * @param  string $underColor
     * @return Image
     */
    public function text($text, $x = 0, $y = 0, $angle = 0, $font = '', $fontSize = 25,
                         $fontWeight = 100, $fillColor = '#ffffff', $underColor = '')
    {
        $draw = new ImagickDraw;

        if ($font) {
            $draw->setFont($font);
        } else {
            $draw->setFont('css/yahei.ttf');
        }
        if ($fontSize)   $draw->setFontSize($fontSize);
        if ($fontWeight) $draw->setFontWeight($fontWeight);
        if ($fillColor)  $draw->setFillColor($fillColor);
        if ($underColor) $draw->setTextUnderColor($underColor);

        if ($this->imagick->getImageMimeType() == 'image/gif') {
            foreach ($this->imagick as $image) {
                $image->annotateImage($draw, $x, $y, $angle, $text);
            }
        } else {
            $this->imagick->annotateImage($draw, $x, $y, $angle, $text);
        }

        return $this;
    }

    /**
     * crop image
     *
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @param int $quality
     * @return Image
     */
    public function crop($x = 0, $y = 0, $width = 0, $height = 0, $quality = 80)
    {
        if (!$width) $width = $this->imagick->getImageHeight() - $x;
        if (!$height) $height = $this->imagick->getImageHeight() - $y;

        $this->imagick->setImageCompressionQuality($quality);
        $this->imagick->cropImage($width, $height, $x, $y);

        return $this;
    }

    /**
     * circle image
     *
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function circle($width = 0,$height = 0)
    {
        if (!$width) $width = $this->imagick->getImageWidth() / 2;
        if (!$height) $height = $this->imagick->getImageHeight() / 2;

        $this->imagick->roundCorners(round($width), round($height));

        return $this;
    }

    /**
     * Save image to local disk
     *
     * @param  string $filename
     * @return bool
     */
    public function save($filename = null)
    {
        if ($this->imagick->getImageMimeType() == 'image/gif') {
            return $this->imagick->writeImages($filename,true);
        } else {
            return $this->imagick->writeImage($filename);
        }
    }

    /**
     * Return base64 encoded image content
     *
     * @param  bool $header     Whether contain image header
     * @return string
     */
    public function base64($header = false)
    {
        $base64 = base64_encode($this->imagick);
        if ($header) {
            $base64 = 'data:' . $this->imagick->getImageMimeType() . ';base64,' . $base64;
        }

        return $base64;
    }

    /**
     * Return image content
     *
     * @return string
     */
    public function __toString()
    {
        return strval($this->imagick);
    }

    /**
     * Upload image to remote server
     *
     * @return string   Remote url
     */
    public function upload()
    {
    }

    /**
     * get image format
     *
     * @return string
     */
    public function getImageFormat()
    {
        return strtolower($this->imagick->getImageFormat());
    }
}
