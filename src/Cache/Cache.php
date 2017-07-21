<?php

namespace Cache;

/**
 * Class Cache
 * @package Cache
 */
class Cache
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var int
     */
    protected $extendedPath;

    /**
     * Cache constructor.
     * @param string $name
     * @param string $directory
     * @param string $extension
     */
    public function __construct($name, $directory, $extension = ".cache")
    {
        $this->name = $name;
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR) . "/";
        $this->extension = $extension;
        $this->extendedPath = false;
    }

    /**
     * Check whether data accociated with a key
     *
     * @param string $key
     * @return boolean
     */
    public function isCached($key)
    {
        $cachedData = $this->loadCache();
        if (false != $cachedData) {
            return isset($cachedData[$key]['data']);
        }
    }

    /**
     * @return string
     */
    public function getCacheFile()
    {
        if (true === $this->checkCacheDir()) {
            return sprintf("%s%s%s", $this->getDirectory(), $this->getSafeName(), $this->getExtension());
        }
    }

    /**
     * @param $name
     * @return string
     */
    public static function generateCacheKey($name)
    {
        return sha1($name);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        $path = $this->directory;
        if ($this->isExtendedPath()) {
            $name = $this->getSafeName();
            for ($i = 0; $i < 3; $i++) {
                $path .= substr($name, $i, 2);
                $path .= DIRECTORY_SEPARATOR;
            }
        }
        return $path;
    }

    /**
     * @param string $directory
     */
    public function setDirectory($directory)
    {
        $this->directory = $directory;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param string $extension
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    /**
     * @return bool
     */
    public function isExtendedPath()
    {
        return $this->extendedPath;
    }

    /**
     * @param bool|int $extendedPath
     */
    public function setExtendedPath($extendedPath)
    {
        $this->extendedPath = $extendedPath;
    }

    /**
     * Store data in the cache
     *
     * @param string $key
     * @param mixed $data
     * @param integer [optional] $expiration
     * @return object
     */
    public function store($key, $data, $expiration = 0)
    {

        $storeData = array(
            'time'   => time(),
            'expire' => $expiration,
            'data'   => serialize($data),
        );
        $dataArray = $this->loadCache();
        if (true === is_array($dataArray)) {
            $dataArray[$key] = $storeData;
        } else {
            $dataArray = array($key => $storeData);
        }
        $cacheData = json_encode($dataArray);
        file_put_contents($this->getCacheFile(), $cacheData);
        return $this;
    }

    /**
     * Retrieve cached data by its key
     *
     * @param string $key
     * @param boolean [optional] $timestamp
     * @return string
     */
    public function retrieve($key, $timestamp = false)
    {
        $cachedData = $this->loadCache();
        (false === $timestamp) ? $type = 'data' : $type = 'time';
        if (!isset($cachedData[$key][$type])) return null;
        return unserialize($cachedData[$key][$type]);
    }

    /**
     * Retrieve all cached data
     *
     * @param boolean [optional] $meta
     * @return array
     */
    public function retrieveAll($meta = false)
    {
        if ($meta === false) {
            $results = array();
            $cachedData = $this->loadCache();
            if ($cachedData) {
                foreach ($cachedData as $k => $v) {
                    $results[$k] = unserialize($v['data']);
                }
            }
            return $results;
        } else {
            return $this->loadCache();
        }
    }

    /**
     * Erase cached entry by its key
     *
     * @throws \Exception
     *
     * @param string $key
     * @return object
     */
    public function erase($key)
    {
        $cacheData = $this->loadCache();
        if (true === is_array($cacheData)) {
            if (true === isset($cacheData[$key])) {
                unset($cacheData[$key]);
                $cacheData = json_encode($cacheData);
                file_put_contents($this->getCacheFile(), $cacheData);
            } else {
                throw new \Exception("Error: erase() - Key '{$key}' not found.");
            }
        }
        return $this;
    }

    /**
     * Erase all expired entries
     *
     * @return integer
     */
    public function eraseExpired()
    {
        $cacheData = $this->loadCache();
        if (true === is_array($cacheData)) {
            $counter = 0;
            foreach ($cacheData as $key => $entry) {
                if (true === $this->checkExpired($entry['time'], $entry['expire'])) {
                    unset($cacheData[$key]);
                    $counter++;
                }
            }
            if ($counter > 0) {
                $cacheData = json_encode($cacheData);
                file_put_contents($this->getCacheFile(), $cacheData);
            }
            return $counter;
        }
    }

    /**
     * Erase all cached entries
     *
     * @return object
     */
    public function eraseAll()
    {
        $cacheFile = $this->getCacheFile();
        if (true === file_exists($cacheFile)) {
            $f = fopen($cacheFile, 'w');
            fclose($f);
        }
        return $this;
    }

    /**
     * Check whether a timestamp is still in the duration
     *
     * @param integer $timestamp
     * @param integer $expiration
     * @return boolean
     */
    protected function checkExpired($timestamp, $expiration)
    {
        $result = false;
        if ($expiration !== 0) {
            $timeDiff = time() - $timestamp;
            ($timeDiff > $expiration) ? $result = true : $result = false;
        }
        return $result;
    }

    /**
     * Check if a writable cache directory exists and if not create a new one
     *
     * @return bool
     * @throws \Exception
     */
    protected function checkCacheDir()
    {
        if (!is_dir($this->getDirectory()) && !mkdir($this->getDirectory(), 0775, true)) {
            throw new \Exception('Unable to create cache directory ' . $this->getCachePath());
        } elseif (!is_readable($this->getDirectory()) || !is_writable($this->getDirectory())) {
            if (!chmod($this->getDirectory(), 0775)) {
                throw new \Exception($this->getDirectory() . ' must be readable and writeable');
            }
        }
        return true;
    }

    /**
     * Load appointed cache
     *
     * @return mixed
     */
    protected function loadCache()
    {
        if (true === file_exists($this->getCacheFile())) {
            $file = file_get_contents($this->getCacheFile());
            return json_decode($file, true);
        } else {
            return false;
        }
    }

    /**
     * @return string
     */
    protected function getExtendedPath()
    {
        $path = $this->getDirectory();
        $name = $this->getSafeName();
        for ($i = 0; $i < 3; $i++) {
            $path .= substr($name, $i, 2);
            $path .= DIRECTORY_SEPARATOR;
        }
        return $path;
    }

    /**
     * Clean name from spaces
     *
     * @return mixed
     */
    protected function getSafeName()
    {
        return preg_replace('/[^0-9a-z\.\_\-]/i', '', strtolower($this->getName()));
    }


}