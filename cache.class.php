<?php
/**
 * RoadRunner v1.5.1
 * This cache class will compress the contents and save them.
 * It will also make a GZip version and save that too.
 * It will then display the right version depending on if the
 * browser supports it.
 * 
 * @author Matthew Burns http://mattandceri.info
 * @copyright 2014
 */
class RoadRunner{
    
    /**
     * Set everything up
     * @return void
     */
    public function __construct() {
        header("Cache-Control: private, max-age=31536000");
        header("X-Powered-By: RoadRunner Cache Class v1.5.1");

        // Check to see if the client is requesting a new copy
        $this->ifMod = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? 
            strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : 
            0);

        // Check to see if the client supports gzip
        $this->supportsGzip = (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? 
            strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false : 
            0);

        $this->output = '';
        $this->compressedOutput = '';
    }

    /**
     * Cleans up the used vars
     * @return void
     */
    public function __destruct() {
        unset(
            $this->ifMod,
            $this->supportsGzip,
            $this->output,
            $this->compressedOutput
        );
    }

    /**
     * Checks the cache to see if it should be updated
     * will also send a 304 if fine
     * @param array $files name of the files
     * @param string $file name of the compressed file
     * @return bool
     */
    public function checkCache(array $files,$comp) {
        // Get a few vars setup
        $compmtime = (file_exists($comp) ? filemtime($comp) : 0);
        $updateCache = 0;

        // See if any of the files have a newer mod time
        // var $files has to be an array()
        foreach ($files as $file) {
            if(file_exists($file)){
                $filesmtime[][$file] = filemtime($file);
            }
        }

        foreach ($filesmtime as $key => $file) {
            foreach ($file as $key2 => $value) {
                $mtime = filemtime($key2);
                if(($mtime-$compmtime)>0){
                    $updateCache = 1;
                    break;
                }
            }
        }

        // need to check ifMod and if $comp exsists
        // also check if files have newer mod time
        // send 304 if old files
        if ($this->ifMod == $compmtime && file_exists($comp) && $updateCache==0) {
            $this->send304($compmtime);
        }

        // client has requested new data or $comp doesnt exsist or cache out of date
        // let the script calling this class decide how to update the cache
        if (!file_exists($comp) || $updateCache==1) {
            return 1;
        }

        //client has requested new data and $comp does exsist and cache is in date
        if (file_exists($comp) && $updateCache==0) {
            $this->loadCache($comp);
            $this->loadCompressGz($comp.'.gz');

            //return the results to the browser
            $this->expiresHeaders($compmtime);
            echo $this->finalOutput();
            return 0;
        }
    }

    /**
     * Determins the output that should be used
     * will use gzip if the client accepts it
     * @return string
     */
    public function finalOutput() {
        if($this->supportsGzip){
            $this->sendGzip();
            return $this->compressedOutput;
        } else {
            return $this->output;
        }
    }

    /**
     * Compresses a html page
     * Turned the colon removal off by default as it might be in normal text
     * @param string $buffer data to fix
     * @param string $type type of compression
     * @return string
     */
    public function compressPage($buffer,$type='half') {

        // Remove comments (mostly CSS comments)
        $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);

        // Remove HTML Comments
        $buffer = preg_replace('/<!--(.*)-->/Uis', '', $buffer);

        if ($type=='full') {
            // Remove space after colons
            $buffer = str_replace(': ', ':', $buffer);
        }

        // Remove whitespace
        $buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '   ', '    '), '', $buffer);

        // Save for later
        $this->output = $buffer;

        return $buffer;
    }

    /**
     * Compresses JavaScript
     * Uses the JShrink package by Robert Hafner <tedivm@tedivm.com>
     * url: https://github.com/tedious/JShrink
     * note: don't compress already minified source
     * eg. JQuery, JQueryUI, etc.
     * @param string $raw data to compress
     * @return string
     */
    public function compressJs($raw) {
        require_once('min/jshrink.php');
        $compressed = \JShrink\Minifier::minify($raw,array('flaggedComments' => false));

        // Remove whitespace
        $compressed = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '   ', '    '), '', $compressed);

        // Save for later
        $this->output = $compressed;

        return $compressed;
    }

    /**
     * Makes the GZip file of the already compressed file
     * @param string $data data to save
     * @param string $file name of the file
     * @return string
     */
    public function compressGz($data,$file) {
        $contents = gzencode(trim(preg_replace( '/\s+/', ' ', $data)),9);

        // save the result
        $fp = fopen($file, 'w');
        fwrite($fp, $contents);
        fclose($fp);

        $this->compressedOutput = $contents;

        return $contents;
    }

    /**
     * Appends the CSS to the HTML string using the key
     * @param string $fileCss name of the file
     * @param string $contents html to append to
     * @return string
     */
    public function appendCss($fileCss,$contents) {
        //Grab the css
        $fp = fopen($fileCss, 'r');
        $css = fread($fp, filesize($fileCss));
        fclose($fp);

        //Compress the CSS with colons
        $css = $this->compressPage($css,'full');

        return preg_replace('#%XXX%#', $css, $contents);
    }

    /**
     * Loads the GZip version of the compressed file
     * @param string $file name of the file
     * @return void
     */
    public function loadCompressGz($file) {

        if(file_exists($file)){

            // Load the compressed cache from the file
            $fp = fopen($file, 'r');
            $contents = fread($fp, filesize($file));
            fclose($fp);

            $this->compressedOutput = $contents;

            return;

        } else {
            
            // check if the output has already been minified
            if (empty($this->output)) {
                // if not grab the minfied save file
                $output2 = $this->loadCache(substr($file,0,-3));
                $contents = $this->compressGz($output2,$file);

                return;
            }else{
                // Output has the minified source
                $data = $this->output;
                $contents = $this->compressGz($data,$file);

                return;
            }
        }
    }

    /**
     * Saves out the compressed file
     * @param string $file name of the file
     * @param string $data data to save
     * @return void
     */
    public function updateCache($file,$data) {
        //Save out the cached copy
        $fp = fopen($file,'w');
        fwrite($fp,$data);
        fclose($fp);
    }

    /**
     * Loads the minified file
     * returns string if sucessful
     * @param string $file name of the file
     * @return string|void return the contents of the file
     */
    public function loadCache($file) {
        // Load the cache from file
        if(file_exists($file)){
            $fp = fopen($file,'r');
            $cache = fread($fp,filesize($file));
            fclose($fp);

            // Echo out the result
            $this->output = $cache;

            return $cache;
        } else {
            return;
        }
    }

    /**
     * Opens the raw, uncompressed source file
     * @param string $file name of the file
     * @return string
     */
    public function openUncompressed($file) {
        $fp = fopen($file,'r');
        $contents = fread($fp, filesize($file));
        fclose($fp);

        return $contents;
    }

    /**
     * Easy way to send a 304 header
     * @param string $time unix time to send to the client
     * @return void
     */
    public function send304($time) {
        header('HTTP/1.1 304 Not Modified');
        header("Expires: ".gmdate('D, d M Y H:i:s \G\M\T', time() + (3600 * 24 * 365)));
        header("Last-Modified: ".gmdate('D, d M Y H:i:s \G\M\T', $time));

        // This will be all the content we send
        exit;
    }

    /**
     * Specify that the content sent will be GZip
     * @return void
     */
    public function sendGzip() {
        header("Content-Encoding: gzip");
    }

    /**
     * Sends the expires headers
     * @param string $time unix time to send to the client
     * @return void
     */
    public function expiresHeaders($time=0) {
        // Cache for a year
        header("Expires: ".gmdate('D, d M Y H:i:s \G\M\T', time() + (3600 * 24 * 365)));

        // Send Last-Modified as right now for a brand new file
        // or when the compressed file was last generated
        if($time==0){
            header("Last-Modified: ".gmdate('D, d M Y H:i:s \G\M\T', time()));
        } else {
            header("Last-Modified: ".gmdate('D, d M Y H:i:s \G\M\T', $time));
        }
    }
}
?>
