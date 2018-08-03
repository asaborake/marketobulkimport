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
        #Check CSV file exist
        if(file_exists($usage)){
            if(file_exists($import_error)){
                #Error csv file converts to array and add normal csv file
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

                #Delete error csv file
                unlink($import_error);
            }
        } else {
            throw new Exception("Error:CSV file for posting doesn't exist");
        }

        #Posting csv file
        $postData = new Posting();
        $res = $postData->doPost($usage);
        $log->makeLog("response",$res);

        if(!$res->success){
            throw new Exception("Error:CSV file posting failed");
        } else {
            $resArr = $res->result[0];
            do {
                #Polling
                if(isset($resArr->batchId)){
                    $p = new Polling();
                    $r = $p->doPoll($resArr->batchId);
                    $log->makeLog("polling", $r);
                } else {
                    throw new Exception("Error:BatchId getting failed");
                }
                #Failed is finish
                $f = $r->result[0]->status;
                if($f == "Failed"){
                    throw new Exception("Error:Polling is 'Failed' status");
                    break;
                }
                sleep(60);
            } while ($f != "Complete");

            #Generate error CSV file
            if(isset($r->result[0]->numOfRowsFailed) && $r->result[0]->numOfRowsFailed >= 1){
                $failfile = new FailFileGetting();
                $resf = $failfile->doFailFileGet($r->result[0]->batchId, $import_error);
                copy($import_error, $config->getLogPath()."ImportErrorReport_".$filedate.".csv");
                $log->makeLog("getErrLog", $resf);
            }
        }
        #Generate success log
        $resSuccess = $log->makeLog("success", $r);
        $flag = "Success:Finished,if you'd like to know the detail of process, look at the 'success.log'. | ".$resSuccess;
    } catch(Exception $e){
        #Generate error log
        $resError = $log->makeLog("error", $e->getMessage());
        $flag = "Error:Failed,if you'd like to know the detail of process, look at the 'error.log'. | ".$resError;
    }
} else {
    #Generate error log
    $flag = "Error:Argument is yyyymmdd";
    $log->makeLog("error", $flag);
}
#Generate result log
$log->makeLog("result", $flag);
print_r($flag);