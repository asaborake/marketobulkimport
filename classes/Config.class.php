<?php
require_once "classes/LogAdmin.class.php";

class Config
{
    #Set path with your application
    private const APPLICATION_DIR = '';
    private const TOKEN_PATH = self::APPLICATION_DIR.'/config/token.json';
    private const CSV_PATH = self::APPLICATION_DIR.'/csv/';
    private const LOG_PATH = self::APPLICATION_DIR.'/log/';

    #Set your Marketo information
    private const API_NAME = '';
    private const BASE_URL = '';
    private const AUTH_ENDPOINT = self::BASE_URL.'/identity/oauth/token';
    private const BULK_ENDPOINT = self::BASE_URL.'/bulk/v1/customobjects/'.self::API_NAME."/import";
    private const CLIENT_ID = '';
    private const CLIENT_SECRET = '';

    #Getter Method
    public function getTokenPath(){return self::TOKEN_PATH;}
    public function getCSVPath(){return self::CSV_PATH;}
    public function getLogPath(){return self::LOG_PATH;}

    #API request for Marketo(curl)
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
            throw new Exception("Error:Function argument is null");
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

    #Generate token
    public function generateToken(){
        #Request body
        $url = self::AUTH_ENDPOINT;
        $url .= "?grant_type=client_credentials&client_id=";
        $url .= self::CLIENT_ID;
        $url .= "&client_secret=";
        $url .= self::CLIENT_SECRET;
        $method = array('method'=>'GET', 'body'=>'-');

        #Request API
        try{
            $response = $this->APIRequest($url, $method, "Error:Token getting failed");
            return $response;
        } catch(Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    #Post csv file
    public function postData($access_token, $file){
        #Request body
        $url = self::BULK_ENDPOINT.".json";
        $cfile = new CURLFile($file, "text/plain", "file");
        $requestBody = array('access_token'=>$access_token,'format'=>'csv','file'=>$cfile);
        $method = array('method'=>'POST', 'body'=>$requestBody);

        #Request API
        try{
            $response = $this->APIRequest($url, $method, "Error:CSV file posting failed");
            return $response;
        } catch(Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    #Polling, Get error csv file
    public function poll($access_token, $batchId, $mode){
        #Request body
        $url = self::BULK_ENDPOINT;
        $url .= "/".$batchId;
        $url .= "/".$mode.".json?access_token=".$access_token;
        $method = array('method'=>'GET', 'body'=>$mode);

        #Request API
        try{
            $error_message = $mode==="status" ? "Error:Polling failed" : "Error:Error CSV file getting failed";
            $response = $this->APIRequest($url, $method, $error_message);
            return $response;
        } catch(Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}