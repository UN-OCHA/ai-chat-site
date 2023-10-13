<?php

namespace Drupal\ocha_reliefweb_chat\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a OCHA ReliefWeb Chat text extractor plugin annotation object.
 *
 * Plugin Namespace: Plugin\ocha_reliefweb_chat\TextExtractor.
 *
 * @see \Drupal\ocha_reliefweb_chat\Plugin\TextExtractorPluginBase
 * @see \Drupal\ocha_reliefweb_chat\Plugin\TextExtractorPluginInterface
 * @see \Drupal\ocha_reliefweb_chat\Plugin\TextExtractorPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class OchaReliefWebChatTextExtractor extends Plugin {

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

  /**
   * List of file mimetypes supported by the extractor.
   *
   * @var array
   */
  public array $mimetypes;

}
