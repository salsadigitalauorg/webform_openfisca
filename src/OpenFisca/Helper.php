<?php

declare(strict_types = 1);

namespace Drupal\webform_openfisca\OpenFisca;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Helper class to handle OpenFisca data.
 */
class Helper {

  /**
   * Expand a comma-separated string into an array.
   *
   * @param string $csv_string
   *   The string.
   * @param bool $remove_empty
   *   Remove empty values.
   *
   * @return string[]
   *   The array.
   */
  public static function expandCsvString(string $csv_string, bool $remove_empty = TRUE) : array {
    $result = str_getcsv(trim($csv_string));
    if (!$remove_empty) {
      return $result;
    }

    return array_filter(
      array_map(
        static fn ($item) : string => !empty($item) ? trim((string) $item) : '',
        $result
      )
    );
  }

  /**
   * Parse an OpenFisca field mapping into OpenFisca input/output data.
   *
   * @param string $field_mapping
   *   The mapping, e.g. 'persons.PersonA.salary'.
   * @param string|null $group_entity
   *   The returned group entity, e.g. 'persons'.
   * @param string|null $entity
   *   The returned entity, e.g. 'PersonA'.
   * @param string[] $path
   *   The path array, e.g. ['persons', 'PersonA', 'salary']
   * @param string[] $parents
   *   The parents array, e.g. ['persons', 'PersonA']
   *
   * @return string
   *   The variable, e.g. 'salary'.
   */
  public static function parseOpenFiscaFieldMapping(string $field_mapping, ?string &$group_entity = NULL, ?string &$entity = NULL, array &$path = [], array &$parents = []) : string {
    try {
      $keys = explode('.', trim($field_mapping));
      $group_entity = NULL;
      $entity = NULL;
      // The last element is the variable, e.g 'salary'.
      $variable = array_pop($keys);
      $path = [$variable];
      // The second element is the entity, e.g 'PersonA'.
      if (isset($keys[1])) {
        $entity = trim($keys[1]);
        array_unshift($path, $entity);
      }
      // The first element is the group entity, e.g 'persons'.
      if (isset($keys[0])) {
        $group_entity = trim($keys[0]);
        array_unshift($path, $group_entity);
      }

      $parents = $path;
      array_pop($parents);

      return $variable ?: '';
    }
    catch (\ValueError) {
      return '';
    }
  }

  /**
   * Create an OpenFisca field mapping, e.g. 'group_entity.entity.variable'.
   *
   * @param string $group_entity
   *   The group entity, e.g. 'persons'.
   * @param string $entity
   *   The entity, e.g. 'PersonA'.
   * @param string $variable
   *   The variable, e.g. 'salary'.
   *
   * @return string
   *   The field mapping, e.g. 'persons.PersonA.salary'.
   */
  public static function combineOpenFiscaFieldMapping(string $group_entity, string $entity, string $variable) : string {
    return implode('.', [
      $group_entity,
      $entity,
      $variable,
    ]);
  }

  /**
   * Encode JSON with pretty print.
   *
   * @param mixed $data
   *   Data to encode.
   *
   * @return string
   *   JSON.
   *
   * @see \Drupal\Component\Serialization\Json::encode()
   */
  public static function jsonEncodePretty(mixed $data) : string {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
  }

  /**
   * Format a period with OpenFisca expected format.
   *
   * @param string $period_format
   *   The period format.
   * @param string $period
   *   The period date.
   *
   * @return string
   *   The formatted value.
   *
   * @see https://openfisca.org/doc/coding-the-legislation/35_periods.html#periods
   */
  public static function formatPeriod(string $period_format = 'DAY', string $period = 'now') : string {
    $date = new DrupalDateTime($period);

    return match ($period_format) {
      // Date format yyyy-mm-dd eg. 2022-11-02.
      'DAY' => $date->format('Y-m-d'),
      // Date format yyyy-Wxx eg. 2022-W44.
      'WEEK' => $date->format('Y-\WW'),
      // Date format yyyy-Wxx-x eg. 2022-W44-2 (2 = Tuesday the weekday number).
      'WEEKDAY' => $date->format('Y-\WW-N'),
      // Date format yyyy-mm eg. 2022-11.
      'MONTH' => $date->format('Y-m'),
      // Date format yyyy eg. 2022.
      'YEAR' => $date->format('Y'),
      // No date format, return string value ETERNITY.
      'ETERNITY' => 'ETERNITY',
      // Unknown format.
      default => '',
    };
  }

}
