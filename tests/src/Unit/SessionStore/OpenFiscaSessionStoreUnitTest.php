<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit\SessionStore;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStore;
use Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Unit tests for the OpenFisca session store.
 *
 * @group webform_openfisca
 * @group webform_openfisca_session_store
 * @coversDefaultClass \Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStore
 */
class OpenFiscaSessionStoreUnitTest extends UnitTestCase {

  /**
   * The fake session attached to the request stack.
   */
  protected Session $session;

  /**
   * Mutable "now" used by the time mock.
   */
  protected int $now = 1700000000;

  /**
   * The store under test.
   */
  protected OpenFiscaSessionStoreInterface $store;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->session = new Session(new MockArraySessionStorage());
    $request = new Request();
    $request->setSession($this->session);
    $request_stack = new RequestStack();
    $request_stack->push($request);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn () => $this->now);

    $this->store = new OpenFiscaSessionStore($request_stack, $time);
  }

  /**
   * Tests basic set/get/has/getAll/clear behaviour.
   */
  public function testSetGetAndClear(): void {
    $this->store->setMultiple('form_a', [
      'result_values' => ['persons.personA.age' => 30],
      'total_benefits' => 1500,
    ], 3600);

    $this->assertTrue($this->store->has('form_a', 'result_values'));
    $this->assertSame(1500, $this->store->get('form_a', 'total_benefits'));
    $this->assertSame('fallback', $this->store->get('form_a', 'missing', 'fallback'));
    $this->assertSame([
      'result_values' => ['persons.personA.age' => 30],
      'total_benefits' => 1500,
    ], $this->store->getAll('form_a'));

    $this->store->set('form_a', 'blocks', '1,2,3', 3600);
    $this->assertSame('1,2,3', $this->store->get('form_a', 'blocks'));

    $this->store->clear('form_a');
    $this->assertFalse($this->store->has('form_a', 'result_values'));
    $this->assertSame([], $this->store->getAll('form_a'));
  }

  /**
   * Tests that buckets are namespaced per webform id.
   */
  public function testWebformNamespacing(): void {
    $this->store->set('form_a', 'total_benefits', 100, 3600);
    $this->store->set('form_b', 'total_benefits', 200, 3600);

    $this->assertSame(100, $this->store->get('form_a', 'total_benefits'));
    $this->assertSame(200, $this->store->get('form_b', 'total_benefits'));

    $this->store->clear('form_a');
    $this->assertNull($this->store->get('form_a', 'total_benefits'));
    $this->assertSame(200, $this->store->get('form_b', 'total_benefits'));
  }

  /**
   * Tests that expired buckets are silently cleared on read.
   */
  public function testTtlExpiry(): void {
    $this->store->set('form_a', 'result_values', ['x' => 1], 60);
    $this->assertSame(['x' => 1], $this->store->get('form_a', 'result_values'));

    // Advance past the TTL.
    $this->now += 61;
    $this->assertNull($this->store->get('form_a', 'result_values'));
    $this->assertFalse($this->store->has('form_a', 'result_values'));
    $this->assertSame([], $this->store->getAll('form_a'));
    // Underlying session should have been cleared too.
    $this->assertFalse($this->session->has(OpenFiscaSessionStoreInterface::SESSION_KEY));
  }

  /**
   * Tests that a non-positive TTL clears the bucket and writes nothing.
   */
  public function testNonPositiveTtlClears(): void {
    $this->store->set('form_a', 'total_benefits', 100, 3600);
    $this->assertSame(100, $this->store->get('form_a', 'total_benefits'));

    $this->store->setMultiple('form_a', ['total_benefits' => 999], 0);
    $this->assertNull($this->store->get('form_a', 'total_benefits'));
  }

  /**
   * Tests that successive writes merge values within the same bucket.
   */
  public function testWritesMergeValues(): void {
    $this->store->setMultiple('form_a', ['a' => 1], 3600);
    $this->store->setMultiple('form_a', ['b' => 2], 3600);

    $this->assertSame(['a' => 1, 'b' => 2], $this->store->getAll('form_a'));
  }

  /**
   * Tests that clearing the only bucket removes the SESSION_KEY entirely.
   */
  public function testClearLastBucketRemovesSessionKey(): void {
    $this->store->set('only', 'x', 1, 3600);
    $this->assertTrue($this->session->has(OpenFiscaSessionStoreInterface::SESSION_KEY));

    $this->store->clear('only');
    $this->assertFalse(
      $this->session->has(OpenFiscaSessionStoreInterface::SESSION_KEY),
      'When the last bucket is cleared, the session key should be removed entirely.',
    );
  }

  /**
   * Tests clearAll().
   */
  public function testClearAll(): void {
    $this->store->set('form_a', 'x', 1, 3600);
    $this->store->set('form_b', 'y', 2, 3600);
    $this->store->clearAll();

    $this->assertSame([], $this->store->getAll('form_a'));
    $this->assertSame([], $this->store->getAll('form_b'));
    $this->assertFalse($this->session->has(OpenFiscaSessionStoreInterface::SESSION_KEY));
  }

  /**
   * Tests that the store no-ops when no request/session is in scope.
   */
  public function testNoActiveRequest(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($this->now);
    $store = new OpenFiscaSessionStore(new RequestStack(), $time);

    $store->set('form_a', 'x', 1, 3600);
    $this->assertNull($store->get('form_a', 'x'));
    $this->assertSame([], $store->getAll('form_a'));
    // No exception thrown.
    $store->clear('form_a');
    $store->clearAll();
  }

}
