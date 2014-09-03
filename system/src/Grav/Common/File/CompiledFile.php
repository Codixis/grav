<?php
namespace Grav\Common\File;

use Grav\Component\Filesystem\File\Php;

trait CompiledFile
{
    /**
     * Get/set parsed file contents.
     *
     * @param mixed $var
     * @return string
     */
    public function content($var = null)
    {
        // If nothing has been loaded, attempt to get pre-compiled version of the file first.
        if ($var === null && $this->raw === null && $this->content === null) {
            $key = md5($this->filename);
            $file = Php::instance(CACHE_DIR . "/compiled/files/{$key}{$this->extension}.php");
            $modified = $this->modified();
            $class = get_class($this);

            if ($file->exists()) {
                $cache = $file->exists() ? $file->content() : null;
            } else {
                $cache = null;
            }


            // Load real file if cache isn't up to date (or is invalid).
            if (
                !is_array($cache)
                || $cache['modified'] != $modified
                || $cache['filename'] != $this->filename
                || $cache['@class'] != $class
            ) {
                // Attempt to lock the file for writing.
                $file->lock(false);

                // Decode RAW file into compiled array.
                $data = $this->decode($this->raw());
                $cache = [
                    '@class' => $class,
                    'filename' => $this->filename,
                    'modified' => $modified,
                    'data' => $data
                ];

                // If compiled file wasn't already locked by another process, save it.
                if ($file->locked() !== false) {
                    $file->save($cache);
                    $file->unlock();
                }
            }

            $this->content = $cache['data'];
        }

        return parent::content($var);
    }
}