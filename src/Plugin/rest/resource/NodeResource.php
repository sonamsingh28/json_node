<?php

namespace Drupal\json_node\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a node resource.
 *
 * @RestResource(
 *   id = "node_resource",
 *   label = @Translation("Node Resource"),
 *   uri_paths = {
 *     "canonical" = "/page_json/{apikey}/{nid}"
 *   }
 * )
 */
class NodeResource extends ResourceBase {
  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user')
    );
  }

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(
      array $configuration,
      $plugin_id,
      $plugin_definition,
      array $serializer_formats,
      LoggerInterface $logger,
      AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
  }

  /**
   * Responds to GET requests.
   *
   * Returns the node data if api key matches.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing node data.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get($apikey = NULL, $nid = NULL) {
    // Uses current user after passing authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    // Fetch the site api key.
    $site_api_key = \Drupal::config('system.site')->get('siteapikey');

    // Check if nid in url is valid and of type page.
    $values = \Drupal::entityQuery('node')
      ->condition('nid', $nid)
      ->condition('type', 'page')
      ->execute();
    $node_exists = !empty($values);
    $data = '';

    // Check if api key present in url matches the one saved in siteapikey
    // variable and if the nid in url is valid or not.
    if (!strcmp($site_api_key, $apikey) && $node_exists) {
      $data = Node::load($nid);
    }
    else {
      throw new AccessDeniedHttpException('Access Denied');
    }
    return new ResourceResponse($data);
  }

}
