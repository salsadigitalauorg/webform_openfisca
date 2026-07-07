<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Kernel test for the [webform_openfisca:wo_session:...] token.
 *
 * @group webform_openfisca
 * @group webform_openfisca_tokens
 */
class WebformOpenFiscaSessionTokenKernelTest extends BaseKernelTestCase {

  /**
   * {@inheritDoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->setupWebformOpenFiscaTest();

    // KernelTestBase does not bind a session to the current request by default;
    // attach an in-memory one so the session store can read and write.
    $request = \Drupal::request();
    if (!$request->hasSession()) {
      $request->setSession(new Session(new MockArraySessionStorage()));
    }
  }

  /**
   * Tests that wo_session tokens read from the session store.
   */
  public function testSessionTokenResolution() : void {
    /** @var \Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface $store */
    $store = \Drupal::service('webform_openfisca.session_store');
    $store->setMultiple('test_dac', [
      'total_benefits' => 1234,
      'blocks' => '11,22,33',
      'result_values' => [
        'persons.personA.age' => 42,
      ],
    ], 3600);

    $token_service = \Drupal::token();

    // Scalar value.
    $this->assertEquals(
      'total: 1234',
      $token_service->replace('total: [webform_openfisca:wo_session:test_dac:total_benefits]')
    );

    // Nested dot-path lookup.
    $this->assertEquals(
      'age: 42',
      $token_service->replace('age: [webform_openfisca:wo_session:test_dac:result_values.persons.personA.age]')
    );

    // Missing key returns empty string.
    $this->assertEquals(
      'missing: ',
      $token_service->replace('missing: [webform_openfisca:wo_session:test_dac:not_set]')
    );

    // Different webform id returns empty string.
    $this->assertEquals(
      'other: ',
      $token_service->replace('other: [webform_openfisca:wo_session:other_form:total_benefits]')
    );

    // Malformed token: empty webform id and key (just the prefix) → empty.
    $this->assertEquals(
      'malformed: ',
      $token_service->replace('malformed: [webform_openfisca:wo_session:]')
    );

    // Malformed token: leading dot in the key path (head segment empty) →
    // empty.
    $this->assertEquals(
      'leading-dot: ',
      $token_service->replace('leading-dot: [webform_openfisca:wo_session:test_dac:.foo]')
    );

    // Dot path traversed against a scalar value → empty.
    $this->assertEquals(
      'scalar-traverse: ',
      $token_service->replace('scalar-traverse: [webform_openfisca:wo_session:test_dac:total_benefits.x]')
    );
  }

  /**
   * Tests that resolving wo_session adds the session cache context.
   */
  public function testSessionCacheContextBubbles() : void {
    /** @var \Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface $store */
    $store = \Drupal::service('webform_openfisca.session_store');
    $store->set('test_dac', 'total_benefits', 99, 3600);

    $bubbleable = new BubbleableMetadata();
    \Drupal::token()->replace(
      '[webform_openfisca:wo_session:test_dac:total_benefits]',
      [],
      [],
      $bubbleable,
    );

    $this->assertContains('session', $bubbleable->getCacheContexts());
    // Session-scoped tokens must be uncacheable so re-submissions within the
    // same session don't serve stale values from render cache.
    $this->assertSame(0, $bubbleable->getCacheMaxAge());
  }

  /**
   * Tests that an expired bucket yields empty replacements.
   *
   * Manipulates the underlying session attribute directly to simulate a
   * bucket whose written_at + ttl is already in the past, avoiding
   * sleep-based timing in tests.
   */
  public function testExpiredBucketYieldsEmpty() : void {
    $session = \Drupal::request()->getSession();
    $session->set(OpenFiscaSessionStoreInterface::SESSION_KEY, [
      'test_dac' => [
        '_meta' => [
          'written_at' => 1,
          'ttl' => 1,
        ],
        'data' => ['total_benefits' => 5],
      ],
    ]);

    $this->assertEquals(
      'total: ',
      \Drupal::token()->replace('total: [webform_openfisca:wo_session:test_dac:total_benefits]')
    );
    // Reading expired buckets should clear them.
    $this->assertFalse($session->has(OpenFiscaSessionStoreInterface::SESSION_KEY));
  }

  /**
   * Sanity check on the SESSION_KEY constant value.
   */
  public function testSessionKeyConstant() : void {
    $this->assertSame('_webform_openfisca', OpenFiscaSessionStoreInterface::SESSION_KEY);
  }

}
