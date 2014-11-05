<?php
namespace callmez\file\system;

class FileSystem extends \League\Flysystem\Filesystem
{
    /**
     * Adapter类方法映射
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        if(method_exists($this->adapter, $method)) {
            return call_user_func_array([$this->adapter, $method], $arguments);
        } else {
            return parent::__call($method, $arguments);
        }
    }
}