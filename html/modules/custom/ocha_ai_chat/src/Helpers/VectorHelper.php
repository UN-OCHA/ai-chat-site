<?php

namespace Drupal\ocha_ai_chat\Helpers;

/**
 * Helper to manipulate vectors.
 */
class VectorHelper {

  /**
   * Calculate the dot product of 2 vectors.
   *
   * @param array $a
   *   First vector.
   * @param array $b
   *   Second vector.
   *
   * @return float
   *   Dot product of the 2 vectors.
   */
  public static function dotProdcut(array $a, array $b): float {
    return array_sum(array_map(function ($x, $y) {
       return $x * $y;
    }, $a, $b));
  }

  /**
   * Calculate the cosine similarity of 2 vectors.
   *
   * @param array $a
   *   First vector.
   * @param array $b
   *   Second vector.
   *
   * @return float
   *   Cosine similarity of the 2 vectors.
   */
  public static function cosineSimilarity(array $a, array $b) {
    return static::dotProdcut($a, $b) / sqrt(static::dotProdcut($a, $a) * static::dotProdcut($b, $b));
  }

  /**
   * Calculate the mean of an array of vectors along the given axis.
   *
   * @param array $vectors
   *   List of vectors.
   * @param string $axis
   *   Either `x` to calculate the means of the rows or `y` for the means of the
   *   columns.
   *
   * @return array
   *   Mean vector.
   */
  public static function mean(array $vectors, string $axis = 'y'): array {
    $x_count = count(reset($vectors));
    $y_count = count($vectors);

    $result = [];
    if ($axis === 'x') {
      for ($i = 0; $i < $y_count; $i++) {
        $result[$i] = array_sum($vectors[$i]) / $x_count;
      }
    }
    else {
      for ($i = 0; $i < $x_count; $i++) {
        $result[$i] = array_sum(array_column($vectors, $i)) / $y_count;
      }
    }
    return $result;
  }

}
