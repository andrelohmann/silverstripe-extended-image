<?php
 
/**
 * extended-image/code/ExtendedGD.php
 * 
 * Can merge Images with Backgroundimages
 * 
 */
 
class ExtendedGDBackend extends DataExtension {
    
    /**
     * Merge two Images together
     */
    public function merge(GDBackend $image){
        
        imagealphablending($this->owner->getImageResource(), false);
		imagesavealpha($this->owner->getImageResource(), true);
        
        imagealphablending($image->getImageResource(), false);
		imagesavealpha($image->getImageResource(), true);
        
        $srcX = 0;
        $srcY = 0;
        $srcW = $image->getWidth();
        $srcH = $image->getHeight();
        $dstX = round(($this->owner->getWidth() - $srcW)/2);
        $dstY = round(($this->owner->getHeight() - $srcH)/2);
        $dstW = $image->getWidth();
        $dstH = $image->getHeight();
        
        imagecopyresampled($this->owner->getImageResource(), $image->getImageResource(), $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
        
        $output = clone $this->owner;
		$output->setImageResource($this->owner->getImageResource());
		return $output;
    }
    
    /**
     * blur the image
     */
    public function blur($intensity) {
        $image = $this->owner->getImageResource();
        
        switch($intensity){
            case 'light':
                for ($x=1; $x<=10; $x++)
                    imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
            break;
            
            case 'strong':
                for ($x=1; $x<=40; $x++)
                    imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
            break;
            
            case 'normal':
            default:
                for ($x=1; $x<=25; $x++)
                    imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
            break;
        }
        
        $output = clone $this->owner;
		$output->setImageResource($image);
		return $output;
    }
    
    public function transparentPaddedResize($width, $height) {
		if(!$this->owner->getImageResource()) return;
		$width = round($width);
		$height = round($height);
		
		// Check that a resize is actually necessary.
		if ($width == $this->owner->getWidth() && $height == $this->owner->getHeight()) {
			return $this->owner;
		}
		
		$newGD = imagecreatetruecolor($width, $height);
		
		// Preserves transparency between images
		imagealphablending($newGD, false);
		imagesavealpha($newGD, true);
                
        $transparent = imagecolorallocatealpha($newGD, 0, 0, 0, 127);
                
		imagefilledrectangle($newGD, 0, 0, $width, $height, $transparent);
        imagealphablending($newGD, true);
		
		$destAR = $width / $height;
		if ($this->owner->getWidth() > 0 && $this->owner->getHeight() > 0) {
			// We can't divide by zero theres something wrong.
			
			$srcAR = $this->owner->getWidth() / $this->owner->getHeight();
		
			// Destination narrower than the source
			if($destAR > $srcAR) {
				$destY = 0;
				$destHeight = $height;
				
				$destWidth = round( $height * $srcAR );
				$destX = round( ($width - $destWidth) / 2 );
			
			// Destination shorter than the source
			} else {
				$destX = 0;
				$destWidth = $width;
				
				$destHeight = round( $width / $srcAR );
				$destY = round( ($height - $destHeight) / 2 );
			}
			
			imagecopyresampled($newGD, $this->owner->getImageResource(), $destX, $destY, 0, 0, $destWidth, $destHeight, $this->owner->getWidth(), $this->owner->getHeight());
		}
		$output = clone $this->owner;
		$output->setImageResource($newGD);
		return $output;
	}
    
    public function detectedFaceImage($face){
		if(!$this->owner->getImageResource()) return;
		
		if ($face == null){
			return $this->owner;
		}
		
		$color = imagecolorallocate($this->owner->getImageResource(), 255, 0, 0); //red
		imagerectangle(
            $this->owner->getImageResource(),
            $face['x'],
            $face['y'],
            $face['x']+$face['w'],
            $face['y']+$face['w'],
            $color
        );
		
		$output = clone $this->owner;
		$output->setImageResource($this->owner->getImageResource());
		return $output;
	}
}