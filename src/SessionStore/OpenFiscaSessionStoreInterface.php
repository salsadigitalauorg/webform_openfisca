<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\SessionStore;

/**
 * Stores Webform OpenFisca calculation results in the user's session.
 *
 * Buckets are namespaced per webform id and carry their own TTL written at
 * set time. Reads transparently expire and clear stale buckets.
 */
interface OpenFiscaSessionStoreInterface {

  /**
   * The session attribute key under which all buckets are stored.
   */
  public const SESSION_KEY = '_webform_openfisca';

  /**
   * Default TTL in seconds (4 hours) when none is supplied.
   */
  public const DEFAULT_TTL = 14400;

  /**
   * Store a single value for a webform.
   *
   * @param string $webform_id
   *   The webform id namespace.
   * @param string $key
   *   The value key.
   * @param mixed $value
   *   The value to store.
   * @param int $ttl_seconds
   *   The TTL in seconds. Values <= 0 cause the bucket to be cleared.
   */
  public function set(string $webform_id, string $key, mixed $value, int $ttl_seconds = self::DEFAULT_TTL): void;

  /**
   * Store multiple values for a webform at once.
   *
   * @param string $webform_id
   *   The webform id namespace.
   * @param array<string, mixed> $values
   *   Key/value pairs to store.
   * @param int $ttl_seconds
   *   The TTL in seconds. Values <= 0 cause the bucket to be cleared.
   */
  public function setMultiple(string $webform_id, array $values, int $ttl_seconds = self::DEFAULT_TTL): void;

  /**
   * Read a single value for a webform.
   *
   * Expired buckets are silently cleared and the default is returned.
   *
   * @param string $webform_id
   *   The webform id namespace.
   * @param string $key
   *   The value key.
   * @param mixed $default
   *   The default value when the key is missing or the bucket has expired.
   *
   * @return mixed
   *   The stored value, or the default.
   */
  public function get(string $webform_id, string $key, mixed $default = NULL): mixed;

  /**
   * Read every value stored for a webform.
   *
   * Expired buckets are silently cleared and an empty array is returned.
   *
   * @param string $webform_id
   *   The webform id namespace.
   *
   * @return array<string, mixed>
   *   The values, keyed by name.
   */
  public function getAll(string $webform_id): array;

  /**
   * Check whether a key exists for a webform and is not expired.
   *
   * @param string $webform_id
   *   The webform id namespace.
   * @param string $key
   *   The value key.
   *
   * @return bool
   *   TRUE if the key exists in a non-expired bucket.
   */
  public function has(string $webform_id, string $key): bool;

  /**
   * Remove every value stored for a webform.
   *
   * @param string $webform_id
   *   The webform id namespace.
   */
  public function clear(string $webform_id): void;

  /**
   * Remove every value for every webform.
   */
  public function clearAll(): void;

}
