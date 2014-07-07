Road Runner Cache Class
===============

Index page example:

    <?php
    include('min/cache.class.php');
    
    $cache = new RoadRunner();
    
    $file = 'min/index.raw.html';
    $fileCss = 'media/c.css';
    $fileComp = 'min/tmp/index.comp';
    
    $update = $cache->checkCache(array($file,$fileCss),$fileComp);
    
    if ($update){ //cache out of date
    	$contents = $cache->openUncompressed($file);
    	$contents = $cache->append_css($fileCss,$contents);
    	$contents = $cache->compress_page($contents);
    	$cache->updateCache($fileComp,$contents);
    	$cache->compressgz($contents,$fileComp.'.gz');
    	$cache->expiresHeaders();
    	echo $cache->finalOutput();
    }
    ?>

Javascript page example:

    <?php
    header("Content-type: application/x-javascript");
    include('min/cache.class.php');
    
    $cache = new RoadRunner();
    
    $dir = 'media/js/';
    $files = array($dir.'j.js',$dir.'modernizr.custom.js',$dir.'common.js');
    $fileComp = 'min/tmp/js.comp';
    
    $update = $cache->checkCache($files,$fileComp);
    if ($update){
    	// There is no cache yet
    	foreach ($files as $file) {
    		$file = $file;
    		$fileOut[] = $cache->openUncompressed($file);
    	}
    	$compressed = $cache->compress_js($fileOut[2]);
    	$compressed = $fileOut[0].$fileOut[1].$compressed;
    	$cache->output = $compressed;
    	$cache->updateCache($fileComp,$compressed);
    	$cache->compressgz($compressed,$fileComp.'.gz');
    	$cache->expiresHeaders();
    	echo $cache->finalOutput();
    }
    ?>
