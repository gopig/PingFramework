<?php

namespace ping\log;

use ping;

use ping\helpers\FileHelper;

class FileTarget extends Target
{
    /**
     * @var string log file path or path alias. If not set, it will use the "@runtime/logs/app.log" file.
     * The directory containing the log files will be automatically created if not existing.
     */
    public $logFile;
    /**
     * @var integer maximum log file size, in kilo-bytes. Defaults to 10240, meaning 10MB.
     */
    public $maxFileSize = 10240; // in KB
    /**
     * @var integer number of log files used for rotation. Defaults to 5.
     */
    public $maxLogFiles = 5;
    /**
     * @var integer the permission to be set for newly created log files.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * If not set, the permission will be determined by the current environment.
     */
    public $fileMode;
    /**
     * @var integer the permission to be set for newly created directories.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * Defaults to 0775, meaning the directory is read-writable by owner and group,
     * but read-only for other users.
     */
    public $dirMode = 0775;

    /**
     * Initializes the route.
     * This method is invoked after the route is created by the route manager.
     */
    public function init()
    {
        parent::init();
        //FIXME 不支持修改 logFile 名称
        var_dump(Ping::$server);
        $this->logFile = (Ping::$server->runtime) . '/logs/app.log';

        $logPath = dirname($this->logFile);
        if(!is_dir($logPath))
        {
            FileHelper::createDirectory($logPath, $this->dirMode, true);
        }
        if($this->maxLogFiles < 1)
        {
            $this->maxLogFiles = 1;
        }
        if($this->maxFileSize < 1)
        {
            $this->maxFileSize = 1;
        }
    }

    public function export()
    {
        $text = implode("\n", array_map([$this, 'formatMessage'], $this->messages)) . "\n";
        if(($fp = @fopen($this->logFile, 'a')) === false)
        {
            throw new \Exception("Unable to append to log file: {$this->logFile}");
        }
        @flock($fp, LOCK_EX);
        if(@filesize($this->logFile) > $this->maxFileSize * 1024)
        {
            $this->rotateFiles();
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @file_put_contents($this->logFile, $text, FILE_APPEND | LOCK_EX);
        }
        else
        {
            @fwrite($fp, $text);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
        if($this->fileMode !== null)
        {
            @chmod($this->logFile, $this->fileMode);
        }
    }

    /**
     * Rotates log files.
     */
    protected function rotateFiles()
    {
        $file = $this->logFile;
        for($i = $this->maxLogFiles; $i > 0; --$i)
        {
            $rotateFile = $file . '.' . $i;
            if(is_file($rotateFile))
            {
                // suppress errors because it's possible multiple processes enter into this section
                if($i === $this->maxLogFiles)
                {
                    @unlink($rotateFile);
                }
                else
                {
                    @rename($rotateFile, $file . '.' . ($i + 1));
                }
            }
        }
        if(is_file($file))
        {
            @rename($file, $file . '.1'); // suppress errors because it's possible multiple processes enter into this section
        }
    }
}
