<?php

class S3StaticSiteBaker
{
	function __construct()
	{
		$this->setS3Key(S3_KEY);
		$this->setS3Secret(S3_SECRET);
		
		$this->staticFolders = array();
		$this->staticFiles = array();
		$this->webFiles = array();
		$this->phpFiles = array();
		
		$this->ignoreFiles = array('.' , '..', '.DS_Store', '.svn', '.git'); 
	}
	
	function addStaticFolder($localFolder,$remoteFolder,$compressCSSAndJS = true)
	{
		$this->staticFolders[] = $remoteFolder;
		
		if(is_dir($localFolder))
		{
			$dh = opendir($localFolder); 
			
			while( false !== ( $file = readdir( $dh ) ) )
			{ 
				if(!in_array($file, $this->ignoreFiles))
				{
					if( is_dir( $localFolder . "/" . $file ) )
					{ 
						$this->addStaticFolder($localFolder . "/" . $file,$remoteFolder."/".$file,$compressCSSAndJS);
					}
					else
					{
						$this->addStaticFile($localFolder . "/" . $file, $remoteFolder . "/" . $file);
					}
				}
				
				
			}
			
			closedir( $dh ); 
			
		}
		else
		{
			echo "Error addStaticFolder: Folder does not exist";
		}
	}
	
	function addStaticFile($localPath,$remotePath,$compressCSSAndJS = true)
	{
		$this->staticFiles[] = array("localPath" => $localPath, "remotePath" => $remotePath, "compress" => $compressCSSAndJS);
	}
	
	function addWebFile($localPath,$remotePath,$compressCSSAndJS = true)
	{
		$this->webFiles[] = array("localPath" => $localPath, "remotePath" => $remotePath, "compress" => $compressCSSAndJS);
	}
	
	function addPHPWebFile($localPath,$paramters,$remotePath)
	{
		$this->phpFiles[] = array("localPath" => $localPath, "remotePath" => $remotePath, "parameters" => $paramters);
	}
	
	function bake()
	{
		
		self::insertSpacer();
		
		if(!$this->checkIngredients())
		{
			self::insertSpacer();
			return;
		}
		
		
		
		
		self::insertSpacer();
		
		if(!$this->preHeatOven())
		{
			self::insertSpacer();
			return;
		}
		
		
		self::insertSpacer();
		
		echo "Baking a cake as fast as we can...";
		
		self::insertSpacer();
		
		echo "Static Files...";
		$this->uploadStaticFiles();
		
		self::insertSpacer();
		
		echo "Web Files...";
		$this->uploadWebFiles();
		
		self::insertSpacer();
		
		echo "PHP Files...";
		$this->executeAndUploadPHPFiles();
		
		self::insertSpacer();
		
		echo "Ding!";
		
		self::insertSpacer();
	}
	
	function executeAndUploadPHPFiles()
	{
		if($this->phpFiles != null)
		{
			foreach($this->phpFiles as $phpFile)
			{
				
				$s3 = new S3($this->getS3Key(),$this->getS3Secret(),false);
				$s3->headers['Content-Type'] = 'text/html';
				
				if(substr($phpFile['remotePath'], 0, 1) == "/")
				{
					$phpFile['remotePath'] =  substr($phpFile['remotePath'], 1);
				}
				
				$dir = dirname($phpFile['localPath']);
				$script = basename($phpFile['localPath']);
				
				ob_start();
				if($phpFile['parameters'] != "")
				{
					system("cd " . $dir . "; php -f " . $script . " " . $phpFile['parameters']);
				} 
				else
				{
					system("cd " . $dir . "; php -f " . $script);
				}
				
				$scriptContents = ob_get_clean();
				
				foreach($this->staticFolders as $staticFolder)
				{
					$scriptContents = str_replace('"'.$staticFolder,'"http://' . $this->getStaticFilesBucketName() .'/' . $this->getStaticFilesVersioning() . $staticFolder,$scriptContents);
				}
				
				$s3->putObject($scriptContents,$this->getWebFilesBucketName(),$phpFile['remotePath'],S3::ACL_PUBLIC_READ,array(),$s3->headers);
				
			}
			
		}
	}
	
	function uploadWebFiles()
	{
		if($this->webFiles != null)
		{
			foreach($this->webFiles as $webFile)
			{
				$s3 = new S3($this->getS3Key(),$this->getS3Secret(),false);
				$ext = pathinfo($webFile['localPath'], PATHINFO_EXTENSION);
				if(self::getSimpleMimeType($ext))
				{
					$s3->headers['Content-Type'] = self::getSimpleMimeType($ext);
				}
				else
				{
					$s3->headers['Content-Type'] = 'application/octet-stream';
				}
				
				if(substr($webFile['remotePath'], 0, 1) == "/")
				{
					$webFile['remotePath'] =  substr($webFile['remotePath'], 1);
				}
				
				// upload
				$s3->putObject(file_get_contents($webFile['localPath']),$this->getWebFilesBucketName(),$webFile['remotePath'],S3::ACL_PUBLIC_READ,array(),$s3->headers);
			}
		}
	}
	
	function uploadStaticFiles()
	{
		if($this->staticFiles != null)
		{
			foreach($this->staticFiles as $staticFile)
			{
				$s3 = new S3($this->getS3Key(),$this->getS3Secret(),false);
				$ext = pathinfo($staticFile['localPath'], PATHINFO_EXTENSION);
				if(self::getSimpleMimeType($ext))
				{
					$s3->headers['Content-Type'] = self::getSimpleMimeType($ext);
				}
				else
				{
					$s3->headers['Content-Type'] = 'application/octet-stream';
				}
				
				if(!is_dir($staticFile['localPath']) && (strpos($staticFile['localPath'], ".css") || strpos($staticFile['localPath'], ".js")))
				{
					if(strpos($staticFile['localPath'], ".css"))
					{
						$tmpfname = tempnam("/tmp", "S3StaticSiteBaker".time().".css");
						
						$cssContents = file_get_contents($staticFile['localPath']);
						$cssContents = str_replace("url('/", "url('/".$this->getStaticFilesVersioning()."/", $cssContents);
						$cssContents = str_replace("url(\"/", "url(\"/".$this->getStaticFilesVersioning()."/", $cssContents);
						$cssContents = str_replace("url(/", "url(/".$this->getStaticFilesVersioning()."/", $cssContents);
						
						file_put_contents($tmpfname, $cssContents);
						
						if($staticFile['compress'] == true)
						{
							system("java -jar resources/compressor/yuicompressor-2.4.2.jar --type css " . $tmpfname ." -o " . $tmpfname);
						}
						
						$s3->putObject(file_get_contents($tmpfname),$this->getStaticFilesBucketName(),$this->getStaticFilesVersioning().$staticFile['remotePath'],S3::ACL_PUBLIC_READ,array(),$s3->headers);
						
						unlink($tmpfname);
						
					}
					else if(strpos($staticFile['localPath'], ".js"))
					{
						$tmpfname = tempnam("/tmp", "S3StaticSiteBaker".time().".js");
						
						$jsContents = file_get_contents($staticFile['localPath']);
						
						file_put_contents($tmpfname, $jsContents);
						
						if($staticFile['compress'] == true)
						{
							system("java -jar resources/compressor/yuicompressor-2.4.2.jar --type js " . $tmpfname ." -o " . $tmpfname);
						}
						
						$s3->putObject(file_get_contents($tmpfname),$this->getStaticFilesBucketName(),$this->getStaticFilesVersioning().$staticFile['remotePath'],S3::ACL_PUBLIC_READ,array(),$s3->headers);
						
						unlink($tmpfname);
						
					}
					
				}
				else
				{
					// no compression
					$s3->putObject(file_get_contents($staticFile['localPath']),$this->getStaticFilesBucketName(),$this->getStaticFilesVersioning().$staticFile['remotePath'],S3::ACL_PUBLIC_READ,array(),$s3->headers);
				}
			}
		}
	}
	
	function checkIngredients()
	{
		echo "Checking Ingredients...";
		
		if($this->getS3Key() == "")
		{
			echo "S3 Key Required";
			return false;
		}
		
		if($this->getS3Secret() == "")
		{
			echo "S3 Secret Required";
			return false;
		}
		
		if($this->getTempDirectory() == "")
		{
			echo "Temp Directory Required";
			return false;
		}
		
		if(!is_dir($this->getTempDirectory()))
		{
			echo "Temporary Directory Not Found";
			return false;
		}
		
		if($this->getWebFilesBucketName() == "")
		{
			echo "Web Files Bucket Name Required";
			return false;
		}
		
		if($this->getStaticFilesBucketName() == "")
		{
			echo "Web Files Bucket Name Required";
			return false;
		}
		
		return true;
	}
	
	function preHeatOven()
	{
		echo "Preheating the Oven...";
		
		// check temp folder
			$tmpfname = tempnam($this->getTempDirectory(), "FOO");
			$handle = fopen($tmpfname, "w");
			fwrite($handle, "writing to tempfile");
			fclose($handle);
			// do here something
			
			if($tmpfname == FALSE)
			{
				echo "Unable to write to temp file";
				return false;
			}
			
			unlink($tmpfname);
			
		// check s3 communication
		$s3 = new S3($this->getS3Key(),$this->getS3Secret(),false);
		
		$buckets = $s3->listBuckets();
		
		if(!$buckets)
		{
			echo "Bucket communication test failed";
			return false;
		}
		if(!in_array($this->getWebFilesBucketName(), $buckets))
		{
			echo "Web Files Bucket does not exist.";
			return false;
		}
		if(!in_array($this->getStaticFilesBucketName(), $buckets))
		{
			echo "Static Files Bucket does not exist.";
			return false;
		}
		
		return true;
		
	}
	
	function insertSpacer()
	{
		sleep(1);
		echo "\n\n";
	}
	
	function setWebFilesBucketName($val) { $this->webFilesBucketName = $val; }
	function getWebFilesBucketName() { return $this->webFilesBucketName; }
	
	function setStaticFilesBucketName($val) { $this->staticFilesBucketName = $val; }
	function getStaticFilesBucketName() { return $this->staticFilesBucketName; }
	
	function setStaticFilesVersioning($val) { $this->staticFilesVersioning = $val; }
	function getStaticFilesVersioning() { return $this->staticFilesVersioning; }

	function setS3Key($val) { $this->S3Key = $val; }
	function getS3Key() { return $this->S3Key; }
	
	function setS3Secret($val) { $this->S3Secret = $val; }
	function getS3Secret() { return $this->S3Secret; }
	
	function setTempDirectory($val) { $this->tempDirectory = $val; }
	function getTempDirectory() { return $this->tempDirectory; }
	
	
	static function getSimpleMimeType($ext)
	{
		$exts = array(
				'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png',
				'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'ico' => 'image/x-icon',
				'swf' => 'application/x-shockwave-flash', 'pdf' => 'application/pdf',
				'zip' => 'application/zip', 'gz' => 'application/x-gzip',
				'tar' => 'application/x-tar', 'bz' => 'application/x-bzip',
				'bz2' => 'application/x-bzip2', 'txt' => 'text/plain',
				'asc' => 'text/plain', 'htm' => 'text/html', 'html' => 'text/html',
				'css' => 'text/css', 'js' => 'text/javascript',
				'xml' => 'text/xml', 'xsl' => 'application/xsl+xml',
				'ogg' => 'application/ogg', 'mp3' => 'audio/mpeg', 'wav' => 'audio/x-wav',
				'avi' => 'video/x-msvideo', 'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg',
				'mov' => 'video/quicktime', 'flv' => 'video/x-flv', 'php' => 'text/x-php'
			);
			
		return $exts[$ext];
	}
	
}


?>
