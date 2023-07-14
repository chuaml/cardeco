<?php 
namespace main;




use \Orders\Factory\Cashsale;
use \Exception;

$msg = '';
try{
    if(isset($_POST['dateStockOut'])){
        try{
            $CF = new Cashsale($con);
            $CF->setMonthlyRecordByDateStockOut($_POST['dateStockOut']);
            
            $list = $CF->generateRecords();
            $list = array_map(function($r){
                return $r->getData();
            }, $list);
        
            $MainFolder = 'public/txtImport_file2/';
            $targetFolder = $MainFolder .date('Y') .'/' .date('M') . '/' .date('d');
            $Date_NOW = date('d-m-Y');
            $fileName = '';
            if(!is_writeable($MainFolder)){
                throw new Exception("Unable to write file. have no permission write to dir: {$targetFolder}.");
            }
            if(!file_exists($targetFolder)){
                mkdir($targetFolder, 0777, true);
            }
            $i = 0;
        
            foreach($list as $file){
                $fileName = $Date_NOW . '_' . time() . "({$i})";
                
                $out = fopen("{$targetFolder}/{$fileName}.txt", 'wb');
                if($out === false){
                    throw new Exception("Unable to open file {$targetFolder}/{$fileName}.txt");
                }
                try{
                    if(!fwrite($out, $file)){
                        throw new Exception('Fail to write File ');
                    }
                }finally{
                    fclose($out);
                }
                ++$i;
            }
            header('HTTP/1.1 200');
            $msg = '<script>alert("Cash sales files exported at: [server]/public/ folder.")</script>';
        }catch(Exception $e){
            header('HTTP/1.1 500');
            $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES);
        }
    }
}finally{
    $con->close();
}

require('view/Cashsale.html')
?>