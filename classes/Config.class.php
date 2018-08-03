<?php
require_once "classes/LogAdmin.class.php";

class Config
{
    #アプリケーションの配置に合わせて設定
    private const APPLICATION_DIR = '';
    private const TOKEN_PATH = self::APPLICATION_DIR.'/config/token.json';
    private const CSV_PATH = self::APPLICATION_DIR.'/csv/';
    private const LOG_PATH = self::APPLICATION_DIR.'/log/';

    #Marketoの設定に合わせて設定
    private const API_NAME = '';
    private const BASE_URL = '';
    private const AUTH_ENDPOINT = self::BASE_URL.'/identity/oauth/token';
    private const BULK_ENDPOINT = self::BASE_URL.'/bulk/v1/customobjects/'.self::API_NAME."/import";
    private const CLIENT_ID = '';
    private const CLIENT_SECRET = '';

    #アプリケーションの設定部分のみゲッターを設定（MarketoAPIの設定内容はこのクラス内のみの使用）
    public function getTokenPath(){return self::TOKEN_PATH;}
    public function getCSVPath(){return self::CSV_PATH;}
    public function getLogPath(){return self::LOG_PATH;}

    #APIリクエストを行うcurl部分を共通化（このクラス内でのみ使用する為、privateに設定）
    private function APIRequest($url, $request, $errormessage){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if($request['method'] === "GET"){
            $accept = $request['body']==="failures" ? "accept: text/csv" : "accept: application/json";
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($accept));
        } elseif($request['method'] === "POST") {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json','Content-Type: multipart/form-data'));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request['body']);
        } else {
            throw new Exception("APIRequestメソッドへのリクエストメソッドの値渡しに失敗しました");
        }
        $r = $request['body']==="failures" ? curl_exec($ch) : json_decode(curl_exec($ch),false);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        if($curlErrno || count($r) === 0 || isset($r->error)){
            $log = new LogAdmin();
			$errResult = $log->makeLog("APIRequestError", $r);
            throw new Exception($errormessage." | ".$errResult);
        }
        return $r;
    }

    #API利用方法別にメソッド設定（トークン発行）
    public function generateToken(){
        #リクエスト内容の生成
        $url = self::AUTH_ENDPOINT;
        $url .= "?grant_type=client_credentials&client_id=";
        $url .= self::CLIENT_ID;
        $url .= "&client_secret=";
        $url .= self::CLIENT_SECRET;
        $method = array('method'=>'GET', 'body'=>'-');

        #APIリクエスト実行
        try{
            $response = $this->APIRequest($url, $method, "トークンの取得に失敗しました");
            return $response;
        } catch(Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    #API利用方法別にメソッド設定（CSVファイルポスト）
    public function postData($access_token, $file){
        #リクエスト内容の生成
        $url = self::BULK_ENDPOINT.".json";
        $cfile = new CURLFile($file, "text/plain", "file");
        $requestBody = array('access_token'=>$access_token,'format'=>'csv','file'=>$cfile);
        $method = array('method'=>'POST', 'body'=>$requestBody);

        #APIリクエスト実行
        try{
            $response = $this->APIRequest($url, $method, "ファイルのポストに失敗しました");
            return $response;
        } catch(Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    #API利用方法別にメソッド設定（ポーリング, エラーCSVファイル取得）
    public function poll($access_token, $batchId, $mode){
        #リクエスト内容の生成
        $url = self::BULK_ENDPOINT;
        $url .= "/".$batchId;
        $url .= "/".$mode.".json?access_token=".$access_token;
        $method = array('method'=>'GET', 'body'=>$mode);

        #APIリクエスト実行
        try{
            $error_message = $mode==="status" ? "ポーリングに失敗しました" : "エラーCSVの取得に失敗しました";
            $response = $this->APIRequest($url, $method, $error_message);
            return $response;
        } catch(Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}