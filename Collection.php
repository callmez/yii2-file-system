<?php
namespace callmez\file\system;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

class Collection extends Component
{
    public $defaultFileSystem;
    public $fileSystemClass = 'League\Flysystem\Filesystem';
    private $_fileSystems = [];

    public function init()
    {
        if ($this->defaultFileSystem === null) {
            throw new InvalidConfigException("The 'defaultFileSystem' property must be set.");
        }
    }

    public function getFileSystems()
    {
        $fileSystems = [];
        foreach ($this->_fileSystems as $id => $fileSystem)
        {
            $fileSystems[$id] = $this->get($id);
        }
        return $fileSystems;
    }

    public function setFileSystems(array $fileSystems)
    {
        $this->_fileSystems = $fileSystems;
    }

    public function get($id)
    {
        $id === null && $id = $this->defaultFileSystem;
        if (!$this->has($id)) {
            throw new InvalidParamException("Unknown file system '{$id}'.");
        }
        if (!is_object($this->_fileSystems[$id])) {
            $this->_fileSystems[$id] = $this->create($id, $this->_fileSystems[$id]);
            if (!is_subclass_of($this->_fileSystems[$id], $this->fileSystemClass)) {
                throw new InvalidParamException("The file system '{$id}' must extend from '{$this->fileSystemClass}'.");
            }
        }
        return $this->_fileSystems[$id];
    }

    public function has($id)
    {
        return array_key_exists($id, $this->_storages);
    }

    public function create($id, $config)
    {
        $config['id'] = $id;
        return Yii::createObject($config);
    }
}