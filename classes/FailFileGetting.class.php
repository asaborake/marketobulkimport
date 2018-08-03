<?php
require_once "classes/TokenAdmin.class.php";
require_once "classes/Config.class.php";

class FailFileGetting{
    public function doFailFileGet($batchId, $file){
		try{
			if(!isset($batchId)){throw new Exception("バッチIDを取得出来ていません");}
			$tokenadmin = new TokenAdmin();
			$access_token = $tokenadmin->getToken();
			$config = new Config();
			$result = $config->poll($access_token, $batchId, "failures");
			file_put_contents($file, trim($result,'"'));
			return $result;
		} catch(Exception $e){
			throw new Exception($e->getMessage());
		}
    }
}