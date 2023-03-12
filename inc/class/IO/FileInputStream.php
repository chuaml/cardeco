<?php 
namespace IO;

use Exception;
use Generator;

class FileInputStream{
    protected $IO;
    private $file;
    private $bufferLength = 8192;

    const OPEN_MODE = 'rb';
    public function __construct(string $file){
        if(!file_exists($file)){
            throw new Exception("File does not exist: {$file}.");
        }
        $openMode = self::OPEN_MODE;
        if(($this->IO = fopen($file, self::OPEN_MODE)) === false){
            throw new Exception("Fail to open IO Stream file: {$file}, open mode: {$openMode}");
        }
        $this->file = $file;
    }

    public function read():?string{
        $data = fgetc($this->IO);
        if($data === false){
            return null;
        }
        return $data;
    }

    public function readSize(int $byteLength):?string{
        $data = fread($this->IO, $byteLength);
        if($data === false){
            return null;
        }
        return $data;
    }

    public function readLine():Generator{
        $line = fgets($this->IO, $this->bufferLength);
        if($line === false){
            return;
        }
        yield $line;
    }

    public function setBufferLength(int $bufferLength):void{
        if($bufferLength > 0){
            $this->bufferLength = $bufferLength;
        }
    }

    public function close():void{
        if(is_resource($this->IO) === true){
            if(\fclose($this->IO) === false){
                throw new Exception('Fail to close file: ' .$this->file);
            }
        }
    }

    public function __destruct(){
        $this->close();
    }
}
