<?php

namespace App\Fast;

use Illuminate\Foundation\Application;


class FastApplication extends Application{

    public function getCachedConfigPath()
    {
        return $this->storagePath().'bootstrap/cache/config.php';
    }
    public function getCachedRoutesPath()
    {
        return $this->storagePath().'bootstrap/cache/routes.php';
    }
    public function getCachedCompilePath()
    {
        return $this->storagePath().'bootstrap/cache/compiled.php';
    }
    public function getCachedServicesPath()
    {
        return $this->storagePath().'bootstrap/cache/services.php';
    }


    public function storagePath(){
        $configDirList = explode('/',$_SERVER['SITE_CACHE_DIR']);
        foreach($configDirList as $key => $tempStr){
            if(isset($tempStr[0]) && $tempStr[0] == '$'){
                $configDirList[$key] = 'fast.youxinjinrong.com';
            }
        }
        $configDirList = implode('/', $configDirList);
        $storageDir =  rtrim($configDirList, '/').'/storage';

        //针对文件和文件夹特殊处理
        $storageDirInfo = pathinfo($storageDir);
        if(isset($storageDirInfo['extension']) && !empty($storageDirInfo['extension'])){
            $dir = $storageDirInfo['dirname'];
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
                chmod($dir, 0777);
            }
            if(!is_file($storageDir) && !file_exists($storageDir)){
                file_put_contents($storageDir,'');
                chmod($storageDir, 0777);
            }
        }else{
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0777, true);
                chmod($storageDir, 0777);
            }
        }
        $storageDir = rtrim($storageDir, '/');
        $bootstrapCacheDir= $storageDir.'/bootstrap/cache';
        if (!is_dir($bootstrapCacheDir)) {
            mkdir($bootstrapCacheDir, 0777, true);
            chmod($bootstrapCacheDir, 0777);
        }
        return $storageDir.'/';
    }

}