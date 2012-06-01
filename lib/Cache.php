<?php

namespace plugins\riCache;

use plugins\riPlugin\Plugin;

/**
 * @package Pages
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: cache.php 274 2009-11-20 17:13:45Z yellow1912 $
 */
class Cache {
	protected $cache, $path, $status, $blocks = array();

	public function __construct($path = null){
	    $this->path = empty($path) ? DIR_FS_CATALOG . Plugin::get('riPlugin.Settings')->get('riCache.path') : $path;
	    $this->status = Plugin::get('riPlugin.Settings')->get('riCache.status');
	}
	
	public function getPath(){
	   return $this->path; 
	}
	
	public function write($name, $cache_folder, $content, $use_subfolder = false){
		
		if(!$this->status) return false;
		
		$this->cache[$cache_folder][$name] = $content;
			
		$cache_folder = $this->calculatePath($cache_folder, $use_subfolder);
		
		$cache_file = "$cache_folder/$name";
		
		$written = 0;
	    if ($fp = @fopen($cache_file, 'wb')) {

	        // lock file for writing
			if (flock($fp, LOCK_EX)) {
				$written = fwrite($fp, $content);
			}
			fclose($fp);

			// Set filemtime
			touch($cache_file, time() + 3600);
			
			//@chmod($cache_file, 0777);			
		}		
						
		return $written !== false && $written > 0 ? $cache_file : false;
	}

	public function read($name, $cache_folder ='', $use_subfolder = false){
		
		if(!$this->status) return false;
		
		if(isset($this->cache[$cache_folder][$name]))
			return $this->cache[$cache_folder][$name];
			
		$cache_folder = $this->calculatePath($cache_folder, $use_subfolder);
		
		$cache_file = "$cache_folder/$name";
			
		$read = @file_get_contents($cache_file);
		
		return $read ? $read : false;
	}
	
	public function remove($name = '', $cache_folder, $DeleteMe = false){
	    if(empty($name)){
    	    $counter = 0;
    	    Plugin::get('riUtility.File')->sureRemoveDir($this->path . $cache_folder, $DeleteMe, $counter);
    	    return $counter;
	    }
	    else 
	        return @unlink($this->path . $cache_folder . $name);
	}
	
	public function startBlock($id, $change_on_page = false, $depend_on = "", $post_safe = true){
		if(!$this->status || (!$post_safe && $_SERVER["REQUEST_METHOD"] == 'POST') || isset($_GET[zen_session_id()]))
			return false;
                 
		if($change_on_page) $id .=  getenv('REQUEST_URI');
		
		$id = md5($id.$depend_on);
		
		if(($content = $this->read($id, 'content')) !== false){
			echo $content;
			return true;
		}
		
		$this->blocks[] = $id;
        
		ob_start();
		return false;
	}		
	
	public function startPage($depend_on = "", $post_safe = false){
		if(!$this->status || ($post_safe && $_SERVER["REQUEST_METHOD"] == 'POST') || isset($_GET[zen_session_id()]))
			return false;
			
		global $current_page_base;
		
		$id = md5($current_page_base.getenv('REQUEST_URI').$depend_on);
		
		if(($content = $this->read($id, 'content')) !== false){
			echo $content;
			return true;
		}
		
		$this->blocks[] = $id;
		
		ob_start();
		return false;
	}

	public function end(){
		if(!$this->status)
			return false;
			
		$id = array_pop($this->blocks); 
		$content = ob_get_clean();        
        $this->write($id, 'content', $content);
        
        echo $content;
	}
	
	public function exists($name, $cache_folder = '', $use_subfolder = false){
		$cache_folder = $this->calculatePath($cache_folder, $use_subfolder);
		return file_exists("$cache_folder/$name") ? "$cache_folder/$name" : false;
	}					
	
	private function calculatePath($cache_folder, $use_subfolder){
	    $cache_folder = $this->path."$cache_folder/";
		if($use_subfolder){
			$path = substr($name , 0, 4);
			$cache_folder .= chunk_split($path, 1, '/');
		}
			
		$cache_folder = rtrim($cache_folder, '/');
		
		if(!is_dir($cache_folder)){
			$old_umask = umask(0);
			@mkdir($cache_folder, 0777, true);
			umask($old_umask);
		}
		return $cache_folder;
	}
}