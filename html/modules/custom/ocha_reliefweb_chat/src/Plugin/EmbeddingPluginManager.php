<?php

namespace Drupal\ocha_reliefweb_chat\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager for the embedding plugins.
 */
class EmbeddingPluginManager extends PluginManagerBase implements EmbeddingPluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/ocha_reliefweb_chat/Embedding',
      $namespaces,
      $module_handler,
      'Drupal\ocha_reliefweb_chat\Plugin\EmbeddingPluginInterface',
      'Drupal\ocha_reliefweb_chat\Annotation\OchaReliefWebChatEmbedding'
    );

    $this->setCacheBackend($cache_backend, 'ocha_reliefweb_chat_embedding_plugins');
    $this->alterInfo('ocha_reliefweb_chat_embedding_info');
  }

}
