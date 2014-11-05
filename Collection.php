<?php
namespace callmez\file\system;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

class Collection extends Component
{
    private $_fileSystems = [];

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
        if (!$this->has($id)) {
            throw new InvalidParamException("Unknown file system '{$id}'.");
        }
        if (!is_object($this->_fileSystems[$id]) || is_callable($this->_fileSystems[$id])) {
            $this->_fileSystems[$id] = $this->create($this->_fileSystems[$id]);
            if (!($this->_fileSystems[$id] instanceof FileSystem)) {
                throw new InvalidConfigException("The file system class '{$id}'' must extend from callmez\\file\\system\FileSystem");
            }
        }
        return $this->_fileSystems[$id];
    }

    public function has($id)
    {
        return array_key_exists($id, $this->_fileSystems);
    }

    public function create($config)
    {
        return Yii::createObject($config);
    }
}