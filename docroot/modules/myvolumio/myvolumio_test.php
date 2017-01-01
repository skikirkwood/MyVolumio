<?php

/**
 * @file
 * Mailchimp module.
 */

namespace Drupal\myvolumio;

use \Drupal\node\Entity\Node;
use \Drupal\myvolumio\Api;

// require_once("src/api/myvolumioApi.php");

/**
 * Testing
 */
function myvolumio_test_api() {

    $uri = 'http://volumio.local/api/v1/backup/playlists/my-web-radio';
   //$uri = 'http://volumio.local/api/v1/backup/config';

    $myvolumio = new \Drupal\myvolumio\Api\myvolumioApi();
    $response = $myvolumio->get($uri, array('headers' => array('Accept' => 'application/json')));
    $json = (string) $response->getBody();

  try {
      $client = \Drupal::httpClient();
      $response = $client->get($uri, array('headers' => array('Accept' => 'application/json')));
      $json = (string) $response->getBody();
      if (empty($json)) {
        return FALSE;
      }
      // try to create a node
      $node = Node::create([
          'type' => 'volumio_setting',
          'title' => 'Placeholder',
          'uid' => \Drupal::currentUser()->id(),
          'status' => 1,
          ]
      );
      $json_object = json_decode($json);
      $json_backup = $json_object->backup;
      $backup = json_encode($json_backup, JSON_UNESCAPED_SLASHES);
      $node->field_volumio_setting_value->value = $backup;
      $node->field_volumio_setting->value='my-web-radio';
      $node->save();

      $query = \Drupal::entityQuery('node')
          ->condition('status', 1)
          ->condition('type', 'volumio_setting')
          ->condition('uid', \Drupal::currentUser()->id());

      $nids = $query->execute();

      // Load a single node.
      $json_object1 = Node::load($nids[96]);

      // Post it back to Volumio

      $jims_web_radios = '[{"service":"webradio","name":"BBC 2","uri":"http://bbcmedia.ic.llnwd.net/stream/bbcmedia_radio2_mf_p"},{"service":"webradio","name":"BBC 4","uri":"http://bbcmedia.ic.llnwd.net/stream/bbcmedia_radio4fm_mf_p?s=1470930986&e=1470945386&h=94cc6ef927e196a7410c1922ed5cf769"},{"service":"webradio","name":"BBC 6","uri":"http://open.live.bbc.co.uk/mediaselector/5/select/version/2.0/mediaset/http-icy-mp3-a/vpid/bbc_6music/format/pls.pls"},{"service":"webradio","name":"SmoothJazz.com.pl","uri":"http://stream4.nadaje.com:11416/listen.pls"},{"service":"webradio","name":"DI Vocal Chillout","uri":"http://prem1.di.fm:80/vocalchillout_hi?a680df71cb89836a6bf11bfd"},{"service":"webradio","name":"DI Chillout","uri":"http://prem1.di.fm:80/chillout_hi?a680df71cb89836a6bf11bfd"},{"service":"webradio","name":"DI Chillout Dreams","uri":"http://prem1.di.fm:80/chilloutdreams_hi?a680df71cb89836a6bf11bfd"},{"service":"webradio","name":"DI Indie Beats","uri":"http://prem4.di.fm:80/indiebeats_hi?a680df71cb89836a6bf11bfd"},{"service":"webradio","name":"DI Lounge","uri":"http://prem1.di.fm:80/lounge_hi?a680df71cb89836a6bf11bfd"},{"service":"webradio","name":"DI Downtemp Lounge","uri":"http://prem1.di.fm:80/downtempolounge_hi?a680df71cb89836a6bf11bfd"},{"service":"webradio","name":"DI Vocal Lounge","uri":"http://prem1.di.fm:80/vocallounge_hi?a680df71cb89836a6bf11bfd"},{"service":"webradio","name":"DI Ambient","uri":"http://prem1.di.fm:80/ambient_hi?a680df71cb89836a6bf11bfd"},{"service":"webradio","name":"KQED","uri":"http://streams.kqed.org/kqedradio.m3u"},{"service":"webradio","name":"Soma FM Lush","uri":"http://ice1.somafm.com/lush-128-aac"},{"service":"webradio","name":"Soma FM Groove Salad","uri":"http://ice1.somafm.com/groovesalad-128-aac"},{"service":"webradio","name":"Soma FM Digitalis","uri":"http://ice1.somafm.com/digitalis-128-aac"},{"service":"webradio","name":"BBC 1","uri":"http://bbcmedia.ic.llnwd.net/stream/bbcmedia_radio1_mf_p?s=1470931920&e=1470946320&h=f2bceb64bad063bafb035192f2ddaa9b"}]';
     // $volumio_response = $client->post('http://volumio.local/api/v1/restore/playlists', array(), $post_data)->send();
      $volumio_response = $client->request('POST', 'http://volumio.local/api/v1/restore/playlists', [
          'form_params' => [
              'path' => 'my-web-radio',
              'type' => 'radios',
              'data' => $jims_web_radios
          ]
      ]);

      return '<h3>Success</h3>';
    }
    catch (\Exception $e) {
      return FALSE;
    }

}