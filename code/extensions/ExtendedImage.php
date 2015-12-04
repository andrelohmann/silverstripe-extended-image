<?php
 
/**
 * extended-image/code/ExtendedImage.php
 * 
 * Can Return the Image as Base64 String
 * Can set Backgroundcolor on padded resize
 * 
 */
 
class ExtendedImage extends DataExtension {

        /**
         * Return an XHTML img tag for this Image.
         * @return string
         */
        public function getBase64Tag() {
                if($this->owner->exists()) {
                        $url = $this->owner->getBase64Source();
                        $title = ($this->owner->Title) ? $this->owner->Title : $this->owner->Filename;
                        if($this->owner->Title) {
                                $title = Convert::raw2att($this->owner->Title);
                        } else {
                                if(preg_match("/([^\/]*)\.[a-zA-Z0-9]{1,6}$/", $title, $matches)) $title = Convert::raw2att($matches[1]);
                        }
                        return "<img src=\"$url\" alt=\"$title\" />";
                }
        }

        /**
         * retrun the Base64 Notation of the Image
         */
        public function getBase64Source(){
                $Fullpath = $this->owner->getFullPath();

                $cache = SS_Cache::factory('Base64Image');
                $cachekey = md5($Fullpath);
                if(!($Base64Image = $cache->load($cachekey))){
                    $Base64Image = base64_encode(file_get_contents($Fullpath));
                    $cache->save($Base64Image);
                }

                $type = strtolower($this->owner->getExtension());

                return "data:image/".$type.";base64,".$Base64Image;
        }
		
		public function DetectFace(){
			$detector = new svay\FaceDetector();
			$detector->faceDetect($this->owner->getFullPath());
			return $detector->getFace();
		}
		
		public function DetectedFace(){
			if($this->owner->exists()) {
				$cacheFile = $this->owner->cacheDetectedFaceFilename();

				if(!file_exists(Director::baseFolder()."/".$cacheFile) || isset($_GET['flush'])) {
					$this->owner->generateDetectedFaceImage();
				}
				
				if(get_class($this->owner) == 'SecureImage') $cached = new SecureImage_Cached($cacheFile);
				else $cached = new Image_Cached($cacheFile);

				// Pass through the title so the templates can use it
				$cached->owner->Title = $this->owner->Title;
				$cached->owner->ID = $this->owner->ID;
				$cached->owner->ParentID = $this->owner->ParentID;
				return $cached;
			}
		}
	
		/**
		 * Return the filename for the cached image.
		 * @return string
		 */
		public function cacheDetectedFaceFilename() {
			$folder = $this->owner->ParentID ? $this->owner->Parent()->Filename : ASSETS_DIR . "/";

			$format = 'DetectedFace';

			if(get_class($this->owner) == 'SecureImage'){
				$file = pathinfo($this->owner->Name);
				return $folder . "_resampled/".md5($format."-".$file['filename']).".".$file['extension'];
			}else{
				return $folder . "_resampled/".$format."-".$this->owner->Name;
			}
		}
	
		/**
		 * Generate an image on the specified format. It will save the image
		 * at the location specified by cacheFilename(). The image will be generated
		 * using the specific 'generate' method for the specified format.
		 */
		public function generateDetectedFaceImage() {
			$cacheFile = $this->owner->cacheDetectedFaceFilename();

			$backend = Injector::inst()->createWithArgs(Image::get_backend(), array(
				Director::baseFolder()."/" . $this->owner->Filename
			));

			if($backend->hasImageResource()){
				$backend = $backend->detectedFaceImage($this->DetectFace());
				
				if($backend){
					$backend->writeTo(Director::baseFolder()."/" . $cacheFile);
				}
			}
		}
	
		/**
		 * Return an XHTML img tag for this Image,
		 * or NULL if the image file doesn't exist on the filesystem.
		 * 
		 * @return string
		 */
		public function TagWithClass($cssclass) {
			if($this->owner->exists()) {
				$url = $this->owner->getURL();
				$title = ($this->owner->Title) ? $this->owner->Title : $this->owner->Filename;
				if($this->owner->Title) {
					$title = Convert::raw2att($this->owner->Title);
				} else {
					if(preg_match("/([^\/]*)\.[a-zA-Z0-9]{1,6}$/", $title, $matches)) {
						$title = Convert::raw2att($matches[1]);
					}
				}
				return "<img src=\"$url\" alt=\"$title\" class=\"$cssclass\" />";
			}
		}
        
        /**
         * Merge the Image onto anotherone fitting it with a min padding
         * @param type $padding
         * @param type $backgroundimage
         */
		public function MergeOver($backgroundimage, $padding = 10) {
            return $this->owner->getMergedImage($format = 'over', $padding, $backgroundimage);
		}
        
        /**
         * use the current Image as a background for the merging image
         * @param type $padding
         * @param type $overlayimage
         */
		public function MergeUnder($overlayimage, $padding = 10) {
            return $this->owner->getMergedImage($format = 'under', $padding, $overlayimage);
		}
        
        /**
         * Return an image object representing the merged image.
         * @param type $padding
         * @param type $mergeimage
		 * @return Image_Cached
         */
		public function getMergedImage($format, $padding, $mergeimage) {
            if($this->owner->exists() && Director::fileExists($mergeimage)) {
                $cacheFile = $this->owner->cacheMergedFilename($format, $padding, $mergeimage);
                
                if(!file_exists(Director::baseFolder()."/".$cacheFile) || isset($_GET['flush'])) {
                    // merge the current image over the given Merging Image
                    if($format == 'over') $this->owner->generateMergedImage($padding, $mergeimage, $this->owner->getFullPath(), $cacheFile);
                    // merge the current image over the given Merging Image
                    else $this->owner->generateMergedImage($padding, $this->owner->getFullPath(), $mergeimage, $cacheFile);
				}
                
                if(get_class($this->owner) == 'SecureImage') $cached = new SecureImage_Cached($cacheFile);
                else $cached = new Image_Cached($cacheFile);
                
				// Pass through the title so the templates can use it
				$cached->owner->Title = $this->owner->Title;
                $cached->owner->ID = $this->owner->ID;
                $cached->owner->ParentID = $this->owner->ParentID;
				return $cached;
            }
		}
	
		/**
		 * Return the filename for the cached image, given it's format name and arguments.
		 * @param string $format The format name.
		 * @param string $padding
		 * @param string $mergedimage (<- path, md5() this)
		 * @return string
		 */
		public function cacheMergedFilename($format, $padding, $mergedimage) {
				$folder = $this->owner->ParentID ? $this->owner->Parent()->Filename : ASSETS_DIR . "/";

				$format = 'merged-'.$format.$padding.md5($mergedimage);

				// Ermitteln, ob eine der Dateien ein png ist
				$image_pathinfo = pathinfo($this->owner->Filename);
				$merge_pathinfo = pathinfo($mergedimage);

				if(get_class($this->owner) == 'SecureImage'){
					$file = pathinfo($this->owner->Name);
					if(strtolower($image_pathinfo['extension']) == 'png' || strtolower($merge_pathinfo['extension']) == 'png'){
						$mergedName = $folder . "_resampled/".md5($format."-".$file['filename']).".".$file['extension'];
						$mergedName = pathinfo($mergedName);
						return $mergedName['dirname'].'/'.$mergedName['filename'].'_'.$image_pathinfo['extension'].'.png';
					}else{
						return $folder . "_resampled/".md5($format."-".$file['filename']).".".$file['extension'];
					}
				}else{
					if(strtolower($image_pathinfo['extension']) == 'png' || strtolower($merge_pathinfo['extension']) == 'png'){
						$mergedName = $folder . "_resampled/$format-" . $this->owner->Name;
						$mergedName = pathinfo($mergedName);
						return $mergedName['dirname'].'/'.$mergedName['filename'].'_'.$image_pathinfo['extension'].'.png';
					}else{
						return $folder . "_resampled/$format-" . $this->owner->Name;
					}
				}
		}
	
		/**
		 * Generate an image on the specified format. It will save the image
		 * at the location specified by cacheFilename(). The image will be generated
		 * using the specific 'generate' method for the specified format.
		 * @param string $format Name of the format to generate.
		 * @param string $arg1 Argument to pass to the generate method.
		 * @param string $arg2 A second argument to pass to the generate method.
		 * @param string $background Background-Color of the resized Image
		 */
		public function generateMergedImage($padding, $bgImagePath, $overlayImagePath, $cacheFile){

				$bgImage = Injector::inst()->createWithArgs(Image::get_backend(), array(
					$bgImagePath
				));

				$ovImage = Injector::inst()->createWithArgs(Image::get_backend(), array(
					$overlayImagePath
				));

				$frontImage = $this->owner->generateFit($ovImage, ($bgImage->getWidth() - (2*$padding)), ($bgImage->getHeight() - (2*$padding)));
				$backend = $bgImage->merge($frontImage);
				if($backend){
					$backend->writeTo(Director::baseFolder()."/" . $cacheFile);
				}
		}
        
        /**
         * Resize this Image by both width and height with transparent Background, using padded resize. Use in templates with $transparentPad.
         * @param type $widthheight separated by ":"
         * @param type $background
         */
        public function TransparentPad($width, $height){
			return (($this->owner->getWidth() == $width) &&  ($this->owner->getHeight() == $height)) 
			? $this->owner
			: $this->owner->getTransparentFormattedImage('TransparentPad', $width, $height);
		}

		/**
		 * Return an image object representing the image in the given format.
		 * This image will be generated using generateFormattedImage().
		 * The generated image is cached, to flush the cache append ?flush=1 to your URL.
		 * @param string $format The name of the format.
		 * @param string $arg1 An argument to pass to the generate function.
		 * @param string $arg2 A second argument to pass to the generate function.
		 * @param string $background Background-Color of the resized Image
		 * @return Image_Cached
		 */
		public function getTransparentFormattedImage($format, $arg1 = null, $arg2 = null) {
			if($this->owner->exists()) {
				$cacheFile = $this->owner->cacheTransparentFilename($format, $arg1, $arg2);

				if(!file_exists(Director::baseFolder()."/".$cacheFile) || isset($_GET['flush'])) {
					$this->owner->generateTransparentFormattedImage($format, $arg1, $arg2);
				}
				
				if(get_class($this->owner) == 'SecureImage') $cached = new SecureImage_Cached($cacheFile);
				else $cached = new Image_Cached($cacheFile);

				// Pass through the title so the templates can use it
				$cached->owner->Title = $this->owner->Title;
				$cached->owner->ID = $this->owner->ID;
				$cached->owner->ParentID = $this->owner->ParentID;
				return $cached;
			}
		}
	
		/**
		 * Return the filename for the cached image, given it's format name and arguments.
		 * @param string $format The format name.
		 * @param string $arg1 The first argument passed to the generate function.
		 * @param string $arg2 The second argument passed to the generate function.
		 * @return string
		 */
		public function cacheTransparentFilename($format, $arg1 = null, $arg2 = null) {
			$folder = $this->owner->ParentID ? $this->owner->Parent()->Filename : ASSETS_DIR . "/";

			$format = $format.$arg1.$arg2;

			if(get_class($this->owner) == 'SecureImage'){
				$file = pathinfo($this->owner->Name);
				return $folder . "_resampled/".md5($format."-".$file['filename']).".".$file['extension'];
			}else{
				return $folder . "_resampled/".$format."-".$this->owner->Name;
			}
		}
	
		/**
		 * Generate an image on the specified format. It will save the image
		 * at the location specified by cacheFilename(). The image will be generated
		 * using the specific 'generate' method for the specified format.
		 * @param string $format Name of the format to generate.
		 * @param string $arg1 Argument to pass to the generate method.
		 * @param string $arg2 A second argument to pass to the generate method.
		 */
		public function generateTransparentFormattedImage($format, $arg1 = null, $arg2 = null) {
			$cacheFile = $this->owner->cacheTransparentFilename($format, $arg1, $arg2);

			$backend = Injector::inst()->createWithArgs(Image::get_backend(), array(
				Director::baseFolder()."/" . $this->owner->Filename
			));

			if($backend->hasImageResource()){
				$generateFunc = "generate$format";		
				if($this->owner->hasMethod($generateFunc)){
					$backend = $this->owner->$generateFunc($backend, $arg1, $arg2);
					if($backend){
						$backend->writeTo(Director::baseFolder()."/" . $cacheFile);
					}

				} else {
					USER_ERROR("Image::generateTransparentFormattedImage - Image $format function not found.",E_USER_WARNING);
				}
			}
		}
	
		/**
		 * Resize this Image by both width and height, using padded resize. Use in templates with $SetSize.
		 * @return GD
		 */
		public function generateTransparentPad(Image_Backend $backend, $width, $height) {
			if(!$backend){
				user_error("Image::generateTransparentFormattedImage - generateTransparentPad is being called by legacy code"
					. " or Image::\$backend is not set.",E_USER_WARNING);
			}else{
				return $backend->transparentPaddedResize($width, $height);
			}
		}
        
        /**
         * Blur the Image
         */
        public function Blur($intensity = 'normal'){
            return $this->owner->getBluredImage($intensity);
        }
        
        /**
         * Return an image object representing the blured image.
         * @return Image_Cached
         */
        public function getBluredImage($intensity){
            if($this->owner->exists()) {
                $cacheFile = $this->owner->cacheBluredFilename($intensity);
                
                if(!file_exists(Director::baseFolder()."/".$cacheFile) || isset($_GET['flush'])) {
                    // blur the current image
                    $this->owner->generateBluredImage($intensity);
				}
                
                if(get_class($this->owner) == 'SecureImage') $cached = new SecureImage_Cached($cacheFile);
                else $cached = new Image_Cached($cacheFile);
                
				// Pass through the title so the templates can use it
				$cached->owner->Title = $this->owner->Title;
                $cached->owner->ID = $this->owner->ID;
                $cached->owner->ParentID = $this->owner->ParentID;
				return $cached;
            }
        }
	
		/**
		 * Return the filename for the cached image.
		 * @return string
		 */
		public function cacheBluredFilename($intensity) {
			$folder = $this->owner->ParentID ? $this->owner->Parent()->Filename : ASSETS_DIR . "/";
			
			$format = 'blured-'.$intensity;
			
			if(get_class($this->owner) == 'SecureImage'){
				$file = pathinfo($this->owner->Name);
				return $folder . "_resampled/".md5($format."-".$file['filename']).".".$file['extension'];
			}else{
				return $folder . "_resampled/".$format."-".$this->owner->Name;
			}
		}
        
		/**
		 * Generate an image on the specified format. It will save the image
		 * at the location specified by cacheFilename(). The image will be generated
		 * using the specific 'generate' method for the specified format.
		 */
		public function generateBluredImage($intensity){
				$cacheFile = $this->owner->cacheBluredFilename($intensity);

				$backend = Injector::inst()->createWithArgs(Image::get_backend(), array(
					Director::baseFolder()."/" . $this->owner->Filename
				));

				$backend = $backend->blur($intensity);
				if($backend){
					$backend->writeTo(Director::baseFolder()."/" . $cacheFile);
				}
		}
}