<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\SessionStore;

use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Default session-backed implementation of the OpenFisca session store.
 *
 * Storage shape inside the session attribute:
 * @code
 * [
 *   '<webform_id>' => [
 *     '_meta' => ['written_at' => <timestamp>, 'ttl' => <seconds>],
 *     'data'  => ['<key>' => <value>, ...],
 *   ],
 *   ...
 * ]
 * @endcode
 */
class OpenFiscaSessionStore implements OpenFiscaSessionStoreInterface {

  public function __construct(
    protected RequestStack $requestStack,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function set(string $webform_id, string $key, mixed $value, int $ttl_seconds = self::DEFAULT_TTL): void {
    $this->setMultiple($webform_id, [$key => $value], $ttl_seconds);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(string $webform_id, array $values, int $ttl_seconds = self::DEFAULT_TTL): void {
    if ($ttl_seconds <= 0) {
      $this->clear($webform_id);
      return;
    }
    $session = $this->getSession();
    if ($session === NULL) {
      return;
    }
    $store = $this->readStore($session);
    $existing = $store[$webform_id]['data'] ?? [];
    $store[$webform_id] = [
      '_meta' => [
        'written_at' => $this->time->getRequestTime(),
        'ttl' => $ttl_seconds,
      ],
      'data' => array_merge($existing, $values),
    ];
    $session->set(self::SESSION_KEY, $store);
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $webform_id, string $key, mixed $default = NULL): mixed {
    $bucket = $this->readBucket($webform_id);
    if ($bucket === NULL) {
      return $default;
    }
    return array_key_exists($key, $bucket['data']) ? $bucket['data'][$key] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getAll(string $webform_id): array {
    $bucket = $this->readBucket($webform_id);
    return $bucket === NULL ? [] : $bucket['data'];
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $webform_id, string $key): bool {
    $bucket = $this->readBucket($webform_id);
    return $bucket !== NULL && array_key_exists($key, $bucket['data']);
  }

  /**
   * {@inheritdoc}
   */
  public function clear(string $webform_id): void {
    $session = $this->getSession();
    if ($session === NULL || !$session->has(self::SESSION_KEY)) {
      return;
    }
    $store = $this->readStore($session);
    if (!isset($store[$webform_id])) {
      return;
    }
    unset($store[$webform_id]);
    if ($store === []) {
      $session->remove(self::SESSION_KEY);
      return;
    }
    $session->set(self::SESSION_KEY, $store);
  }

  /**
   * {@inheritdoc}
   */
  public function clearAll(): void {
    $session = $this->getSession();
    if ($session === NULL) {
      return;
    }
    $session->remove(self::SESSION_KEY);
  }

  /**
   * Read and validate a webform's bucket.
   *
   * Tries the server-side session first, then falls back to the browser
   * cookie mirror written by the journey handler. The browser cookie has
   * no `_meta` envelope; expiry is enforced by the cookie's Max-Age.
   *
   * @param string $webform_id
   *   The webform id.
   *
   * @return array{data: array<string, mixed>}|null
   *   The bucket when found and non-expired, NULL otherwise.
   */
  protected function readBucket(string $webform_id): ?array {
    $bucket = $this->readBucketFromSession($webform_id);
    if ($bucket !== NULL) {
      return $bucket;
    }
    return $this->readBucketFromCookie($webform_id);
  }

  /**
   * Read the bucket from the server-side PHP session.
   *
   * @param string $webform_id
   *   The webform id.
   *
   * @return array{_meta: array{written_at: int, ttl: int}, data: array<string, mixed>}|null
   *   The bucket when valid and non-expired, NULL otherwise.
   */
  protected function readBucketFromSession(string $webform_id): ?array {
    $session = $this->getSession();
    if ($session === NULL || !$session->has(self::SESSION_KEY)) {
      return NULL;
    }
    $store = $this->readStore($session);
    $bucket = $store[$webform_id] ?? NULL;
    if (!is_array($bucket) || !isset($bucket['_meta'], $bucket['data']) || !is_array($bucket['data'])) {
      return NULL;
    }
    $written_at = (int) ($bucket['_meta']['written_at'] ?? 0);
    $ttl = (int) ($bucket['_meta']['ttl'] ?? 0);
    if ($ttl <= 0 || ($written_at + $ttl) <= $this->time->getRequestTime()) {
      $this->clear($webform_id);
      return NULL;
    }
    return $bucket;
  }

  /**
   * Read the bucket from the browser cookie mirror.
   *
   * The browser drops the cookie automatically once Max-Age elapses, so
   * no server-side TTL re-check is needed here. Malformed JSON is treated
   * as a missing cookie.
   *
   * @param string $webform_id
   *   The webform id.
   *
   * @return array{data: array<string, mixed>}|null
   *   The bucket data when present and parseable, NULL otherwise.
   */
  protected function readBucketFromCookie(string $webform_id): ?array {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return NULL;
    }
    $cookie_value = $request->cookies->get('wo_session_' . $webform_id);
    if (!is_string($cookie_value) || $cookie_value === '') {
      return NULL;
    }
    $data = json_decode($cookie_value, TRUE);
    if (!is_array($data)) {
      return NULL;
    }
    return ['data' => $data];
  }

  /**
   * Read the raw store array from the session.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   *
   * @return array<string, mixed>
   *   The store, or an empty array.
   */
  protected function readStore(SessionInterface $session): array {
    $store = $session->get(self::SESSION_KEY, []);
    return is_array($store) ? $store : [];
  }

  /**
   * Get the active session, when one is available.
   *
   * @return \Symfony\Component\HttpFoundation\Session\SessionInterface|null
   *   The session, or NULL when no request/session is in scope (e.g. CLI).
   */
  protected function getSession(): ?SessionInterface {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL || !$request->hasSession()) {
      return NULL;
    }
    return $request->getSession();
  }

}
