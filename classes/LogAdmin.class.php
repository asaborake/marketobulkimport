<?php
require_once "classes/Config.class.php";

class LogAdmin{
    public function makeLog($variation, $content){
        ob_start();
        var_dump($content);
        $e = ob_get_contents();
        ob_end_clean();

        $config = new Config();
        $log_path = $config->getLogPath();
        $file_name = $log_path.$variation.".log";
        $do_date = new DateTime('now');
        $do_date = $do_date->format('Y-m-d H:i');
        $content_array = array($do_date, $e);

        $fp = fopen($file_name,"a");
        fwrite($fp, implode(",", $content_array)."\n");
        fclose($fp);
        
        return $e;
    }
}