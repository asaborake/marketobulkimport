<?php
require_once "classes/TokenAdmin.class.php";
require_once "classes/Config.class.php";

class Polling{
    public function doPoll($batchId){
			try{
				$config = new Config();
				if(!isset($batchId)){throw new Exception("バッチIDを取得出来ていません");}
				$tokenadmin = new TokenAdmin();
				$access_token = $tokenadmin->getToken();
				$result = $config->poll($access_token, $batchId, "status");
				return $result;
			} catch(Exception $e){
				throw new Exception($e->getMessage());
			}
    }
}