
yii2-file-system
=================
Yii2-file-system是 [Flysystem](https://github.com/thephpleague/flysystem)基础上基于 [Yii2](https://github.com/yiisoft/yii2) 框架的实现的扩展。 **任何存储,统一的函数调用**

###扩展功能
- Local 本地存储支持Yii(例:`@web/assets`)别名路径 
- Qinu 七牛云存储

### 将要实现的功能 (欢迎PR)
- 阿里云存储
- 又拍云存储
- 百度云存储
- 新浪云存储

使用要求
========
- php >= 5.4
- [Flysystem](https://github.com/thephpleague/flysystem) 

使用教程
========
###使用`Componser`安装 (以下2种方式)
- 命令行执行 `composer require callmez/yii2-file-system`
- 编辑`composer.json` 

  ```php
  "require": {
      ...
      "callmez/yii2-file-system": "*"
  },
  ```
### 编辑配置文件(2种使用方式)
- 编辑`config/main.php`

  ```php
  'components' => [
    'fileSystemCollection或者fileSystem(第二种名称作为wrapper形式推荐)' => [
      'class' => 'callmez\file\system\Collection',
          'fileSystems' => [
              //根据需求可设置多个存储, 以下来使用例子
              'local' => function() {
                  return new \callmez\file\system\FileSystem(
                      new \callmez\file\system\adapters\Local('路径名 例如:@web/assets')
                  );
              },
              'qiniu' => function() {
                  return new \callmez\file\system\FileSystem(
                      new \callmez\file\system\adapters\Qiniu(
                          '七牛空间的 bucket',
                          '七牛空间的 access key',
                          '七牛空间的 access secret',
                          '七牛的空间域名,默认为 {bucket}.qiniu.com 选填'
                      )
                  );
              }
          ]
    ]
  ]
  ```
- 使用例子(推荐wrapper方式)

  ```php
    // 集合方式
    $local = Yii::$app->fileSystemConnection->get('local');
    $local->write('test.txt', 'hello world');
    echo $local->read('text.txt');
    
    $qiniu = Yii::$app->fileSystemConnection->get('qiniu');
    $qiniu->write('test.txt', 'hello world');
    echo $qiniu->read('text.txt');
    
    // wrapper 方式 (请注意, component命名方式)
    //等同于Yii::$app->fileSystemConnection->get('local')->write('test.txt', 'hello world');
    Yii::$app->fileSystem->write('local://test.txt', 'hello world'); 
    //等同于Yii::$app->fileSystemConnection->get('qiniu')->write('test.txt', 'hello world');
    Yii::$app->fileSystem->write('qiniu://test.txt', 'hello world'); 
  ```
