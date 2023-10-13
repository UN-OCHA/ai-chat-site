<?php

namespace Drupal\ocha_reliefweb_chat\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager for the text extractor plugins.
 */
class TextExtractorPluginManager extends PluginManagerBase implements TextExtractorPluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/ocha_reliefweb_chat/TextExtractor',
      $namespaces,
      $module_handler,
      'Drupal\ocha_reliefweb_chat\Plugin\TextExtractorPluginInterface',
      'Drupal\ocha_reliefweb_chat\Annotation\OchaReliefWebChatTextExtractor'
    );

    $this->setCacheBackend($cache_backend, 'ocha_reliefweb_chat_text_extractor_plugins');
    $this->alterInfo('ocha_reliefweb_chat_text_extractor_info');
  }

}
