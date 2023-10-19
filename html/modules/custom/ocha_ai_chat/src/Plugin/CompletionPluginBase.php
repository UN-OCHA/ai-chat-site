<?php

namespace Drupal\ocha_ai_chat\Plugin;

/**
 * Base completion plugin.
 */
abstract class CompletionPluginBase extends PluginBase implements CompletionPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getPluginType(): string {
    return 'completion';
  }

}
