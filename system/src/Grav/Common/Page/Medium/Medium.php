<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\GravTrait;
use Grav\Common\Data\Data;

/**
 * The Medium is a general class for multimedia objects in Grav pages, specific implementations will derive from
 *
 * @author Grav
 * @license MIT
 *
 */
class Medium extends Data implements RenderableInterface
{
    use GravTrait;
    use ParsedownHtmlTrait;

    /**
     * @var string
     */
    protected $mode = 'source';

    /**
     * @var Medium
     */
    protected $_thumbnail = null;

    /**
     * @var array
     */
    protected $thumbnailTypes = [ 'page', 'default' ];

    /**
     * @var Medium[]
     */
    protected $alternatives = [];

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $styleAttributes = [];

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint $blueprint
     */
    public function __construct($items = [], Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        $this->def('mime', 'application/octet-stream');
        $this->reset();
    }

    /**
     * Add meta file for the medium.
     *
     * @param $filepath
     */
    public function addMetaFile($filepath)
    {
        $this->merge(CompiledYamlFile::instance($filepath)->content());
    }

    /**
     * Add alternative Medium to this Medium.
     *
     * @param $ratio
     * @param Medium $alternative
     */
    public function addAlternative($ratio, Medium $alternative)
    {
        if (!is_numeric($ratio) || $ratio === 0) {
            return;
        }

        $alternative->set('ratio', $ratio);
        $this->alternatives[(float) $ratio] = $alternative;
    }

    /**
     * Return string representation of the object (html).
     *
     * @return string
     */
    public function __toString()
    {
        return $this->html();
    }

    /**
     * Return PATH to file.
     *
     * @param bool $reset
     * @return  string path to file
     */
    public function path($reset = true)
    {
        if ($reset) $this->reset();

        return $this->get('filepath');
    }

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @return string
     */
    public function url($reset = true)
    {
        $output = preg_replace('|^' . GRAV_ROOT . '|', '', $this->get('filepath'));

        if ($reset) $this->reset();

        return self::$grav['base_url'] . $output . $this->urlHash();
    }

    /**
     * Get/set hash for the file's url
     *
     * @param  string  $hash
     * @param  boolean $withHash
     * @return string
     */
    public function urlHash($hash = null, $withHash = true)
    {
        if ($hash) {
            $this->set('urlHash', ltrim($hash, '#'));
        }

        $hash = $this->get('urlHash', '');

        if ($withHash && !empty($hash)) {
            return '#' . $hash;
        } else {
            return $hash;
        }
    }

    /**
     * Get an element (is array) that can be rendered by the Parsedown engine
     *
     * @param  string  $title
     * @param  string  $alt
     * @param  string  $class
     * @param  boolean $reset
     * @return array
     */
    public function parsedownElement($title = null, $alt = null, $class = null, $reset = true)
    {
        $element;

        $attributes = $this->attributes;

        $style = '';
        foreach ($this->styleAttributes as $key => $value) {
            $style .= $key . ': ' . $value . ';';
        }
        $attributes['style'] = $style;

        !empty($title) && empty($attributes['title']) && $attributes['title'] = $title;
        !empty($alt) && empty($attributes['alt']) && $attributes['alt'] = $alt;
        !empty($class) && empty($attributes['class']) && $attributes['class'] = $class;

        switch ($this->mode) {
            case 'text':
                $element = $this->textParsedownElement($attributes, false);
                break;
            case 'thumbnail':
                $element = $this->getThumbnail()->sourceParsedownElement($attributes, false);
                break;
            case 'source':
                $element = $this->sourceParsedownElement($attributes, false);
                break;
        }

        if ($reset) {
            $this->reset();
        }

        $this->display('source');

        return $element;
    }

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  boolean $reset
     * @return array
     */
    protected function sourceParsedownElement(array $attributes, $reset = true)
    {
        return $this->textParsedownElement($attributes, $reset);
    }

    /**
     * Parsedown element for text display mode
     *
     * @param  array $attributes
     * @param  boolean $reset
     * @return array
     */
    protected function textParsedownElement(array $attributes, $reset = true)
    {
        $text = empty($attributes['title']) ? empty($attributes['alt']) ? $this->get('filename') : $attributes['alt'] : $attributes['title'];

        $element = [
            'name' => 'p',
            'attributes' => $attributes,
            'text' => $text
        ];

        if ($reset) {
            $this->reset();
        }

        return $element;
    }

    /**
     * Reset medium.
     *
     * @return $this
     */
    public function reset()
    {
        $this->attributes = [];
        return $this;
    }

    /**
     * Switch display mode.
     *
     * @param string $mode
     *
     * @return $this
     */
    public function display($mode = 'source')
    {
        if ($this->mode === $mode)
            return $this;

        $this->mode = $mode;

        return $mode === 'thumbnail' ? $this->getThumbnail()->reset() : $this->reset();
    }

    /**
     * Switch thumbnail.
     *
     * @param string $type
     *
     * @return $this
     */
    public function thumbnail($type = 'auto')
    {
        if ($type !== 'auto' && !in_array($type, $this->thumbnailTypes))
            return $this;

        if ($this->thumbnailType !== $type) {
            $this->_thumbnail = null;
        }

        $this->thumbnailType = $type;

        return $this;
    }

    /**
     * Turn the current Medium into a Link
     *
     * @param  boolean $reset
     * @param  array  $attributes
     * @return Link
     */
    public function link($reset = true, array $attributes = [])
    {
        if ($this->mode !== 'source') {
            $this->display('source');
        }

        foreach ($this->attributes as $key => $value) {
            empty($attributes['data-' . $key]) && $attributes['data-' . $key] = $value;
        }

        empty($attributes['href']) && $attributes['href'] = $this->url();

        return new Link($attributes, $this);
    }

    /**
     * Turn the current Medium inta a Link with lightbox enabled
     *
     * @param  int  $width
     * @param  int  $height
     * @param  boolean $reset
     * @return Link
     */
    public function lightbox($width = null, $height = null, $reset = true)
    {
        $attributes = ['rel' => 'lightbox'];

        if ($width && $height) {
            $attributes['data-width'] = $width;
            $attributes['data-height'] = $height;
        }

        return $this->link($reset, $attributes);
    }

    /**
     * Allow any action to be called on this medium from twig or markdown
     *
     * @param string $method
     * @param mixed $args
     * @return $this
     */
    public function __call($method, $args)
    {
        return $this;
    }

    /**
     * Get the thumbnail Medium object
     *
     * @return ThumbnailImageMedium
     */
    protected function getThumbnail()
    {
        if (!$this->_thumbnail) {
            $types = $this->thumbnailTypes;

            if ($this->thumbnailType !== 'auto') {
                array_unshift($types, $this->thumbnailType);
            }

            foreach ($types as $type) {
                $thumb = $this->get('thumbnails.' . $type, false);

                if ($thumb) {
                    $thumb = $thumb instanceof ThumbnailMedium ? $thumb : MediumFactory::fromFile($thumb, ['type' => 'thumbnail']);
                    $thumb->parent = $this;
                }

                if ($thumb) {
                    $this->_thumbnail = $thumb;
                    break;
                }
            }
        }

        return $this->_thumbnail;
    }
}
