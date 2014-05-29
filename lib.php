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

require_once($CFG->dirroot . '/config.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_once(dirname(__FILE__).'/tripleplay.php');
require_once(dirname(__FILE__).'/version.php');

class repository_tripleplay extends repository {

	public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array('ajax'=>true)){
		parent::__construct($repositoryid, $context, $options);
		$this->tripleplay = new tripleplay();
	}

	/**
	 * Function to get content from the portal
	 * @return array with the list of content to be displayed
	 */
	public function get_listing($path = '', $page = '1') {
		global $USER;

		if ($this->getUrl() == '') {
			throw new repository_exception('repositoryerror', 'repository', '', get_string('ip_missing', 'repository_tripleplay'));
		}

		$permission = json_decode($this->tripleplay->registerClient($this->getUrl(), $USER->username));

		if ($permission == NULL) {
			throw new repository_exception('repositoryerror', 'repository', '', get_string('error_connection', 'repository_tripleplay'));
		}

		if (isset($permission->error)) {
			throw new repository_exception('repositoryerror', 'repository', '', get_string('error_permissions', 'repository_tripleplay'));
		}


		$ret['dynload']  = true;
		$ret['logouttext']  = get_string('upload', 'repository_tripleplay');
		$ret['path'] = array(array('name'=>get_string('portal', 'repository_tripleplay'), 'path'=>'/'));


		if (empty($path) || $path=='/') {
			$path = '/';
			$init_list = $this->tripleplay->getMenu();
			foreach($init_list as $item){
				$list[] = array(
						'title' 	=> $item['title'], 
						'thumbnail' => $this->defaultFolder(), 
						'path' 		=> $path.$item['title'].'/', 
						'children'	=>array()
					       );
			}

			$ret['list'] = $list;
			return $ret;
		} else {
			$path = file_correct_filepath($path);
		}

		$result = $this->tripleplay->getListing($this->getUrl(), $path);

		if (empty($result->path)) {
			$current_path = '/';
		} else {
			$current_path = file_correct_filepath($result->path);
		}

		$trail = '';
		if (!empty($path)) {
			$parts = explode('/', $path);
			if (count($parts) > 1) {
				foreach ($parts as $part) {
					if (!empty($part)) {
						$trail .= ('/'.$part);
						$ret['path'][] = array('name'=>$part, 'path'=>$trail);
					}
				}
			} else {
				$ret['path'][] = array('name'=>$path, 'path'=>$path);
			}
		}

		$result = json_decode($result);

		$init_path = explode("/", $path);
		switch ($init_path[1]) {
			case 'Browse':
				$list = $this->getBrowse($result, $path);
				break;
			case 'Live TV':
				$list = $this->getTv($result, $path);
				break;
			case 'My Bookmarks':
				$list = $this->getContent($result, $path);
				break;
			case 'My Content':
				$list = $this->getContent($result, $path);
				break;
			default:
				if (isset($result->error)) {
					throw new repository_exception('repositoryerror', 'repository', '', $result->error->message);
				}else{
					$list = array();
				}
				break;
		}

		$ret['list'] = $list;
		return $ret;
	}

	/**
	 * List for My Content
	 * @return array
	 */
	public function getContent($result, $path) {
		if (empty($result->result) || !isset($result->result)) {
			if (isset($result->error)) {
				throw new repository_exception('repositoryerror', 'repository', '', $result->error->message);
			}	
			return array();
		}

		foreach ($result->result as $item) {
			$list[] = $this->getVodInfo($item);
		}

		return $list;
	}


	/**
	 *	List for TV 
	 *  @return array
	 */
	public function getTv($result, $path) {
		if (empty($result->result)) {
			if (isset($result->error)) {
				throw new repository_exception('repositoryerror', 'repository', '', $result->error->message);
			}
			return array();
		}else{
			foreach ($result->result as $channel) {
				$title 			= $channel->name;
				$id    			= $channel->channelNumber;
				$iconPath 		= $this->getThumbPath('/portalImages/services/'.$id.'_v2.png');
				$source 		= $this->getSrcFile($path, $id);
				$list[] = array(
						'title' 	 => $title.'.avi',
						'shorttitle' => $title,
						'thumbnail'  => $iconPath,
						'icon' 		 => $this->defaultTv(),
						'source' 	 => $source. '#' . $title,
						'thumbnail_height' => 130,
						'thumbnail_width'  => 130           
					       );
			}
			return $list;
		}
	}

	/**
	 * List to browse categories and families
	 * @return array
	 */
	public function getBrowse($result, $path) {
		if (empty($result->result) || !isset($result->result)) {
			if (isset($result->error)) {
				throw new repository_exception('repositoryerror', 'repository', '', $result->error->message);
			}	
			return array();
		}

		foreach ($result->result as $vodItem) {

			/** FOLDER **/
			if ($vodItem->type == '1') {
				if($vodItem->id != '' || $vodItem->id != null){
					$title       = $vodItem ->name;
					$list[]      = array(
							'title' => $title,
							'path'  => $path.$vodItem->id.'/',
							'thumbnail' => $this->defaultFolder(),
							'children' 	=> array()
							);
				}
				/** FILE **/
			}elseif ($vodItem->type == '2') {
				$list[] = $this->getVodInfo($vodItem);
			}
		}

		if (!isset($list)) {
			$list = array();
		}
		return $list;
	}

	/**
	 * Overriding search method to get search results from the portal
	 * @return array
	 */
	public function search($search_text, $page = 0) {
		global $USER;

		$permission = json_decode($this->tripleplay->registerClient($this->getUrl(), $USER->username));

		if (isset($permission->error) ) {
			throw new repository_exception('repositoryerror', 'repository', '', $permission->error->message);
		}

		$ret['logouttext']  = get_string('upload', 'repository_tripleplay');

		$result = json_decode($this->tripleplay->search($this->getUrl(), $search_text));

		if (isset($result->error)) {
			$list = array();
			$ret['list'] = $list;
			return $ret;
		}else{
			$list = array();
		}

		foreach ($result->result as $items) {
			if (empty($items)) {
				$ret['list'] = array();
				return $ret;
			}
			foreach($items as $vodItem){
				$list[] = $this->getVodInfo($vodItem);
			}
		}

		$ret['list'] = $list;
		return $ret;
	}

	/**
	 * Generates an array with the item information
	 * @return array
	 */
	public function getVodInfo($vodItem){

		$title 			= $vodItem->name;
		$id    			= $vodItem->id;
		$iconPath 		= $this->getThumbPath($vodItem->iconPath, 1);
		$description 	= $vodItem->synopsis;
		$owner	     	= $vodItem->owner;

		foreach ($vodItem->information as $information) {
			$duration 	= $information->value;
		}

		$metadata = array(
				"mtitle" 	=> $title,
				"msynopsis" => $description ,
				"mduration" => $duration ,
				"mowner" 	=> $owner,
				"murl" 		=> $id,
				);

		$source = $this->getSrcFile($path, $id, $metadata);

		$list = array(
				'shorttitle' => $title,
				'title' 	 => $title.'.avi',
				'thumbnail'  => $iconPath,
				'thumbnail_height' => 130,
				'thumbnail_width'  => 130,
				'icon' => $iconPath,
				'license' 	 => 'Other',
				'author' 	 => $owner,
				'source' 	 => $source. '#' . $title,
			     );

		return $list;
	}

	/**
	 * Generate the source for "files", depending on the type of content
	 * @return string
	 */
	public function getSrcFile($path, $parameters, $metadata) {

		if (strpos($path, '/Live TV/') === 0) {
			$query = 'component=WatchTV&channelNumber='.$parameters;
		}else{
			$query = 'component=WatchVideo&vodItem='.$parameters;
		}

		$host = $this->getUrl();
		if (strpos($host, 'http://') === 0) {
			$url = $host.'/portal/standalone.php?';
	}else{
		$url = 'http://'.$host.'/portal/standalone.php?/';
	}

	$data = $this->getMetadata($metadata);

	if (!empty($data) && strlen($data) > 7 ) {
		$url .= base64_encode($query.$data);
	}else{
		$url .= base64_encode($query);
	}

	return $url;
	}

	/**
	 * If metadata is required, returns a string
	 * @return string
	 */
	public function getMetadata($metadata){

		$mtitle 	= get_config('tripleplay', 'mtitle');
		$msynopsis 	= get_config('tripleplay', 'msynopsis');
		$mduration 	= get_config('tripleplay', 'mduration');
		$mowner 	= get_config('tripleplay', 'mowner');
		$murl 		= get_config('tripleplay', 'murl');

		$data = "extra=";

		if ($mtitle != 0)
			$data .= "&title=".$metadata["mtitle"];

		if ($msynopsis != 0)
			$data .= "&synopsis=".$metadata["msynopsis"];

		if ($mduration != 0)
			$data .= "&duration=".$metadata["mduration"];

		if ($mowner != 0)
			$data .= "&owner=".$metadata["mowner"];

		if ($murl != 0)
			$data .= "&url=".$metadata["murl"];

		return $data;
	}

	/**
	 * Returns the full path for thumbnails
	 * @return string
	 */
	public function getThumbPath ($iconPath, $type) {
		global $CFG;

		if ($iconPath == 'undefined' || $iconPath == '') {
			if ($type == "1") {
				return $this->defaultVideo();
			}else{
				return $this->defaultTv();
			}
		}else{
			if (getimagesize($this->getUrl().$iconPath) !== false) {
				return $this->getUrl().$iconPath;
			}else{
				if ($type == "1") {
					return $this->defaultVideo();
				}else{
					return $this->defaultTv();
				}
			}
		}
	}


	/**
	 * Method to return the url for tpportal from admin settings if defined
	 * @return string
	 */
	public function getUrl(){
		$url = get_config('tripleplay', 'host');

		if (empty($url))
			return $url;

		if (strpos($url, 'http://') === false) {
			$url = 'http://'.$url;
	}elseif (substr($url, -1) == "/" ) {
		$url = substr_replace($url ,"",-1);
	}

	return $url;
	}


	/**
	 * Returns default folder image
	 * @return string
	 */
	public function defaultFolder(){
		global $CFG;
		return $CFG->wwwroot.'/repository/tripleplay/pix/folder.png';
	}

	/**
	 * Returns default tv image
	 * @return string
	 */
	public function defaultTv(){
		global $CFG;
		return $CFG->wwwroot.'/repository/tripleplay/pix/tv.png';
	}

	/**
	 * Returns default video image
	 * @return string
	 */
	public function defaultVideo(){
		global $CFG;
		return $CFG->wwwroot.'/repository/tripleplay/pix/video.png';
	}

	/**
	 * file types supported 
	 * @return array
	 */
	public function supported_filetypes() {
		return array('video');
	}

	/**
	 * External links only
	 * @return int
	 */
	public function supported_returntypes() {
		return FILE_EXTERNAL;
	}

	/**
	 * Overriding print_login to display upload form
	 * @return array
	 */
	public function print_login ($ajax = true) {
		global $USER;
		$ret = array(
				'object' => array(
					'type' => 'text/html',
					'src' => $this->getUrl()."/portal/standalone.php?".base64_encode("connectUsername=".$USER->username."&component=ContentUploadForm&onUploadCompletion=location.reload(true)&extraCss=css/upload_vle.css")
					)
			    );

		$ret['nosearch'] = true;
		$ret['nologin'] = true;
		$ret['logouttext']  = get_string('content', 'repository_tripleplay');

		return $ret;
	}



	/* GLOBAL ADMIN SETTINGS */

	/*we use Global Settings due to the permissions required by triplecare */

	/**
	 * Options of the plugin defined by the Admin
	 * @return array
	 */
	public static function get_type_option_names() {
		return array('host','pluginname', 'mtitle', 'msynopsis', 'mowner', 'murl', 'mduration');
	}

	/**
	 * Moodle form displaying plugin settings
	 * 
	 */
	public static function type_config_form($mform){
		parent::type_config_form($mform);
		$mform->addElement('text'	 , 'host'	  , get_string('host_desc', 'repository_tripleplay'));
		$mform->addElement('static'	 , 'metadata' , get_string('metadata' , 'repository_tripleplay'), null);
		$mform->addElement('checkbox', 'mtitle'	  , get_string('mtitle'	  , 'repository_tripleplay'));
		$mform->addElement('checkbox', 'msynopsis', get_string('msynopsis', 'repository_tripleplay'));
		$mform->addElement('checkbox', 'mduration', get_string('mduration', 'repository_tripleplay'));
		$mform->addElement('checkbox', 'mowner'	  , get_string('mowner'	  , 'repository_tripleplay'));
		$mform->addElement('checkbox', 'murl'	  , get_string('murl'	  , 'repository_tripleplay'));

	}

	/*END OF ADMIN SETTINGS */
}
