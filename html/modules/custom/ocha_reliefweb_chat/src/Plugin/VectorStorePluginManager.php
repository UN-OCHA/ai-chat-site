<?php

namespace Drupal\ocha_reliefweb_chat\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager for the vector store plugins.
 */
class VectorStorePluginManager extends PluginManagerBase implements VectorStorePluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/ocha_reliefweb_chat/VectorStore',
      $namespaces,
      $module_handler,
      'Drupal\ocha_reliefweb_chat\Plugin\VectorStorePluginInterface',
      'Drupal\ocha_reliefweb_chat\Annotation\OchaReliefWebChatVectorStore'
    );

    $this->setCacheBackend($cache_backend, 'ocha_reliefweb_chat_vector_store_plugins');
    $this->alterInfo('ocha_reliefweb_chat_vector_store_info');
  }

}
