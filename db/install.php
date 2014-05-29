<?php

/**
 * Tripleplay Repository Plugin
 *
 * @package    repository_tripleplay
 * @copyright  2013 Tripleplay Services Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

function xmldb_repository_tripleplay_install() {
    global $CFG;
    $result = true;
    require_once($CFG->dirroot.'/repository/lib.php');
    $tripleplay = new repository_type('tripleplay', array(), true);

    if(!$id = $tripleplay->create(true)) {
        $result=false;
    }
    
    return $result;
}
