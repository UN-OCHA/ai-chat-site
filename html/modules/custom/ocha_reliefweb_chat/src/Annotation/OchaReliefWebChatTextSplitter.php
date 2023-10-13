<?php

namespace Drupal\ocha_reliefweb_chat\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a OCHA ReliefWeb Chat text splitter plugin annotation object.
 *
 * Plugin Namespace: Plugin\ocha_reliefweb_chat\TextSplitter.
 *
 * @see \Drupal\ocha_reliefweb_chat\Plugin\TextSplitterPluginBase
 * @see \Drupal\ocha_reliefweb_chat\Plugin\TextSplitterPluginInterface
 * @see \Drupal\ocha_reliefweb_chat\Plugin\TextSplitterPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class OchaReliefWebChatTextSplitter extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   * @ingroup plugin_translatable
   */
  public Translation $label;

  /**
   * A short description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   * @ingroup plugin_translatable
   */
  public Translation $description;

}
