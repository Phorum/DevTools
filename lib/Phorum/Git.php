<?php
/**
 * This script implements the Phorum_Git class.
 *
 * @author Maurice Makaay
 * @copyright Phorum
 * @package Phorum
 * @category DevTools
 */

/**
 * The Phorum_Git class.
 *
 * This class provides functionality for communicating with the
 * Phorum github repositories.
 *
 * @package Phorum
 */
class Phorum_Git
{
    protected $_gitapi = "https://github.com/api/v2/json/";

    protected function _api_get($request)
    {
        $url = $this->_gitapi . $request;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($ch);
        curl_close($ch);

        if (!$output) throw new Exception(
            "HTTP request for URL '$url' failed");

        $data = json_decode($output);
        if ($data === NULL) throw new Exception(
            "Could not decode JSON response for URL '$url'");

        return $data;
    }

    /**
     * Retrieve a list of Phorum repositories from github.
     */
    public function getRepositories()
    {
        return $this->_api_get("repos/show/Phorum")->repositories; 
    }
}

