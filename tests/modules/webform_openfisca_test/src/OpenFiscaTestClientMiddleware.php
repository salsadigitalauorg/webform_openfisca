<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca_test;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Promise\FulfilledPromise;
use Psr\Http\Message\RequestInterface;

/**
 * Guzzle middleware for testing the OpenFisca Client.
 */
class OpenFiscaTestClientMiddleware {

  /**
   * Invoked method that returns a promise.
   */
  public function __invoke() : \Closure {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        $uri = $request->getUri();
        $host = $uri->getHost();

        // Return 404 for the 'invalid-api' API endpoint.
        if ($host === 'invalid-api.openfisca.test') {
          return $this->returnHttpNotFound();
        }

        // Use the test fixtures if the API host matches a fixture directory.
        $fixture_dir = $this->getFixtureDirectory($host);
        if (is_dir($fixture_dir)) {
          $fixture = $this->loadFixture($request);
          // Return the JSON fixture file if exists.
          if ($fixture !== FALSE) {
            return new FulfilledPromise(new Response(200, [], $fixture));
          }
          return $this->returnHttpNotFound();
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options);
      };
    };
  }

  /**
   * Load the JSON fixture for a request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return string|false
   *   Content of the fixture or FALSE if not exist.
   */
  protected function loadFixture(RequestInterface $request): string|false {
    $uri = $request->getUri();
    $host = $uri->getHost();
    $path = $uri->getPath();
    $suffixes = [];

    $query = $uri->getQuery();
    if ($query !== '') {
      $suffixes[] = substr(hash('sha256', $query), 0, 24);
    }

    $method = strtoupper($request->getMethod());
    if ($method === 'POST') {
      $body = (string) $request->getBody();
      $suffixes[] = substr(hash('sha256', $body), 0, 24);
    }

    $fixture = $this->getFixturePath($host, $path, $method, implode('-', $suffixes));
    if (!file_exists($fixture)) {
      // Attempt to match a fixture with '-notes-*' suffix.
      $pattern = str_replace('.json', '-notes-*.json', $fixture);
      $matches = glob($pattern);
      if (empty($matches)) {
        return FALSE;
      }
      $fixture = reset($matches);
    }
    return file_get_contents($fixture);
  }

  /**
   * Get the fixture directory for a request.
   *
   * @param string $host
   *   The URI host.
   *
   * @return string
   *   The fixture directory.
   */
  protected function getFixtureDirectory(string $host): string {
    return dirname(__DIR__, 3) . '/fixtures/' . $host;
  }

  /**
   * Get the path of a fixture for a request.
   *
   * @param string $host
   *   The URI host.
   * @param string $path
   *   The URI path.
   * @param string $method
   *   The request method.
   * @param string $suffix
   *   Additional suffix for the path.
   *
   * @return string
   *   The path to the fixture.
   */
  protected function getFixturePath(string $host, string $path, string $method = 'GET', string $suffix = ''): string {
    return $this->getFixtureDirectory($host) . '/' . '__' . $method . '/' . $path . ($suffix ? "-$suffix" : '') . '.json';
  }

  /**
   * Return HTTP error 404.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   The promise.
   */
  protected function returnHttpNotFound() : PromiseInterface {
    return new FulfilledPromise(new Response(404, [], 'Test API Not Found'));
  }

}
