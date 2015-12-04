<?php
 
/**
 * extended_image/code/SecureImage.php
 * 
 * SecureImage allows to chain its modification methods.
 * Also the resampled Filenames are saved in md5.
 * 
 */
 
class SecureImage extends Image implements Flushable {
		
	/**
	 * @var array All attributes on the form field (not the field holder).
	 * Partially determined based on other instance properties, please use {@link getAttributes()}.
	 */
	protected $attributes = array();

	/**
	 * @config
	 * @var bool Regenerates images if set to true. This is set by {@link flush()}
	 */
	private static $flush = false;

	/**
	 * Triggered early in the request when someone requests a flush.
	 */
	public static function flush() {
		self::$flush = true;
	}

	/**
	 * Set an HTML attribute on the field element, mostly an <input> tag.
	 * 
	 * Some attributes are best set through more specialized methods, to avoid interfering with built-in behaviour:
	 * - 'class': {@link addExtraClass()}
	 * - 'title': {@link setDescription()}
	 * - 'value': {@link setValue}
	 * - 'name': {@link setName}
	 * 
	 * CAUTION Doesn't work on most fields which are composed of more than one HTML form field:
	 * AjaxUniqueTextField, CheckboxSetField, ComplexTableField, CompositeField, ConfirmedPasswordField,
	 * CountryDropdownField, CreditCardField, CurrencyField, DateField, DatetimeField, FieldGroup, GridField,
	 * HtmlEditorField, ImageField, ImageFormAction, InlineFormAction, ListBoxField, etc.
	 * 
	 * @param string
	 * @param string
	 */
	public function setAttribute($name, $value) {
		$this->attributes[$name] = $value;
		return $this;
	}

	/**
	 * Get an HTML attribute defined by the field, or added through {@link setAttribute()}.
	 * Caution: Doesn't work on all fields, see {@link setAttribute()}.
	 * 
	 * @return string
	 */
	public function getAttribute($name) {
		$attrs = $this->getAttributes();
		return @$attrs[$name];
	}
	
	/**
	 * @return array
	 */
	public function getAttributes() {
		return $this->attributes;
	}

	/**
	 * @param Array Custom attributes to process. Falls back to {@link getAttributes()}.
	 * If at least one argument is passed as a string, all arguments act as excludes by name.
	 * @return string HTML attributes, ready for insertion into an HTML tag
	 */
	public function getAttributesHTML($attrs = null) {
		$exclude = (is_string($attrs)) ? func_get_args() : null;

		if(!$attrs || is_string($attrs)) $attrs = $this->getAttributes();

		// Remove empty
		$attrs = array_filter((array)$attrs, function($v) {
			return ($v || $v === 0 || $v === '0');
		}); 

		// Remove excluded
		if($exclude) $attrs = array_diff_key($attrs, array_flip($exclude));

		// Create markkup
		$parts = array();
		foreach($attrs as $name => $value) {
			$parts[] = ($value === true) ? "{$name}=\"{$name}\"" : "{$name}=\"" . Convert::raw2att($value) . "\"";
		}

		return implode(' ', $parts);
	}
	
	/**
	 * Return an XHTML img tag for this Image,
	 * or NULL if the image file doesn't exist on the filesystem.
	 * 
	 * @return string
	 */
	public function getTag() {
		if($this->exists()) {
			$url = $this->getURL();
			$title = ($this->Title) ? $this->Title : $this->Filename;
			if($this->Title) {
				$title = Convert::raw2att($this->Title);
			} else {
				if(preg_match("/([^\/]*)\.[a-zA-Z0-9]{1,6}$/", $title, $matches)) {
					$title = Convert::raw2att($matches[1]);
				}
			}
			return "<img src=\"$url\" alt=\"$title\" ".$this->getAttributesHTML()." />";
		}
	}
    

	/**
	 * Return an image object representing the image in the given format.
	 * This image will be generated using generateFormattedImage().
	 * The generated image is cached, to flush the cache append ?flush=1 to your URL.
	 * @param string $format The name of the format.
	 * @param string $arg1 An argument to pass to the generate function.
	 * @param string $arg2 A second argument to pass to the generate function.
	 * @return SecureImage_Cached
	 */
	public function getFormattedImage($format) {
		$args = func_get_args();

		if($this->exists()) {
			$cacheFile = call_user_func_array(array($this, "cacheFilename"), $args);

			if(!file_exists(Director::baseFolder()."/".$cacheFile) || self::$flush) {
				call_user_func_array(array($this, "generateFormattedImage"), $args);
			}
			
			$cached = new SecureImage_Cached($cacheFile);
			// Pass through the title so the templates can use it
			$cached->Title = $this->Title;
            $cached->ID = $this->ID;
            $cached->ParentID = $this->ParentID;
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
	public function cacheFilename($format) {
		$args = func_get_args();
		array_shift($args);
		$folder = $this->ParentID ? $this->Parent()->Filename : ASSETS_DIR . "/";
		
		$format = $format.implode('',$args);
                $file = pathinfo($this->Name);
		return $folder . "_resampled/".md5($format."-".$file['filename']).".".$file['extension'];
	}
    
}



/**
 * A resized / processed {@link Image} object.
 * When Image object are processed or resized, a suitable Image_Cached object is returned, pointing to the
 * cached copy of the processed image.
 * @package sapphire
 * @subpackage filesystem
 */
class SecureImage_Cached extends SecureImage {
	/**
	 * Create a new cached image.
	 * @param string $filename The filename of the image.
	 * @param boolean $isSingleton This this to true if this is a singleton() object, a stub for calling methods.  Singletons
	 * don't have their defaults set.
	 */
	public function __construct($filename = null, $isSingleton = false) {
		parent::__construct(array(), $isSingleton);
		$this->Filename = $filename;
	}

	/**
	 * Override the parent's exists method becuase the ID is explicitly set to -1 on a cached image we can't use the
	 * default check
	 *
	 * @return bool Whether the cached image exists
	 */
	public function exists() {
		return file_exists($this->getFullPath());
	}
	
	public function getRelativePath() {
		return $this->getField('Filename');
	}
	
	// Prevent this from doing anything
	public function requireTable() {
		return false;
	}	
	
	/**
	 * Prevent writing the cached image to the database
	 *
	 * @throws Exception
	 */
	public function write($showDebug = false, $forceInsert = false, $forceWrite = false, $writeComponents = false) {
		throw new Exception("{$this->ClassName} can not be written back to the database.");
	}
}