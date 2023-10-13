<?php

namespace Drupal\ocha_reliefweb_chat\Plugin\Devel\Dumper;

use Drupal\devel\DevelDumperBase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * Provides a Symfony VarDumper dumper plugin.
 *
 * @DevelDumper(
 *   id = "debug_dumper",
 *   label = @Translation("Symfony var-dumper with more depth"),
 *   description = @Translation("Wrapper for <a href='https://github.com/symfony/var-dumper'>Symfony var-dumper</a> debugging tool."),
 * )
 */
class DebugDumper extends DevelDumperBase {

  /**
   * {@inheritdoc}
   */
  public function export($input, $name = NULL) {
    $cloner = new VarCloner();
    $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();

    $output = fopen('php://memory', 'r+b');
    $dumper->dump($cloner->cloneVar($input), $output, [
      // 1 and 160 are the default values for these options
      'maxDepth' => 10,
      'maxStringLength' => 160,
    ]);
    $output = stream_get_contents($output, -1, 0);

    if ($name) {
      $output = $name . ' => ' . $output;
    }

    return $this->setSafeMarkup($output);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkRequirements() {
    return class_exists('Symfony\Component\VarDumper\Cloner\VarCloner', TRUE);
  }

}
