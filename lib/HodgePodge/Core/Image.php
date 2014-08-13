<?php
namespace HodgePodge\Core;

use HodgePodge\Exception;
use HodgePodge\Core\Log;

class Image
{
    const MAX_FONT_SIZE = 100;

    public static $config = array(
        'margin' => 20,
        'font' => 'arial.ttf',
        'opacity' => 0.2,
    );

    public static function fromFile($filename)
    {
        if (is_array($filename)) { // If is array, assume it came from $_FILES upload
            $filename = $filename['tmp_name'];
        }
        
        if (!$filename) {
            return false;
        }
        
        // Imagemagick cannot read directly from URLs - so open the URL as a file stream instead
        $file = @file_get_contents($filename);
        
        try {
            $image = new \Imagick();
            $image->readImageBlob($file);
        } catch (\ImagickException $e) {
            $image->destroy();
            return false;
        }
        
        if ($image) {
            return new Image($image);
        } else {
            return false;
        }
    }
    
    public function __construct(\Imagick $image)
    {
        $this->image = $image;
    }

    public function __destruct()
    {
        if ($this->image) {
            $this->image->destroy();
        }
    }

    public function __clone()
    {
        $this->image = $this->image->clone();
    }

    ///////////////////////////////////////

    public function save($filename, $quality = 95)
    {
        $this->image->setImageFormat('jpeg');
        $this->image->setCompressionQuality($quality);
        return $this->image->writeImage($filename);
    }

    public function display($type = 'png')
    {
        switch ($type) {
            default:
            case 'png':
                header('Content-type: image/png');
                $this->image->setImageFormat('png');
                echo $this->image;
                return;
            
            case 'jpg':
            case 'jpeg':
                header('Content-type: image/jpeg');
                $this->image->setImageFormat('jpeg');
                $this->image->setCompressionQuality(95);
                echo $this->image;
                return;
        }
    }

    public function __toString()
    {
        $this->image->setImageFormat('jpeg');
        $this->image->setCompressionQuality(95);
        return (string) $this->image;
    }

    public function resize($width, $height, $keep_ratio = true)
    {
        $this->image->scaleImage($width, $height, !$keep_ratio);
        return $this;
    }

    public function resizeLongest($longest_side)
    {
        $width_orig = $this->image->getImageWidth();
        $height_orig = $this->image->getImageHeight();
        $ratio_orig = $width_orig/$height_orig;
        
        if ($width_orig > $height_orig) {
            $width = $longest_side;
            $height = 0;
        } else {
            $width = 0;
            $height = $longest_side;
        }
        
        $this->image->scaleImage($width, $height);
        return $this;
    }

    public function resizeShortest($shortest_side)
    {
        $width_orig = $this->image->getImageWidth();
        $height_orig = $this->image->getImageHeight();
        $ratio_orig = $width_orig/$height_orig;
        
        if ($width_orig > $height_orig) {
            $width = 0;
            $height = $shortest_side;
        } else {
            $width = $shortest_side;
            $height = 0;
        }
        
        $this->image->scaleImage($width, $height);
        return $this;
    }

    public function crop($x, $y, $height, $width)
    {
        $this->image->cropImage($width, $height, $x, $y);
        return $this;
    }

    public function resizeCrop($width, $height, $compass = 'N')
    {
        $this->resizeShortest($width > $height ? $width : $height);
        $width_orig = $this->image->getImageWidth();
        $height_orig = $this->image->getImageHeight();
        
        list($x, $y) = self::calculateCompass(
            $width_orig,
            $height_orig,
            $width,
            $height,
            $compass
        );
        
        $this->crop($x, $y, $height, $width);
        return $this;
    }

    public function rotate($degrees)
    {
        $degrees = 0 - $degrees; // Reverse direction for Imagemagick
        
        while ($degrees < 0) {
            $degrees += 360;
        }
        
        $background = new \ImagickPixel();
        $this->image->rotateImage($background, $degrees);
        $background->destroy();
        
        return $this;
    }

    public function watermark($image, $compass = 'SE')
    {
        if ($image instanceof Image) {
            $watermark = $image;
        } else {
            $watermark = Image::fromFile($image);
        }
        
        if (!$watermark) {
            Log::error('Watermark image is invalid');
            return $this;
        }
        
        list($x, $y) = self::calculateCompass(
            $this->image->getImageWidth(),
            $this->image->getImageHeight(),
            $watermark->image->getImageWidth(),
            $watermark->image->getImageHeight(),
            $compass
        );
        
        $this->image->compositeImage($watermark->image, imagick::COMPOSITE_OVER, $x, $y);
        
        return $this;
    }

    public function watermarkProportional($image, $compass = 'SE', $proportion_width = null, $proportion_height = '0.25')
    {
        if ($image instanceof Image) {
            $watermark = $image;
        } else {
            $watermark = Image::fromFile($image);
        }
        
        $w_height = $watermark->image->getImageHeight();
        $w_width = $watermark->image->getImageWidth();
        $width = $this->image->getImageWidth();
        $height = $this->image->getImageHeight();
        
        // If the watermark is too wide, then resize the watermark
        if ($proportion_width && ($width * $proportion_width < $w_width)) {
            $new_max_width = (int) $width * $proportion_width;
            $new_max_height = (int) $w_height * ($new_max_width / $w_width);
            $watermark->resize($new_max_width, $new_max_height);
            $w_width = $watermark->image->getImageWidth();
            $w_height = $watermark->image->getImageHeight(); // Get the new height/width from the resized image
        }
        
        // If the watermark is too tall (even after above), then resize the watermark
        if ($proportion_height && ($height * $proportion_height < $w_height)) {
            $new_max_height = (int) $height * $proportion_height;
            $new_max_width = (int) $w_width * ($new_max_height / $w_height);
            
            $watermark->resize($new_max_width, $new_max_height);
            $w_width = $watermark->image->getImageWidth();
            $w_height = $watermark->image->getImageHeight(); // Get the new height/width from the resized image
        }
        
        list($x, $y) = self::calculateCompass(
            $width,
            $height,
            $w_width,
            $w_height,
            $compass
        );
        
        $this->image->compositeImage($watermark->image, imagick::COMPOSITE_OVER, $x, $y);
        return $this;
    }

    public function watermarkText($text, $compass = 'C')
    {
        // Get max size of watermark to generate.
        $x = $this->image->getImageWidth();
        $y = $this->image->getImageHeight();
        
        // Make watermark image
        $watermark = new \Imagick();
        $watermark->setFont(self::$config['font']);
        $watermark->setBackgroundColor('transparent');
        $watermark->newPseudoImage(
            $x - (self::$config['margin']),
            $y - (self::$config['margin']),
            "label:" . $text
        );
        $watermark->trimImage(2);
        $watermark->negateImage(1, imagick::CHANNEL_RED | imagick::CHANNEL_GREEN | imagick::CHANNEL_BLUE);
        $watermark->levelImage(65536 * (1 - (1 / self::$config['opacity'])), 1, (65536 * 1), imagick::CHANNEL_ALPHA);
        
        // Calculate x / y
        $tx = $watermark->getImageWidth();
        $ty = $watermark->getImageHeight();
        
        switch(strtoupper($compass)) {
            case 'N':
                $text_y = 0;
                break;
            
            case 'S':
                $text_y = $y - $ty;
                $text_x = intval(($x - $tx) / 2); // Center text
                break;
            
            default:
                Log::warning('Unknown text watermark compass direction ('.$compass.') - C used');
                // no break - default to center
            case 'NW-SE':
            case 'SW-NE':
            case 'C':
                $text_y = intval(($y - $ty) / 2); // Center text
                $text_x = intval(($x - $tx) / 2); // Center text
                break;            
        }
        
        $this->image->compositeImage($watermark, imagick::COMPOSITE_OVER, $text_x, $text_y);
        
        // Free resources for watermark image.
        $watermark->destroy();
        unset($watermark);
        
        return $this;
    }

    public function addText($text, $x, $y, $fontsize, $colour = 'black')
    {
        $draw = new \ImagickDraw();
        $draw->setFillColor($colour);
        $draw->setFont(self::$config['font']);
        $draw->setFontSize($fontsize);
        
        $this->image->annotateImage($draw, $x, $y, 0, $text);
        return $this;
    }

    public function addTextBox($text, $x1, $y1, $x2, $y2)
    {
        $textbox = new \Imagick();
        $textbox->setFont(self::$config['font']);
        $textbox->setBackgroundColor('transparent');
        $textbox->setGravity(imagick::GRAVITY_CENTER);
        
        // 'label' sticks the string on one line, whereas 'caption' will make a paragraph.
        // Unfortunately, will happily split two words onto two lines which looks a bit silly
        // if ($text has two words) then...
        if (count(explode(" ", (trim($text)))) == 2) {
            $method = 'label';
        } else {
            $method = 'caption';
        }
        
        $textbox->newPseudoImage(
            $x2 - $x1,
            $y2 - $y1,
            $method . ":" . $text
        );
        
        $this->image->compositeImage($textbox, imagick::COMPOSITE_OVER, $x1, $y1);
        return $this;
    }

    public function autobalance($quality = 2)
    {
        $colorspace = array(imagick::CHANNEL_RED, imagick::CHANNEL_GREEN, imagick::CHANNEL_BLUE);
        $quantum = 65536;
        $boundary = 2.3;
        
        switch ($quality) {
            case 0:
                return $this;
            
            case 1:
                $this->image->modulateImage(95, 105, 100);
                return $this;
            
            default:
            case 2:
                $boundary = 2.6;
                
                $this->image->setImageColorspace(imagick::COLORSPACE_RGB);
                $stats = $this->image->getImageChannelStatistics();
                
                // Calculate global mean and std dev
                foreach ($colorspace as $cs) {
                    $stats = $this->image->getImageChannelMean($cs);
                    $mean += $stats['mean'] / 3;
                    $stdDev += $stats['standardDeviation'] / 3;
                }
                
                // Calculate max and min for image
                $max = $mean + ($boundary * $stdDev);
                $max = ($max > $quantum)?$quantum:$max;
                
                $min = $mean - ($boundary * $stdDev);
                $min = ($min < 0)?0:$min;
                
                foreach ($colorspace as $cs) {
                    $this->image->levelImage($min, 1, $max, $cs);
                }
                
                return $this;
            
            case 3:
                $boundary = 2;
                $this->image->setImageColorspace(imagick::COLORSPACE_RGB);
                $stats = $this->image->getImageChannelStatistics();
                
                // Calculate global mean and std dev
                foreach ($colorspace as $cs) {
                    $stats = $this->image->getImageChannelMean($cs);
                    $mean += $stats['mean'] / 3;
                    $stdDev += $stats['standardDeviation'] / 3;
                }
                
                // Calculate max and min for image
                $max = $mean + ($boundary * $stdDev);
                $max = ($max > $quantum)?$quantum:$max;
                
                $min = $mean - ($boundary * $stdDev);
                $min = ($min < 0)?0:$min;
                
                foreach ($colorspace as $cs) {
                    $this->image->levelImage($min, 1, $max, $cs);
                }
                
                $this->image->modulateImage(97, 100, 100);
                return $this;
        }
    }

    protected static function calculateCompass($fullx, $fully, $partx, $party, $compass)
    {
        switch($compass)
        {
            case 'NW':
                $x = 0;
                $y = 0;
            break;
            
            case 'N':
                $x = intval(($fullx / 2) - ($partx / 2));
                $y = 0;
            break;
            
            case 'NE':
                $x = $fullx - $partx;
                $y = 0;
            break;
            
            case 'W':
                $x = 0;
                $y = intval(($fully / 2) - ($party / 2));
            break;
            
            case 'C':
                $x = intval(($fullx / 2) - ($partx / 2));
                $y = intval(($fully / 2) - ($party / 2));
            break;
            
            case 'E':
                $x = $fullx - $partx;
                $y = intval(($fully / 2) - ($party / 2));
            break;
            
            case 'SW':
                $x = 0;
                $y = $fully - $party;
            break;
            
            case 'S':
                $x = intval(($fullx / 2) - ($partx / 2));
                $y = $fully - $party;
            break;
            
            default:
                Log::warning('Unknown compass direction ('.$compass.') - SE used' );
            case 'SE':
                $x = $fullx - $partx;
                $y = $fully - $party;
            break;
        }
        return array($x, $y);
    }
}
