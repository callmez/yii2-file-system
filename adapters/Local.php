<?php
namespace callmez\file\system\adapters;

use Yii;
use League\Flysystem\Adapter\Local as LocalFileSystem;

class Local extends LocalFileSystem
{
    public function __construct($root)
    {
        parent::__construct(Yii::getAlias($root));
    }
}