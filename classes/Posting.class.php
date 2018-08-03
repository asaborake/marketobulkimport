<?php
require_once "classes/TokenAdmin.class.php";
require_once "classes/Config.class.php";

class Posting extends Config{
	public function doPost($file){
		try{
			$config = new Config();
			if(!file_exists($file)){throw new Exception("Error:CSV file doen't exit");}
			$tokenadmin = new TokenAdmin();
			$access_token = $tokenadmin->getToken();
			$result = $config->postData($access_token, $file);
			return $result;
		} catch(Exception $e){
			throw new Exception($e->getMessage());
		}
    }
}