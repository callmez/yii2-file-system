<?php
namespace callmez\file\system\adapters;

use Yii;
use League\Flysystem\Util;
use League\Flysystem\Config;
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
    public function write($path, $contents, Config $config)
    {
        return $this->update($path, $contents, $config);
    }

    /**
     * Write using a stream
     *
     * @param $path
     * @param $resource
     * @param null $config
     * @return array|bool
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->updateStream($path, $resource, $config);
    }

    /**
     * Update a file
     *
     * @param   string       $path
     * @param   string       $contents
     * @param   mixed        $config   Config object or visibility setting
     * @return  array|bool
     */
    public function update($path, $contents, Config $config)
    {
        list($ret, $err) = Qiniu_RS_Put($this->getClient(), $this->bucket, $path, $contents, null);
        if ($err !== null) {
            return false;
        }
        $mimetype = Util::guessMimeType($path, $contents);
        return compact('mimetype', 'path');
    }

    /**
     * Update a file using a stream
     *
     * @param   string    $path
     * @param   resource  $resource
     * @param   mixed     $config   Config object or visibility setting
     * @return  array|bool
     */
    public function updateStream($path, $resource, Config $config)
    {
        $size = Util::getStreamSize($resource);
        list($ret, $err) = Qiniu_RS_Rput($this->getClient(), $this->bucket, $path, $resource, $size, null);
        if ($err !== null) {
            return false;
        }
        return compact('path', 'size');
    }

    /**
     * Read a file
     *
     * @param   string  $path
     * @return  array|bool
     */
    public function read($path)
    {
        $contents = stream_get_contents($this->readStream($path)['stream']);
        return compact('contents', 'path');
    }

    /**
     * Get a read-stream for a file
     *
     * @param $path
     * @return array|bool
     */
    public function readStream($path)
    {
        $stream = fopen($this->getPrivateUrl($path), 'r');
        return compact('stream', 'path');
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
            $files[] = array_merge($pathInfo, $this->normalizeData($file), [
                'type' => isset($pathInfo['extension']) ? 'file' : 'dir',
            ]);
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
        $ret['key'] = $path;
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
    public function createDir($dirname, Config $config)
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

    /**
     * 获取公有资源地址
     * @param $path
     * @return string
     */
    public function getUrl($path)
    {
        return Qiniu_RS_MakeBaseUrl($this->domain, $path);
    }

    /**
     * 获取私有资源地址(公有资源一样可用)
     * @param $path
     * @return string
     */
    public function getPrivateUrl($path)
    {
        $getPolicy = new \Qiniu_RS_GetPolicy();
        return $getPolicy->MakeRequest($this->getUrl($path), null);
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