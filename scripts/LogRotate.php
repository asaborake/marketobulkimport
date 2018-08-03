<?php
require_once "classes/LogAdmin.class.php";
require_once "classes/Config.class.php";
require_once "classes/Posting.class.php";
require_once "classes/Polling.class.php";
require_once "classes/FailFileGetting.class.php";

$flag;
$batchId;
$r;
$f;
$filedate = $argv[1];
$content = array();
$csv = array();
$csv_data = array();
$log = new LogAdmin();
$config = new Config();

if(isset($filedate)){
    $csv_path = $config->getCSVPath();
    $usage = $csv_path."UsageReport".$filedate."_mod_.csv";
    $import_error = $csv_path."ImportErrorReport.csv";

    try{
        #CSVの存在チェック
        if(file_exists($usage)){
            if(file_exists($import_error)){
                #エラーCSVを配列化したうえで通常CSVに追記する
                $fp = fopen($import_error, "r");
                $i = 0;
                while (($data = fgetcsv($fp, 0, ",")) !== FALSE) {
                    ++$i;
                    if($i === 1){continue;}
                    $csv_data = array();
                    foreach($data as $value){
                        if($value === end($data)){break;}
                        array_push($csv_data, $value);
                    }
                    $csv = array($csv_data);
                }
                fclose($fp);
                
                $f = fopen($usage, "a");
                foreach($csv as $fields){
                    fputcsv($f, $fields);
                }
                fclose($f);

                #マージが完了したのでエラーCSVを削除する
                unlink($import_error);
            }
        } else {
            throw new Exception("UsageReportファイルが見つかりません（LogRotate）");
        }

        #CSVファイルを送信
        $postData = new Posting();
        $res = $postData->doPost($usage);
        $log->makeLog("response",$res);

        if(!$res->success){
            throw new Exception("ファイルのポストに失敗しました（LogRotate）");
        } else {
            $resArr = $res->result[0]; #ポストした処理のIDを含む配列
            do {
                #ポーリング実施
                if(isset($resArr->batchId)){
                    $p = new Polling();
                    $r = $p->doPoll($resArr->batchId);
                    $log->makeLog("polling", $r);
                } else {
                    throw new Exception("バッチIDの取得に失敗しました（オブジェクト配列周り）");
                }
                #Failedの際の終了処理
                $f = $r->result[0]->status;
                if($f == "Failed"){
                    throw new Exception("ポーリングがFailedで終了しました"); #Failedでもループ終了
                    break; #前でthrowしているので必要ないと思うが、念の為
                }
                sleep(60);
            } while ($f != "Complete");

            #エラーCSV生成（エラーが含まれていた場合）
            if(isset($r->result[0]->numOfRowsFailed) && $r->result[0]->numOfRowsFailed >= 1){
                $failfile = new FailFileGetting();
                $resf = $failfile->doFailFileGet($r->result[0]->batchId, $import_error);
                copy($import_error, $config->getLogPath()."ImportErrorReport_".$filedate.".csv");
                $log->makeLog("getErrLog", $resf);
            }
        }
        #結果ログ作成（success.logに１実行１行で書き込み）
        $resSuccess = $log->makeLog("success", $r);
        $flag = "success:APIへの書き込みが終わりました。詳細はsuccess.logを参考にしてください。 | ".$resSuccess;
    } catch(Exception $e){
        #エラーログ作成（error.logに１実行1行で書き込み）
        $resError = $log->makeLog("error", $e->getMessage());
        $flag = "error:何らかの段階でエラーが発生しました。詳細はerror.logを参考にしてください。 | ".$resError;
    }
} else {
    #エラーログ作成（error.logに１実行1行で書き込み）
    $flag = "error:引数にyyyymmdd形式で日付を入れてください";
    $log->makeLog("error", $flag);
}
#終了ログ作成（result.logに１実行１行で書き込み。成功したか失敗したかを$flagを参照して記載）
$log->makeLog("result", $flag);
print_r($flag);