<?php namespace AgelxNash\Evo\QThumb;

use phpThumb;
use EvolutionCMS\Core;

class Make
{
    /** @var Core|null */
    protected $core;
    /** @var phpThumb */
    protected $phpThumb;
    protected $options = array();
    protected $image;
    protected $noFile;
    protected $queue = false;
    protected $allowOptions = array();

    protected $allowExt = array('png', 'gif', 'jpg');

    const DEFAULT_EXT = 'jpg';
    const DEFAULT_QUALITY = '96';
    const NO_IMAGE = 'assets/images/noimage.jpg';
    const CACHE_NOIMAGE_FOLDER = 'assets/images/noimage/';
    const CACHE_IMAGE_FOLDER = 'assets/thumbs/';
    const TABLE = 'images';

    /**
     * @param $core Core
     */
    public function __construct(Core $core)
    {
        $this->core = $core;
    }

    /**
     * @param string $fileName
     * @return array|string
     */
    public function getNoFile($fileName = '')
    {
        $to = $this->makeToFilePath();
        if (!file_exists($to)) {
            $to = $this->createFile(MODX_BASE_PATH . $this->noFile, $to);
        }
        if (file_exists($to)) {
            copy($to, $fileName);
        }

        return $this->clearPath($fileName, false);
    }

    protected function makeToFilePath()
    {
        return MODX_BASE_PATH . static::CACHE_NOIMAGE_FOLDER .
            md5(serialize($this->options) . $this->noFile) . '.' . $this->getParam('f');
    }

    /**
     * @param array $params
     */
    public function init(array $params = array())
    {
        $this->noFile = (isset($params['noimage']) && $this->checkFile($params['noimage'])) ? $params['noimage'] : static::NO_IMAGE;

        $this->image = (isset($params['input']) && $this->checkFile($params['input'])) ? $params['input'] : $this->noFile;
        $this->queue = (empty($params['queue']) || $params['queue'] == 'false') ? false : true;

        $this->options = $this->parseOptions(
            $this->normalizeOptions(
                isset($params['options']) && is_scalar($params['options']) ? $params['options'] : ''
            )
        );
    }

    /**
     * @param string $options
     * @return array
     */
    protected function normalizeOptions($options)
    {
        return explode('&', strtr($options, array(',' => '&', '_' => '=')));
    }

    /**
     * @param array $options
     * @return array
     */
    protected function parseOptions($options)
    {
        $allow = $this->getAllow();
        $need = array_keys($allow);

        $out = array();
        foreach ($options as $value) {
            $thumb = explode('=', $value);
            $key = str_replace('[]', '', $thumb[0]);
            if (!empty($key)) {
                if (isset($need[$key])) {
                    if (is_string($need[$key])) {
                        $need[$key] = array($need[$key]);
                    }
                    $need[$key][] = $thumb[1];
                } else {
                    $need[$key] = $thumb[1];
                }
                unset($allow[$key]);
            }
            $out[$key] = $need[$key];
        }
        foreach ($allow as $key => $value) {
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param $oldImage
     * @param $newImage
     * @param array $options
     * @return $this
     */
    public function saveQueue($oldImage, $newImage, array $options = array())
    {
        $oldImage = $this->core->db->escape($oldImage);
        $newImage = $this->core->db->escape($newImage);
        $options = $this->core->db->escape(serialize($options));
        $table = $this->core->getFullTableName(static::TABLE);

        $q = $this->core->db->getValue("SELECT count(`id`) FROM " . $table . " WHERE `image` = '" . $oldImage . "' AND `cache_image` = '" . $newImage . "' AND `config` = '" . $options . "'");
        if ($q == 0) {
            $this->core->db->insert(array(
                'image'       => $oldImage,
                'cache_image' => $newImage,
                'config'      => $options,
                'isend'       => 0
            ), $table);
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getAllow()
    {
        $this->allowOptions = array();
        $this->allowOptions['q'] = static::DEFAULT_QUALITY;

        $path_parts = pathinfo($this->getImage());
        if (in_array(strtolower($path_parts['extension']), $this->allowExt)) {
            $this->allowOptions['f'] = strtolower($path_parts['extension']);
        } else {
            $this->allowOptions['f'] = static::DEFAULT_EXT;
        }

        return $this->allowOptions;
    }

    /**
     * @param $file
     * @param bool $full
     * @return mixed|string
     */
    public function clearPath($file, $full = true)
    {
        $out = '';
        if (is_scalar($file)) {
            $out = preg_replace("#^" . MODX_BASE_PATH . "#", '', $file);
            if ($full && !empty($out)) {
                $out = MODX_BASE_PATH . $out;
            }
        }

        return $out;
    }

    /**
     * @return mixed|string
     */
    protected function getImage()
    {
        $file = !empty($this->image) ? $this->image : $this->noFile;

        return $this->clearPath($file);
    }

    /**
     * @param $image
     * @return $this
     */
    protected function loadPhpThumb($image)
    {
        $this->phpThumb = new phpthumb();
        $this->phpThumb->config_cache_directory = $this->core->getCachePath();
        $this->phpThumb->config_ttf_directory = dirname(__DIR__) . '/fonts';
        $this->phpThumb->setSourceFilename($image);

        foreach ($this->options as $key => $value) {
            if (!empty($key)) {
                $this->phpThumb->setParameter($key, $value);
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getCacheName()
    {
        $image = $this->getImage();
        $path_parts = pathinfo($image);
        $tmp = preg_replace("#^" . MODX_BASE_PATH . "#", "", $path_parts['dirname']);
        $tmp = ($tmp == 'assets/images' ? '' : preg_replace("#^assets/images/#", "/", ltrim($tmp, '/')));
        $ftime = filemtime($image);
        $tmp = static::CACHE_IMAGE_FOLDER . ltrim($tmp, '/');
        $tmp = explode("/", $tmp);
        $tmp[] = substr(md5(serialize($this->options)), 0, 3);
        $tmp[] = date("Y-m", $ftime);
        $folder = '';
        for ($i = 0; $i < count($tmp); $i++) {
            $folder .= "/" . $tmp[$i];
            if (!is_dir(MODX_BASE_PATH . $folder) || !file_exists(MODX_BASE_PATH . $folder)) {
                mkdir(MODX_BASE_PATH . $folder);
            }
        }

        $outputFilename = MODX_BASE_PATH . $folder . "/" . date("d_h_i_s",
                $ftime) . "_" . $path_parts['extension'] . "_" . $path_parts['filename'] . "." . $this->getParam('f');

        return $outputFilename;
    }

    /**
     * @param $key
     * @return null
     */
    public function getParam($key)
    {
        return (!empty($key) && is_scalar($key) && isset($this->options[$key])) ? $this->options[$key] : null;
    }

    /**
     * @param $from
     * @param $to
     * @return array|string
     */
    public function createFile($from, $to)
    {
        if (!file_exists($to) || filemtime($from) > filemtime($to)) {
            $this->loadPhpThumb($from);
            if ($this->phpThumb->GenerateThumbnail()) {
                $this->phpThumb->RenderToFile($to);
            }
        }
        $res = explode("/assets", $to, 2);
        $res = "/assets" . $res[1];

        return $res;
    }

    /**
     * @param $file
     * @return bool
     */
    protected function checkFile($file)
    {
        $out = false;
        if (is_scalar($file) && !preg_match("/^http(s)?:\/\/\w+/", $file)) {
            $file = MODX_BASE_PATH . preg_replace("#^" . MODX_BASE_PATH . "#", '', $file);
            $out = (file_exists($file) && is_file($file) && is_readable($file));
        }

        return $out;
    }

    /**
     * @return mixed|string
     */
    public function makeFile()
    {
        $tmpName = $this->getCacheName();
        $image = $this->getImage();
        if (!file_exists($tmpName) || filemtime($image) > filemtime($tmpName)) {
            if ($this->queue) {
                $res = $this->saveQueue($this->clearPath($image, false), $this->clearPath($tmpName, false),
                    $this->options)->getNoFile($tmpName);
            } else {
                $res = $this->createFile($image, $tmpName);
            }
        } else {
            $res = $this->clearPath($tmpName, false);
        }

        return $res;
    }

    /**
     * @return bool|null
     */
    public function runQueue()
    {
        $flag = null;
        $table = $this->core->getFullTableName(static::TABLE);
        $q = $this->core->db->query("SELECT * FROM " . $table . " WHERE `isend` = 0 ORDER BY RAND() LIMIT 1");
        if ($this->core->db->getRecordCount($q) == 1) {
            $q = $this->core->db->getRow($q);
            $this->options = unserialize($q['config']);
            if (file_exists(MODX_BASE_PATH . $q['cache_image'])) {
                unlink(MODX_BASE_PATH . $q['cache_image']);
            }
            if (file_exists(MODX_BASE_PATH . $q['image'])) {
                $this->createFile(MODX_BASE_PATH . $q['image'], MODX_BASE_PATH . $q['cache_image']);
                $this->core->db->update(array('isend' => 1), $table, "id = " . $q['id']);
                $flag = true;
            } else {
                $this->core->db->delete($table, "id = " . $q['id']);
                $flag = false;
            }
        }

        return $flag;
    }
}
