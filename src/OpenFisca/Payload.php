<?php

declare(strict_types = 1);

namespace Drupal\webform_openfisca\OpenFisca;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;

/**
 * Represents the data sent to or received from OpenFisca.
 */
abstract class Payload {

  /**
   * The payload data.
   *
   * @var array<string, mixed>
   */
  protected array $payload = [];

  /**
   * Extra data for debugging.
   *
   * @var array<string, mixed>
   */
  protected array $debugData = [];

  /**
   * Get the JSON of the payload.
   *
   * @param bool $prettyPrint
   *   Whether to pretty print the JSON.
   *
   * @return string
   *   The JSON.
   */
  public function toJson(bool $prettyPrint = FALSE): string {
    return $prettyPrint ? Helper::jsonEncodePretty($this->payload) : Json::encode($this->payload);
  }

  /**
   * Get the payload data.
   *
   * @return array
   *   The payload.
   */
  public function getData(): array {
    return $this->payload;
  }

  /**
   * Create a payload object from JSON string.
   *
   * @param string $json
   *   The JSON.
   *
   * @return self
   *   The payload.
   */
  public static function fromJson(string $json) : static {
    $payload = new static();
    $payload->payload = !empty($json) ? Json::decode($json) : [];
    return $payload;
  }

  /**
   * Check if a key exists in the payload.
   *
   * @param array|string $key_path
   *   The key path in the format:
   *   - array: ['persons', 'PersonA', 'age']
   *   - string: 'persons.PersonsA.age'
   *
   * @return bool
   *   TRUE if the key path exists.
   */
  public function keyPathExists(array|string $key_path): bool {
    $path = $key_path;
    if (!is_array($key_path)) {
      $path = [];
      Helper::parseOpenFiscaFieldMapping($key_path, path: $path);
    }
    return NestedArray::keyExists($this->payload, $path);
  }

  /**
   * Get the value of a key path.
   *
   * @param array|string $key_path
   *   The key path in the format:
   *  - array: ['persons', 'PersonA', 'age']
   *  - string: 'persons.PersonsA.age'
   *
   * @return mixed
   *   The value.
   */
  public function getValue(array|string $key_path) : mixed {
    $path = $key_path;
    if (!is_array($key_path)) {
      $path = [];
      Helper::parseOpenFiscaFieldMapping($key_path, path: $path);
    }
    return NestedArray::getValue($this->payload, $path);
  }

  /**
   * Set the value of a key path.
   *
   * @param array|string $key_path
   *   The key path.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   *   The current payload.
   */
  public function setValue(array|string $key_path, mixed $value) : static {
    $path = $key_path;
    if (!is_array($key_path)) {
      $path = [];
      Helper::parseOpenFiscaFieldMapping($key_path, path: $path);
    }
    NestedArray::setValue($this->payload, $path, $value);
    return $this;
  }

  /**
   * Find a key recursively.
   *
   * @param array $data
   *   The data array.
   * @param string $key
   *   The key to find.
   * @param array $parents
   *   The parent key path, only used for recursive.
   *
   * @return array|null
   *   The path of the first matching key, or NULL if not found.
   */
  public function findKeyRecursive(array $data, string $key, array $parents = []): ?array {
    foreach ($data as $data_key => $data_value) {
      $parents[] = $data_key;
      if ($data_key === $key) {
        return $parents;
      }
      if (is_array($data_value)) {
        $path = $this->findKeyRecursive($data_value, $key, $parents);
        if ($path !== NULL) {
          return $path;
        }
      }
      array_pop($parents);
    }
    return NULL;
  }

  /**
   * Find the path of the first matching key,
   *
   * @param string $key
   *   The key, e.g. 'salary'.
   * @param array $parents
   *   The parents key path. If set, only find within the subset of the payload
   * defined the parents, e.g. [persons]
   *
   * @return array|null
   *   The key path e.g. [persons, PersonA, salary], or NULL if not found.
   */
  public function findKey(string $key, array $parents = []): ?array {
    $data = $this->payload;
    if (!empty($parents)) {
      $data = NestedArray::getValue($this->payload, $parents);
      if (!is_array($data)) {
        return NULL;
      }
    }
    $key_path = $this->findKeyRecursive($data, $key);
    if ($key_path !== NULL) {
      array_push($parents, ...$key_path);
      return $parents;
    }
    return NULL;
  }

  /**
   * Find the key path of the first matching key.
   *
   * @param string $key
   *   The key, e.g. 'salary'.
   * @param array $parents
   *   The parents key path. If set, only find within the subset of the payload
   * defined the parents, e.g. [persons]
   *
   * @return string|null
   *   The key path e.g. 'persons.PersonA.salary', or NULL if not found.
   */
  public function findKeyPath(string $key, array $parents = []): ?string {
    $key_path = $this->findKey($key);
    return is_array($key_path) ? implode('.', $key_path) : NULL;
  }

  /**
   * Check if the payload has a debug data.
   *
   * @param string $key
   *   The name of the debug data.
   *
   * @return bool
   *   TRUE if exists.
   */
  public function hasDebugData(string $key): bool {
    return array_key_exists($key, $this->debugData);
  }

  /**
   * Retrieve the debug data.
   *
   * @param string $key
   *   The name if the debug data.
   *
   * @return mixed
   *   The data.
   */
  public function getDebugData(string $key) : mixed {
    return $this->debugData[$key] ?? NULL;
  }

  /**
   * Set the value of debug data.
   *
   * @param string $key
   *   The name of the debug data.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   *   The payload.
   */
  public function setDebugData(string $key, mixed $value) : static {
    $this->debugData[$key] = $value;
    return $this;
  }

  /**
   * Remove an debug data.
   *
   * @param string $key
   *   The name of the debug data.
   *
   * @return $this
   *   The payload.
   */
  public function unsetDebugData(string $key) : static {
    unset($this->debugData[$key]);
    return $this;
  }

  /**
   * Return all debug data.
   *
   * @return array<string, mixed>
   *   The debug data.
   */
  public function getAllDebugData() : array {
    return $this->debugData;
  }

  /**
   * Remove all debug data.
   *
   * @return $this
   *   The payload.
   */
  public function unsetAllDebugData() : static {
    $this->debugData = [];
    return $this;
  }

}
