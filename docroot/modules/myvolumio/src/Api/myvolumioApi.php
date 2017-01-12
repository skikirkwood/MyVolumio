<?php

/**
 * @file
 * Contains Drupal\yesmail\Api\YesmailApiBase.
 */

namespace Drupal\myvolumio\Api;

use \Drupal\node\Entity\Node;

/**
 * Class myvolumioApi.
 *
 * @package Drupal\myvolumio\Api
 */
class myvolumioApi
{

    const PLAYLIST_BACKUP = 'backup/playlists/';
    const PLAYLIST_RESTORE = 'restore/playlists/';
    const CONFIG_BACKUP = 'backup/config/';
    const CONFIG_RESTORE = 'restore/config/';

    /**
     * Construct a myvolumioApi object.
     *
     */
    public function __construct()
    {
        $this->httpClient = \Drupal::httpClient();
    }

    /**
     * {@inheritdoc}
     */

    public function backupMyWebRadio() {

        $result = $this->backupMyWebRadios('my-web-radio', 'myRadios');

        return $result;
    }

    public function backupMyRadioFavourites() {

        $result = $this->backupMyWebRadios('radio-favourites', 'radios');

        return $result;
    }

    public function backupMyPlaylists() {

        $result = $this->backupMyWebRadios('playlist', 'playlist');

        return $result;
    }

    public function backupMyConfig() {
        $result = $this->backupConfig();

        return $result;
    }

    // Internal functions below - make private

    public function backupMyWebRadios($type, $path) {

        $radios = $this->getMyWebRadios($type);
        $response = $this->saveRadios($type, $path, $radios);

        return $response;

    }

    public function getMyWebRadios($type)
    {

        $url = self::PLAYLIST_BACKUP . $type;

        $response = $this->makeGetRequest($url);

        return (string)$response;
    }

    public function saveRadios($type, $path, $radios)
    {

        // try to create a node
        $node = Node::create([
                'type' => 'volumio_setting',
                'title' => $path,
                'uid' => \Drupal::currentUser()->id(),
                'status' => 1,
            ]
        );
        $json_object = json_decode($radios);
        $json_backup = $json_object->backup;
        $backup = json_encode($json_backup, JSON_UNESCAPED_SLASHES);
        $node->field_volumio_setting_value->value = $backup;
        $node->field_volumio_setting->value = $type;
        $node->save();

        return $node->id();
    }

    public function restoreRadios($path, $type, $radios)
    {

        $url = self::PLAYLIST_RESTORE;
        $volumio_response = $this->makePostRequest($url, [
            'form_params' => [
                'path' => $path,
                'type' => $type,
                'data' => $radios  //$web_radios
            ]
        ]);

        return $volumio_response;
    }

    public function restorePlaylist($nid) {

        $json = $this->getNodeValue($nid);
        $result = $this->restoreRadios('my-web-radio', 'myRadios', $json);

        return $result;

    }

    public function backupConfig() {

        $config = $this->getConfig();
        $response = $this->saveConfig($config);

        return $response;
    }

    public function getConfig()
    {

        $url = self::CONFIG_BACKUP;

        $response = $this->makeGetRequest($url);

        return (string)$response;
    }

    public function saveConfig($config)
    {

        // try to create a node
        $node = Node::create([
                'type' => 'volumio_setting',
                'title' => 'Volumio Config',
                'uid' => \Drupal::currentUser()->id(),
                'status' => 1,
            ]
        );
        $json_object = json_decode($config);
        $backup = json_encode($json_object, JSON_UNESCAPED_SLASHES);
        $node->field_volumio_setting_value->value = $backup;
        $node->field_volumio_setting->value = 'Config';
        $node->save();

        return $node->id();
    }

    public function restoreConfig($config)
    {

        $url = self::CONFIG_RESTORE;
        $volumio_response = $this->makePostRequest($url, [
            'form_params' => [
                'config' => $config
            ]
        ]);

        return $volumio_response;
    }

    public function getNodeValue($nid) {

        $json_object = Node::load($nid);
        $json_string = $json_object->field_volumio_setting_value->getValue();
        $json = $json_string[0]['value'];

        return $json;
    }

    public function makePostRequest($url, $json = array())
    {

        $response = $this->httpClient->post(
            $this->getEndpointUrl($url),
            $json
        );

        $this->checkStatusCode($response);

        return $response->getBody();

    }

    /**
     * {@inheritdoc}
     */
    public function makeGetRequest($url, $query_params = array())
    {

        $response = $this->httpClient->get(
            $this->getEndpointUrl($url),
            [
                'headers' => ['Accept' => 'application/json'],
                'query' => $query_params
            ]
        );

        $this->checkStatusCode($response);

        $request = $response->getBody();

        return $request;
    }

    /**
     * A lower level way to submit a request to the myvolumio API.
     *
     *
     *
     * @return array
     *   The JSON response from the myvolumio API, or an empty array if no
     *   response was given or if the status code was incorrect.
     */
    private function makeRequest($request)
    {
        try {
            $response = $this->httpClient->send($request);

            $this->checkStatusCode($response);

            $json = $response->json();

            return $json;
        } catch (\Exception $e) {
            $this->logToWatchdog($e->getMessage());
            return array();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpointUrl($endpoint)
    {

        return 'http://volumio2.local/api/v1/' . $endpoint;
    }

    /**
     * Check the status code of a request.
     *
     * @throws Exception
     *   Thrown when the status code is anything other than 200.
     *
     * @return int
     *   200 if the response is OK. Otherwise an exception is thrown.
     */
    private function checkStatusCode($response)
    {
        $status_code = $response->getStatusCode();
        switch ($status_code) {
            case 200:
            case 201:
            case 202:
            case 204:
                break;

            case 400:
            case 401:
            case 403:
            case 404:
            case 405:
            case 409:
            case 413:
            case 500:
                throw new \Exception($response->getReasonPhrase());

        }
        return $status_code;
    }

    /**
     * Log an error to Drupal's watchdog.
     *
     * @param string $message
     *    The message to add.
     */
    private function logToWatchdog($message)
    {
        \Drupal::logger('volumio_api')->error($message);
        //   watchdog('volumio_api', $message, $variables, $severity, $link);
    }

}
