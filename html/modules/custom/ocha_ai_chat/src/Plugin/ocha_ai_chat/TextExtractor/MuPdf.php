<?php

namespace Drupal\ocha_ai_chat\Plugin\ocha_ai_chat\TextExtractor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ocha_ai_chat\Plugin\TextExtractorPluginBase;

/**
 * PDF to text extractor using MuPDF.
 *
 * @OchaAiChatTextExtractor(
 *   id = "mupdf",
 *   label = @Translation("MuPDF"),
 *   description = @Translation("Extract text from PDF using MuPDF"),
 *   mimetypes = {
 *     "application/pdf",
 *   }
 * )
 *
 * @todo if we can use FFI we may be able to extract the text for each page
 * without having to call mutool for each page which would be much faster.
 */
class MuPdf extends TextExtractorPluginBase {

  /**
   * Path to mutool executable.
   *
   * @var string
   */
  protected $mutool;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $plugin_type = $this->getPluginType();
    $plugin_id = $this->getPluginId();
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    $form['plugins'][$plugin_type][$plugin_id]['mutool'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mutool'),
      '#description' => $this->t('Path to the mutool executable'),
      '#default_value' => $config['mutool'] ?? NULL,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getText(string $path): string {
    // This is much faster than retrieving the text for each page but we lose
    // the ability to reference particular pages.
    return $this->getPageRangeText($path, '1-N');
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTexts(string $path): array {
    $page_count = $this->getPageCount($path);

    // For easier referencing when retrieving relevant parts of a document,
    // we retrieve the text for each page individually.
    //
    // @todo evaluate whether to extract the entire text at once instead as it
    // might help with paragraphs split between pages.
    $texts = [];
    for ($page = 1; $page <= $page_count; $page++) {
      $texts[$page] = $this->getPageRangeText($path, $page . '-' . $page);
    }

    return $texts;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageCount(string $path): int {
    $mutool = $this->getMutool();
    $source = escapeshellarg($path);

    $command = "{$mutool} info -M {$source}";
    exec($command, $output, $result_code);

    if (empty($result_code) && preg_match('/Pages: (?<count>\d+)/', implode("\n", $output), $matches) === 1) {
      return intval($matches['count']);
    }
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedMimetypes(): array {
    return ['application/pdf'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPageRangeText(string $path, string $page_range): string {
    $tempfile = tempnam(sys_get_temp_dir(), 'mupdf_');

    $mutool = $this->getMutool();
    $options = implode(',', [
      'preserve-ligatures',
      'preserve-whitespace',
      'dehyphenate',
      'mediabox-clip=yes',
    ]);
    $destination = escapeshellarg($tempfile);
    $source = escapeshellarg($path);

    $command = "{$mutool} convert -F text -O {$options} -o {$destination} {$source} {$page_range}";
    exec($command, $output, $result_code);

    if (empty($result_code)) {
      $text = file_get_contents($tempfile);
    }
    else {
      $text = '';
    }

    unlink($tempfile);
    return $text;
  }

  /**
   * Get the mutool executable.
   *
   * @return string
   *   Path to the mutool executable.
   */
  protected function getMutool(): string {
    if (!isset($this->mutool)) {
      $mutool = $this->getPluginSetting('mutool', '/usr/bin/mutool');
      if (is_executable($mutool)) {
        $this->mutool = $mutool;
      }
      else {
        throw new \Exception('Mutool executable not found or invalid.');
      }
    }
    return $this->mutool;
  }

}
