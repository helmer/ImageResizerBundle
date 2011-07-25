<?php

namespace Adenclassifieds\ImageResizerBundle\Image;

use Doctrine\Common\Cache\AbstractCache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Image Loader.
 * Handles the resource loading.
 *
 * If the resource is located on the filesystem, load it directly.
 *
 * If the resource is an url (distant file) cache it using the configured cache service
 * for subsequent calls
 *
 * @author David Stendardi <david.stendardi@adenclassifieds.com>
 */
class Loader
{
    /**
     * An imagick fresh instance
     *
     * @var Imagick
     */
    protected $imagick;

    /**
     * The cache service used to limit number of http fetchs
     *
     * @var AbstractCache
     */
    protected $cache;

    /**
     * Where to find the resources (path to the filer mount)
     *
     * @var string
     */
    protected $basePath;

    /**
     * Path to default image in case load fails
     *
     * @var string
     */
    protected $defaultImage;

    /**
     * @param Imagick image
     * @param Cache cache
     * @param string base path
     */
    public function __construct(\Imagick $image, AbstractCache $cache, $basePath, $defaultImage)
    {
        $this->image = $image;
        $this->cache = $cache;
        $this->basePath = $basePath;
        $this->defaultImage = $defaultImage;
    }


    /**
     * Load a resource (external or local)
     *
     * @param string image url or path
     * @return Imagick instance
     */
    public function load($resource)
    {
        try {
            if (0 === strpos($resource, 'http')) {
                $content = $this->loadExternalImage($resource);
                $this->image->readImageBlob($content);
            } else {
                $fullPath = $this->basePath . '/' . $resource;
                $this->image->readImage($fullPath);
            }
        } catch (\Exception $e) {
            if (0 === strlen($this->defaultImage)) {
                throw $e;
            }
            $this->image->readImage($this->defaultImage);
        }

        return $this->image;
    }

    /**
     * Loads image blob from url, using curl
     *
     * @throws Exception
     */
    protected function loadExternalImage($resource)
    {
        if ($content = $this->cache->fetch($resource)) {
            return $content;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $resource);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        $content = curl_exec($curl);
        curl_close($curl);

        if (!$content) {
            throw new NotFoundHttpException();
        }

        $this->cache->save($resource, $content);

        return $content;
    }
}