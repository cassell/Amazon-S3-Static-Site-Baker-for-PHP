<?php

/* PHP Site Baker Config File */

// www.example.com

require_once("resources/s3/class.S3.php");
require_once("resources/classes/class.S3StaticSiteBaker.php");

$baker = new S3StaticSiteBaker();

// s3 setup
$baker->setS3Key('S3KEYS3KEYS3KEY');
$baker->setS3Secret('S3SECRETS3SECRETS3SECRETS3SECRETS3SECRETS3SECRET');
$baker->setStaticFilesBucketName('static.example.com');
$baker->setWebFilesBucketName('www.example.com');
$baker->setStaticFilesVersioning(date("YmdHi"));

// temp
$baker->setTempDirectory('/tmp');

$baker->addStaticFolder('/Library/WebServer/Documents/example/trunk/source/css','/css');
$baker->addStaticFolder('/Library/WebServer/Documents/example/trunk/source/img','/img');

$baker->addWebFile('/Library/WebServer/Documents/example/trunk/source/robots.txt','/robots.txt');

$baker->addPHPWebFile('/Library/WebServer/Documents/example/trunk/source/index.php',null,'/index.html');
$baker->addPHPWebFile('/Library/WebServer/Documents/example/trunk/source/error.php',null,'/error.html');


$baker->bake();

?>