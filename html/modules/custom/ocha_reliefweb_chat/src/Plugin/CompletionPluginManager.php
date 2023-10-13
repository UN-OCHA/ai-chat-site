<?php

namespace Drupal\ocha_reliefweb_chat\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager for the completion plugins.
 */
class CompletionPluginManager extends PluginManagerBase implements CompletionPluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/ocha_reliefweb_chat/Completion',
      $namespaces,
      $module_handler,
      'Drupal\ocha_reliefweb_chat\Plugin\CompletionPluginInterface',
      'Drupal\ocha_reliefweb_chat\Annotation\OchaReliefWebChatCompletion'
    );

    $this->setCacheBackend($cache_backend, 'ocha_reliefweb_chat_completion_plugins');
    $this->alterInfo('ocha_reliefweb_chat_completion_info');
  }

}
