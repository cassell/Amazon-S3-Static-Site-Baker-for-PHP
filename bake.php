<?php

require_once("resources/s3/class.S3.php");
require_once("resources/classes/class.S3StaticSiteBaker.php");

if(count($argv) < 2 || $argv[1] == "?")
{
	echo "Usage: php -f bake.php <website-config-file.php> \n";
	exit;
}

if(!file_exists($argv[1]))
{
	echo "Error: config file not found.\n";
	echo "Usage: php -f bake.php <website-config-file.php> \n";
	exit;
}

$baker = new S3StaticSiteBaker();

include($argv[1]);

$baker->bake();

?>