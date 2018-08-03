<?php
require_once "classes/Config.class.php";

class TokenAdmin{
    private $config;

    public function __construct() {
        $this->config = new Config();
    }

    private function makeToken(){
        $response = $this->config->generateToken();
        $date = new DateTime('now');
        $expires_in = $date->add(new DateInterval('PT'.$response->expires_in.'S'))->format('Y-m-d H:i:s');
        $json_array = array(
            'access_token' => $response->access_token,
            'token_type' => $response->token_type,
            'expires_in' => $expires_in,
            'scope' => $response->scope
        );
        $arr = json_encode($json_array);
        $token_path = $this->config->getTokenPath();
        file_put_contents($token_path, $arr);
        return $response->access_token;
    }

    public function getToken(){
        $token_path = $this->config->getTokenPath();
        if(file_exists($token_path)){
            $json = file_get_contents($token_path);
            $json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
            $obj = json_decode($json,false);
            if(isset($obj->expires_in)){
                $from = new DateTime($obj->expires_in);
                $to = new DateTime('now');
                $interval = $to->diff($from)->format('%R');
            } else {
                throw new Exception("トークンの有効期限が不明です");
            }

            try{
                $r = $interval === "+" && !is_null($obj->access_token) ? $obj->access_token : $this->makeToken();
                return $r;
            } catch(Exception $e){
                throw new Exception($e->getMessage());
            }
        } else {
            throw new Exception("token.jsonファイルがありません");
        }
    }
}