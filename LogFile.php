<?php
class LogFile {

    function __construct($fileName, $mode = 'w', $autodelete = 0) {
        $this->fileName = $fileName;
        $this->file = @fopen($fileName, $mode);
        $this->autoDelete = $autodelete;
    }

    function __destruct() {
        if ($this->file)
            fclose($this->file);
        if ($this->autoDelete)
            @unlink($this->fileName);
    }
    
    public function fileExists()
    {
        return file_exists($this->fileName);
    }

    public function isValid() {
        return !empty($this->file);
    }

    public function writeString($line) {
        if ($this->file) {
            $dt = new \DateTime("now", new \DateTimeZone("Europe/Moscow"));
            $msg = ++$this->row . ".(" . $dt->format('Y-m-j H:i:s') . ") $line\n";
            fwrite($this->file, $msg, strlen($msg));
        }
    }

    public function rewriteString($line) {
        if ($this->file) {
            fseek($this->file, 0);
            fwrite($this->file, $line, strlen($line));
        }
    }

    protected $file = null;
    protected $row = 0;
    protected $autoDelete = false;
    protected $fileName = '';
}
