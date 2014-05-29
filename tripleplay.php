<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 
/**
 *
 * @package    repository_tripleplay
 * @copyright  2014 Tripleplay Services Ltd.
 * @author     Nuno Horta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class tripleplay{

	/**
	 * method to register and authenticate the client using credentials from moodle
	 * @return string 
	 */
	public function registerClient($url, $username) {
		global $client_id;

		$browser_name = $this->get_browsername();
		$method = "RegisterPcClientEx";
		$params = " [ \"".$_SERVER['REMOTE_ADDR']."\",
			\"no mac (".$_SERVER['REMOTE_ADDR'].")\",
			\"".PHP_OS."\",
			\"".$browser_name."\",
			\"null\",
			\"null\",
			\"null\"
				] ";

		$result = json_decode($this->getContent($url, $this->getJson($method, $params)));

		if (!isset($result->result)) {
			return $result;
		}else{
			$client_id = $result->result->clientId;
		}

		if ($client_id) {
			return $this->setCredentials($client_id, $username, $url);
		}
	}

	/**
	 * Set credentials after registering the client
	 * @return string
	 */
	public function setCredentials($client_id, $username, $url) {
		$method = "SetConnectCredentials";
		$params = "[".$client_id.", \"".$username."\"]";

		$result = $this->getContent($url, $this->getJson($method, $params));

		return $result;
	}

	/**
	 * Get file/dir listing from triplecare
	 *
	 * @param string $url
	 * @param string $path
	 * @return array
	 */
	public function getListing($url, $path) {
		$params = $this->getParameters($path);
		$data   = $this->getContent($url, $params);
		return $data;
	}

	/**
	 * Get items from search
	 * @param string $url
	 * @param string $search_text
	 */
	public function search($url, $search_text) {
		global $client_id;

		$method = "SearchItems";
		$params = "[".$client_id.", \"".$search_text."\", null, [\"duration\"]]"; 

		return $this->getContent($url, $this->getJson($method, $params));
	}

	/**
	 * Initial listing for the plugin
	 * @return array
	 */
	public function getMenu() {
		return array( 
				array( "title" => 'Live TV'),
				array( "title" => 'Browse'),
				array( "title" => 'My Content'),
				array( "title" => 'My Bookmarks') 
			    );
	}

	/**
	 * Builds JSON raw string
	 * @param string $method
	 * @param string $params
	 * @return string
	 */
	public function getJson($method, $params) {
		return '{
			"jsonrpc":"2.0",
				"id":null,
				"method":"'.$method.'",
				"params":'.$params.'
		}';
	}

	/** 
	 * Handles the type of method and the parameters to request triplecare
	 *
	 * @param string $path
	 * @return string
	 */
	public function getParameters($path) {
		global $client_id;

		$init_path = explode("/", $path);

		if ($init_path[1] == 'Browse') {
			$method = "GetItems";
			$params = "[".$client_id." , \"".substr($path, 7)."\", [\"duration\"]]"; 
		}elseif ($init_path[1] == 'Live TV') {
			$method = "GetAllServices";
			$params = "[".$client_id."]"; 
		}elseif ($init_path[1] == 'My Content') {
			$method = "GetUsersItems";
			$params = "[".$client_id.", null, [\"duration\"]]";
		}elseif ($init_path[1] == 'My Bookmarks') {
			$method = "GetUsersBookmarks";
			$params = "[".$client_id.", null, [\"duration\"]]";
		}

		return $this->getJson($method, $params);
	}

	/**
	 * Request content to triplecare
	 *
	 * @param string $url
	 * @param string $params
	 * @return string
	 */
	public function getContent($url, $params){

		$url = $url."/triplecare/JsonRpcHandler.php";

		$ch = curl_init($url);                                                                      
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params );                                                                
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
					'Content-Type: application/json',                                                                                
					'Content-Length: ' . strlen($params))                                                                       
			   );                                                                                                                   

		$result = curl_exec($ch);
		return $result;
	}

	public function get_browsername(){
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== FALSE){
			$browser = 'Microsoft Internet Explorer';
		}elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) {
			$browser = 'Google Chrome';
		}elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== FALSE) {
			$browser = 'Mozilla Firefox';
		}elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== FALSE) {
			$browser = 'Opera';
		}elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== FALSE) {
			$browser = 'Apple Safari';
		}else {
			$browser = 'browser'; //<-- Browser not found.
		}
		return $browser;
	}

}
