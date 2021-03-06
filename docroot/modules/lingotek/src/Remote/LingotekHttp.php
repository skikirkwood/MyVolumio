<?php

namespace Drupal\lingotek\Remote;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * Lingotek HTTP implementation using Guzzle.
 */
class LingotekHttp implements LingotekHttpInterface {

  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->config = $config_factory->get('lingotek.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /*
   * send a GET request
   */
  public function get($path, $args = array()) {
    $options = [];
    if (count($args)) {
      $options = [RequestOptions::QUERY => $args];
    }
    return $this->httpClient->get($this->getBaseUrl() . $path,
      [
        RequestOptions::HEADERS => $this->getDefaultHeaders(),
      ] + $options
    );
  }

  /*
   * send a POST request
   */
  public function post($path, $args = array(), $use_multipart = FALSE) {
    $options = [];
    if (count($args) && $use_multipart) {
      $multipart = [];
      foreach ($args as $name => $contents) {
        $multipart[] = ['name' => $name, 'contents' => $contents];
      }
      $options[RequestOptions::MULTIPART] = $multipart;
    }
    elseif (count($args) && !$use_multipart) {
      $options[RequestOptions::FORM_PARAMS] = $args;
    }
    return $this->httpClient->post($this->getBaseUrl() . $path,
      [
        RequestOptions::HEADERS => $this->getDefaultHeaders(),
      ] + $options
    );
  }

  /*
   * send a DELETE request
   */
  public function delete($path, $args = array()) {
    // Let the post method masquerade as a DELETE
    $options = [];
    if (count($args)) {
      $options = [RequestOptions::QUERY => $args];
    }
    return $this->httpClient->delete($this->getBaseUrl() . $path,
      [
        RequestOptions::HEADERS => $this->getDefaultHeaders() +
          ['X-HTTP-Method-Override' => 'DELETE'],
      ] + $options
    );
  }

  /*
   * send a PATCH request
   */
  public function patch($path, $args = array(), $use_multipart = FALSE) {
    return $this->httpClient->patch($this->getBaseUrl() . $path,
      [
        RequestOptions::FORM_PARAMS => $args,
        RequestOptions::HEADERS => $this->getDefaultHeaders() +
          // Let the post method masquerade as a PATCH.
          ['X-HTTP-Method-Override' => 'PATCH'],
      ]
    );
  }

  public function getCurrentToken() {
    return $this->config->get('account.access_token');
  }

  protected function getDefaultHeaders() {
    $headers = ['Accept' => '*/*'];
    if ($token = $this->config->get('account.access_token')) {
      $headers['Authorization'] = 'bearer ' . $token;
    }
    return $headers;
  }

  protected function getBaseUrl() {
    $base_url = $this->config->get('account.sandbox_host');
    if ($this->config->get('account.use_production')) {
      $base_url = $this->config->get('account.host');
    }
    return $base_url;
  }

}
