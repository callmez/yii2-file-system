<?php
namespace callmez\file\system\adapters;

use Yii;

class Local extends \League\Flysystem\Adapter\Local
{
    public function __construct($root)
    {
        parent::__construct(Yii::getAlias($root));
    }
}