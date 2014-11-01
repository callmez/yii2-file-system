<?php
namespace callmez\file\system;

use Yii;
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

    public function get($id = null)
    {
        $id === null && $id = $this->defaultFileSystem;
        if (!$this->has($id)) {
            throw new InvalidParamException("Unknown file system '{$id}'.");
        }
        if (!is_object($this->_fileSystems[$id])) {
            $this->_fileSystems[$id] = $this->create($this->_fileSystems[$id]);
        }
        return $this->_fileSystems[$id];
    }

    public function has($id)
    {
        return array_key_exists($id, $this->_fileSystems);
    }

    public function create($config)
    {
        if (!isset($config['class'])) {
            throw new InvalidConfigException('File system config must be an array containing a "class" element.');
        }
        $class = $config['class'];
        unset($config['class']);
        return Yii::createObject($this->fileSystemClass, [Yii::createObject($class, array_values($config))]);
    }
}