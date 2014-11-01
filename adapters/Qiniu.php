<?php
namespace callmez\file\system\adapters;

use League\Flysystem\Util;
use Yii;
use League\Flysystem\Adapter\AbstractAdapter;

// 列举资源
require_once Yii::getAlias("@vendor/qiniu/php-sdk/qiniu/rsf.php");

class Qiniu extends AbstractAdapter
{
    public $bucket;
    public $domain;
    private $_client;
    public function __construct($bucket, $accessKey, $accessSecret, $domain = null)
    {
        $this->bucket = $bucket;
        Qiniu_SetKeys($accessKey, $accessSecret);
        $this->domain = $domain === null ? $this->bucket . '.qiniudn.com' : $domain;
    }
    /**
     * Check whether a file is present
     *
     * @param   string   $path
     * @return  boolean
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Write a file
     *
     * @param $path
     * @param $contents
     * @param null $config
     * @return array|bool
     */
    public function write($path, $contents, $config = null)
    {
        $bucket = $this->bucket . (empty($config->settings['update']) ? '' : ':' . $path);
        $putPolicy = new \Qiniu_RS_PutPolicy($bucket);
        $upToken = $putPolicy->Token(null);
        list($ret, $err) = Qiniu_Put($upToken, $path, $contents, null);
        if ($err !== null) {
            return false;
        }
        $mimetype = Util::guessMimeType($path, $contents);
        return compact('mimetype', 'path');
    }

    /**
     * Update a file
     *
     * @param   string       $path
     * @param   string       $contents
     * @param   mixed        $config   Config object or visibility setting
     * @return  array|bool
     */
    public function update($path, $contents, $config = null)
    {
        $config->settings['update'] = true;
        return $this->write($path, $contents, $config);
    }

    /**
     * Read a file
     *
     * @param   string  $path
     * @return  array|bool
     */
    public function read($path)
    {
        $getPolicy = new \Qiniu_RS_GetPolicy();
        $url = $getPolicy->MakeRequest($this->getUrl($path), null);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $contents = curl_exec($ch);
        curl_close($ch);

        return compact('contents', 'path');
    }

    /**
     * Rename a file
     *
     * @param $path
     * @param $newpath
     * @return bool
     */
    public function rename($path, $newpath)
    {
        $err = Qiniu_RS_Move($this->getClient(), $this->bucket, $path, $this->bucket, $newpath);
        return $err === null;
    }

    /**
     * Copy a file
     *
     * @param $path
     * @param $newpath
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $err = Qiniu_RS_Copy($this->getClient(), $this->bucket, $path, $this->bucket, $newpath);
        return $err === null;
    }

    /**
     * Delete a file
     *
     * @param $path
     * @return bool
     */
    public function delete($path)
    {
        $err = Qiniu_RS_Delete($this->getClient(), $this->bucket, $path);
        return $err === null;
    }

    /**
     * List contents of a directory
     *
     * @param string $directory
     * @param bool $recursive
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $files = [];
        foreach($this->listDirContents($directory) as $k => $file) {
            $pathInfo = pathinfo($file['key']);
            $files[] = array_merge([
                'type' => isset($pathInfo['extension']) ? 'file' : 'dir',
            ], $pathInfo, $this->normalizeData($file));
        }
        return $files;
    }

    /**
     * Get the metadata of a file
     *
     * @param $path
     * @return array
     */
    public function getMetadata($path)
    {
        list($ret, $err) = Qiniu_RS_Stat($this->getClient(), $this->bucket, $path);
        if ($err !== null) {
            return false;
        }
        return $this->normalizeData($ret);
    }

    /**
     * Get the size of a file
     *
     * @param $path
     * @return array
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file
     *
     * @param $path
     * @return array
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file
     *
     * @param $path
     * @return array
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Create a directory
     * 七牛无目录概念. 直接创建成功
     * @param   string       $dirname directory name
     * @param   array|Config $options
     *
     * @return  bool
     */
    public function createDir($dirname, $options = null)
    {
        return ['path' => $dirname];
    }

    /**
     * Delete a directory
     * 七牛无目录概念. 目前实现方案是.列举指定目录资源.批量删除
     * @param $dirname
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $item = $this->listDirContents($dirname);
        $enties = array_map(function($file) {
            return new \Qiniu_RS_EntryPath($this->bucket, $file['key']);
        }, $item);
        list($ret, $err) = Qiniu_RS_BatchDelete($this->getClient(), $enties);
        return $err === null;
    }

    protected function normalizeData($file)
    {
        return [
            'path' => $file['key'],
            'size' => $file['fsize'],
            'mimetype' => $file['mimeType'],
            'timestamp' => (int)($file['putTime'] / 10000000) //Epoch 时间戳
        ];
    }

    public function getUrl($path)
    {
        return Qiniu_RS_MakeBaseUrl($this->domain, $path);
    }

    protected function getClient()
    {
        if ($this->_client === null) {
            $this->setClient(new \Qiniu_MacHttpClient(null));
        }
        return $this->_client;
    }

    protected function setClient($client)
    {
        return $this->_client = $client;
    }

    protected function listDirContents($directory, $start = '')
    {
        list($item, $marker, $err) = Qiniu_RSF_ListPrefix($this->getClient(), $this->bucket, $directory, $start);
        if ($err !== 'EOF') {
            $start = $marker;
            $item = array_merge($item, $this->listDirContents($directory, $start));
        }
        return $item;
    }
}