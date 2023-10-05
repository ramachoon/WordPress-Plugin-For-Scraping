<?php

namespace PHPCrawl;

use PHPCrawl\Utils\PHPCrawlerUtils;

/**
 * Cache for storing user-data to send with requests, like cookies, post-data
 * and basic-authentications.
 *
 * @package phpcrawl
 * @internal
 */
class PHPCrawlerUserSendDataCache
{
    /**
     * Array containing basic-authentications to send.
     *
     * @var array
     */
    protected $basic_authentications = [];

    /**
     * Array containing post-data to send.
     *
     * @var array
     */
    protected $post_data = [];

    /**
     * Adds post-data together with an URL-regex to the list of post-data to send with requests.
     *
     * @param string $url_regex Regular expression defining the URL(s) the post-data should be send to.
     * @param array $post_data_array Post-data-array, the keys are the post-data-keys, the values the post-values.
     *                                (like ["key1" => "value1", "key2" => "value2"]
     * @return bool|null
     */
    public function addPostData($url_regex, $post_data_array): ?bool
    {
        // Check regex
        $regex_okay = PHPCrawlerUtils::checkRegexPattern($url_regex);

        if ($regex_okay == true) {
            foreach ($post_data_array as $key => $value) {
                // Add data to post_data-array
                $tmp = [];
                $tmp['url_regex'] = $url_regex;
                $tmp['key'] = $key;
                $tmp['value'] = $value;

                $this->post_data[] = $tmp;
            }

            return true;
        }

        return false;
    }

    /**
     * Returns the post-data (key and value) that should be send to the given URL.
     *
     * @param string $url The URL.
     * @return array Array containing the post_keys as keys and the values as values.
     *               (like ["key1" => "value1", "key2" => "value2"]
     */
    public function getPostDataForUrl($url): array
    {
        $post_data_array = [];

        foreach ($this->post_data as $x => $xValue) {
            if (preg_match($xValue['url_regex'], $url)) {
                $post_data_array[$xValue['key']] = $this->post_data[$x]['value'];
            }
        }

        return $post_data_array;
    }

    /**
     * Adds a basic-authentication (username and password) to the list of authentications that will be send
     * with requests.
     *
     * @param string $url_regex Regular expression defining the URL(s) the authentication should be send to.
     * @param string $username The username
     * @param string $password The password
     *
     * @return bool
     */
    public function addBasicAuthentication($url_regex, $username, $password): ?bool
    {
        // Check regex
        $regex_okay = PHPCrawlerUtils::checkRegexPattern($url_regex);

        if ($regex_okay == true) {
            // Add authentication to basic_authentications-array
            $tmp = [];
            $tmp['url_regex'] = $url_regex;
            $tmp['username'] = $username;
            $tmp['password'] = $password;

            $this->basic_authentications[] = $tmp;
            return true;
        }

        return false;
    }

    /**
     * Returns the basic-authentication (username and password) that should be send to the given URL.
     *
     * @param string $url The URL.
     * @return array Array containing the keys "username" and "password".
     *               Returns NULL if no authentication was found in cache for the given URL.
     */
    public function getBasicAuthenticationForUrl($url): ?array
    {
        foreach ($this->basic_authentications as $x => $xValue) {
            if (preg_match($xValue['url_regex'], $url)) {
                $tmp = [];
                $tmp['username'] = $this->basic_authentications[$x]['username'];
                $tmp['password'] = $this->basic_authentications[$x]['password'];

                return $tmp;
            }
        }

        return null;
    }
}
