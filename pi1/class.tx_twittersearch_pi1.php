<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Thomas Loeffler <typo3@tomalo.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
require_once(PATH_tslib . 'class.tslib_pibase.php');
/**
 * Plugin 'Twitter Search' for the 'twittersearch' extension.
 *
 * @author	Thomas Loeffler <typo3@tomalo.de>
 * @package	TYPO3
 * @subpackage	tx_twittersearch
 */
class tx_twittersearch_pi1 extends tslib_pibase {
	var $prefixId = 'tx_twittersearch_pi1'; // Same as class name
	var $scriptRelPath = 'pi1/class.tx_twittersearch_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'twittersearch'; // The extension key.
	var $debug = FALSE;

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_initPIflexForm();
		$this->getFFconfig();
		// 091010: added debug functionality for developing
		$this->debug = $this->conf['debug'];
		// template file
		if ($this->conf['templatefile']) {
			$template = $this->cObj->fileResource($this->conf['templatefile']);
		} else {
			$template = $this->cObj->fileResource('EXT:' . $this->extKey . '/res/templates/list.html');
		}
		// max results per page
		$this->max_results_per_page = ($this->conf['sVIEW.']['max_results_per_page'] ? $this->conf['sVIEW.']['max_results_per_page'] : '5');
		// force utf-8 decode?
		$this->useUtf8Decode = ($this->conf['sCONFIG.']['force_utf8_decode'] ? $this->conf['sCONFIG.']['force_utf8_decode'] : FALSE);
		$temp = '';
		if ($tweets = $this->buildSearchParts()) {
			// get first block and replace marker: 
			$template_part = $this->cObj->getSubpart($template, '###TEMPLATE_LIST###');
			$tweet_template = $this->cObj->getSubpart($template_part, '###TWEET###');
			foreach ($tweets as $tweet) {
				$temp .= $this->cObj->substituteMarkerArray($tweet_template, $tweet, '###|###', TRUE, TRUE);
			}
			$main_content = $this->cObj->substituteSubpart($template_part, '###TWEET###', $temp);
			$main_content = $this->cObj->substituteMarker($main_content, '###HEADER###', $this->global_result['title']);
			if ($this->max_results_per_page < $this->conf['sVIEW.']['max_results']) {
				$pagebrowser_template = $this->cObj->getSubpart($template, '###TEMPLATE_PAGEBROWSER###');
				$markerArray = $this->buildPageBrowserMarker();
				$pagebrowser = $this->cObj->substituteMarkerArray($pagebrowser_template, $markerArray);
			}
			if ($pagebrowser) {
				$main_content = $this->cObj->substituteMarker($main_content, '###PAGEBROWSER###', $pagebrowser);
			} else {
				$main_content = $this->cObj->substituteMarker($main_content, '###PAGEBROWSER###', '');
			}
		} else {
			$template_part = $this->cObj->getSubpart($template, '###TEMPLATE_NORESULT###');
			$main_content = $this->cObj->substituteMarker($template_part, '###NORESULT###', $this->pi_getLL('no_result'));
		}
		$content .= $main_content;
		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * Building the parts of the search
	 *
	 * @return	The result of the function makeUrl()
	 */
	function buildSearchParts() {
		$part = array();
		if ($this->conf['sDEF.']['user_search']) { //search for user(s)
			$users = t3lib_div::trimExplode(',', $this->conf['sDEF.']['user_search']);
			foreach ($users as $user) {
				$part['user'][] = $user;
			}
		}
		if ($this->conf['sDEF.']['word_search']) { //search for word(s)
			$words = t3lib_div::trimExplode(',', $this->conf['sDEF.']['word_search']);
			foreach ($words as $word) {
				$part['word'][] = $word;
			}
		}
		if ($this->conf['sDEF.']['hash_search']) { //search for hash(es)
			$hashes = t3lib_div::trimExplode(',', $this->conf['sDEF.']['hash_search']);
			foreach ($hashes as $hash) {
				$part['hash'][] = $hash;
			}
		}
		if ($this->conf['sDEF.']['language']) { //search for user(s)
			$languages = t3lib_div::trimExplode(',', $this->conf['sDEF.']['language']);
			foreach ($languages as $language) {
				$part['language'][] = $language;
			}
		}
		if (sizeof($part) > 0) {
			return $this->makeUrl($part);
		} else {
			return FALSE;
		}
	}

	/**
	 *  Building the url for the search
	 *
	 * @param	string		$part: url parts for the search
	 * @return	Every single tweet
	 */
	function makeUrl($part) {
		$base_url = 'http://search.twitter.com/search.'; // base url
		if ($this->conf['format']) { // format atom or json
			$url = $base_url . $this->conf['format'];
		} else {
			$url = $base_url . 'atom';
		}
		$url .= '?';
		$first_query = FALSE;
		if (is_array($part['hash'])) { // building the query for searching hashes
			foreach ($part['hash'] as $key => $hash) {
				if ($key === 0 and $first_query == FALSE) {
					$url .= 'q=%23' . urlencode($hash);
					$first_query = TRUE;
				} elseif ($key > 0) {
					$url .= ($this->conf['sDEF.']['hash_search_andor'] == 'OR' ? '+OR' : '') . '+%23' . urlencode($hash);
				} else {
					$url .= '&q=%23' . urlencode($hash);
				}
			}
		}
		if (is_array($part['word'])) { // building the query for searching words
			foreach ($part['word'] as $key => $word) {
				if ($key === 0 and $first_query == FALSE) {
					$url .= 'q=' . urlencode($word);
					$first_query = TRUE;
				} elseif ($key > 0 or ($key === 0 and $first_query == TRUE)) {
					$url .= ($this->conf['sDEF.']['word_search_andor'] == 'OR' ? '+OR' : '') . '+' . urlencode($word);
				} else {
					$url .= '&q=' . urlencode($word);
				}
			}
		}
		if (is_array($part['user'])) { // building the query for search users
			foreach ($part['user'] as $key => $user) {
				// 091010 feature (0001): exclude users with an "-" before username in plugin (thx @ Franz Ripfel)
				// begin
				if (substr($user, 0, 1) == '-') {
					$user = substr($user, 1, strlen($user));
					$negate = '-';
				} else {
					$negate = '';
				}
				// end
				if ($key === 0 and $first_query == FALSE) {
					$url .= 'q=' . $negate . 'from%3A' . urlencode($user); // 091010 feature (0001)
					$first_query = TRUE;
				} elseif ($key > 0 or ($key === 0 and $first_query == TRUE)) {
					$url .= ($this->conf['sDEF.']['user_search_andor'] == 'OR' ? '+OR' : '') . '+' . $negate . 'from%3A' . urlencode($user); // 091010 feature (0001)
				} else {
					$url .= '&q=from%3A' . urlencode($user);
				}
			}
		}
		if (is_array($part['language'])) {
			foreach ($part['language'] as $key => $language) {
				if ($key === 0 and $first_query == FALSE) {
					$url .= 'lang=' . urlencode($language);
					$first_query = TRUE;
				} else {
					$url .= '&lang=' . urlencode($language);
				}
			}
		}
		// results per page - needed if more than 15 results wanted
		if ($this->conf['sVIEW.']['max_results'] > 15) {
			$url .= '&rpp=' . $this->conf['sVIEW.']['max_results'];
		}
		// check if own search is valid url and build url for the right search outpot (json, atom)
		if ($this->conf['sDEF.']['own_search'] and preg_match("/^http:\/\/[0-9a-z]([-.]?[0-9a-z])*.[a-z]{2,4}$^/", $this->conf['sDEF.']['own_search']) !== FALSE) {
			$url = preg_replace('/search\?/', 'search.' . ($this->conf['sDEF.']['format'] ? $this->conf['sDEF.']['format'] : 'atom') . '?', $this->conf['sDEF.']['own_search']);
		}
		// parsing the url with SimpleXML
		$this->page = intval($this->piVars['page']);
		// 100106: added getUrl for TYPO3 selection of getting URL (e.g. curl, file_get_contents)
		// thx @ Mario Rossi - snowflake
		$xml_content = t3lib_div::getUrl($url, 0, false, $report);
		if ($this->debug) {
			t3lib_div::debug($report);
		}
		if ($xml_object = @simplexml_load_string($xml_content)) { // 091010: added an @ for "un"-displaying errors
			$this->global_result['title'] = (string) $xml_object->title;
			$j = 0;
			if ($this->page > 1) {
				$pointer = (($this->page - 1) * $this->max_results_per_page);
				$j = 0;
			} else {
				$pointer = 0;
			}
			$this->tweetCounter = sizeof($xml_object->entry);
			for ($pointer; $j < ($this->max_results_per_page); $pointer++) {
				if ($xml_object->entry[$pointer] and $pointer < $this->tweetCounter) {
					$entry[$pointer] = $this->parseEntry($xml_object->entry[$pointer]);
				} else {
					break;
				}
				$j++;
			}
			return $entry;
		} else {
			return FALSE;
		}
	}

	/**
	 * Parses the entry (tweet) to get all information out of the XML
	 *
	 * @param	object		$entry: The XML object of one tweet
	 * @return	The tweet information as an array
	 */
	function parseEntry($entry) {
		$date_to_check = strtotime((string) $entry->published);
		if (date('d.m.Y') == date('d.m.Y', $date_to_check)) {
			$result['published'] = date('H:i', $date_to_check);
		} else {
			$result['published'] = date('d.m.Y H:i', $date_to_check);
		}
		if ($this->conf['sVIEW.']['view_fullname']) {
			$twitter_name = $this->whatAboutUtf8((string) $entry->author->name);
		} else {
			$temp = $this->whatAboutUtf8((string) $entry->author->name);
			$temp = t3lib_div::trimExplode(' (', $temp);
			$twitter_name = $temp[0];
		}
		$result['title'] = (string) $entry->title;
		$result['content'] = $this->whatAboutUtf8((string) $entry->content);
		$result['author'] = $this->cObj->typolink($twitter_name, array('parameter' => (string) $entry->author->uri . ' _blank'));
		$result['author_name'] = $twitter_name;
		$result['author_uri'] = (string) $entry->author->uri;
		$result['avatar'] = ($entry->link[1]['rel'] == 'image') ? $entry->link[1]['href'] : '';
		return $result;
	}

	/**
	 * Decodes a string cause of a utf-8 problem
	 *
	 * @return	The value, decoded or not, checked by the flexform plugin setting
	 *
	 */
	function whatAboutUtf8($value) {
		if ($this->useUtf8Decode) {
			return utf8_decode($value);
		} else {
			return $value;
		}
	}

	/**
	 * Building the markerArray for the page browser
	 *
	 * @return	The marker array with all information of the page browser
	 */
	function buildPageBrowserMarker() {
		$firstPage = FALSE;
		$lastPage = FALSE;
		if ($this->tweetCounter > $this->conf['sVIEW.']['max_results']) {
			$this->tweetCounter = $this->conf['sVIEW.']['max_results'];
		}
		if ($this->page < 2 or !$this->page) {
			$firstPage = TRUE;
			$lastPage = FALSE;
		}
		if ($this->page > 1 and (($this->page) * $this->max_results_per_page) >= $this->tweetCounter) {
			$firstPage = FALSE;
			$lastPage = TRUE;
		}
		if ($this->page) {
			$page = $this->page;
		} else {
			$page = 1;
		}
		$rest = $this->tweetCounter % $this->max_results_per_page;
		$lastPageNumber = ($rest == 0) ? $this->tweetCounter / $this->max_results_per_page : (($this->tweetCounter - $rest) / $this->max_results_per_page) + 1;
		if ($firstPage) {
			$markerArray['###FIRST_PAGE###'] = '&nbsp;';
			$markerArray['###PREV_PAGE###'] = '&nbsp;';
			$markerArray['###NEXT_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('nextPage'), array('page' => $page + 1), TRUE);
			$markerArray['###LAST_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('lastPage'), array('page' => $lastPageNumber), TRUE);
			#$markerArray['###NEXT_PAGE###'] = $this->cObj->typolink($this->pi_getLL('nextPage'), array('parameter' => $GLOBALS['TSFE']->id, 'additionalParams' => '&'.$this->prefixId.'[page]='.($page + 1)));
			#$markerArray['###LAST_PAGE###'] = $this->cObj->typolink($this->pi_getLL('lastPage'), array('parameter' => $GLOBALS['TSFE']->id, 'additionalParams' => '&'.$this->prefixId.'[page]='.($lastPageNumber)));
		} elseif ($lastPage) {
			$markerArray['###FIRST_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('firstPage'), array('page' => FALSE), TRUE);
			$markerArray['###PREV_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('prevPage'), array('page' => $page - 1), TRUE);
			$markerArray['###NEXT_PAGE###'] = '&nbsp;';
			$markerArray['###LAST_PAGE###'] = '&nbsp;';
		} else {
			$markerArray['###FIRST_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('firstPage'), array('page' => FALSE), TRUE);
			// 091010: do not show parameter if page 1
			// begin
			if (($page - 1) === 1) {
				$markerArray['###PREV_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('prevPage'), array('page' => FALSE), TRUE);
			} else {
				$markerArray['###PREV_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('prevPage'), array('page' => $page - 1), TRUE);
			}
			// end
			$markerArray['###NEXT_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('nextPage'), array('page' => $page + 1), TRUE);
			$markerArray['###LAST_PAGE###'] = $this->pi_linkTP_keepPIvars($this->pi_getLL('lastPage'), array('page' => $lastPageNumber), TRUE);
		}
		$firstResult = (($page - 1) * $this->max_results_per_page) + 1;
		$lastResult = $firstResult + $this->max_results_per_page - 1;
		if ($lastResult > $this->tweetCounter) {
			$lastResult = $this->tweetCounter;
		}
		$markerArray['###PAGE###'] = $this->pi_getLL('page') . ' ' . ($this->page ? $this->page : '1') . ' ' . $this->pi_getLL('of') . ' ' . $lastPageNumber;
		return $markerArray;
	}

	/**
	 * gets the configuration of the plugin flexform
	 *
	 *
	 */
	function getFFConfig() {
		if (is_array($this->cObj->data['pi_flexform']['data'])) { // if there are flexform values
			foreach ($this->cObj->data['pi_flexform']['data'] as $key => $value) { // every flexform category
				if (count($this->cObj->data['pi_flexform']['data'][$key]['lDEF']) > 0) { // if there are flexform values
					foreach ($this->cObj->data['pi_flexform']['data'][$key]['lDEF'] as $key2 => $value2) { // every flexform option
						if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], $key2, $key)) { // if value exists in flexform
							$this->conf[$key . '.'][$key2] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], $key2, $key); // overwrite $this->conf
						}
					}
				}
			}
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/twittersearch/pi1/class.tx_twittersearch_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/twittersearch/pi1/class.tx_twittersearch_pi1.php']);
}

?>
