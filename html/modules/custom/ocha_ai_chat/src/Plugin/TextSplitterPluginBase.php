<?php

namespace Drupal\ocha_ai_chat\Plugin;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base text splitter plugin.
 */
abstract class TextSplitterPluginBase extends PluginBase implements TextSplitterPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

}
