<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\OpenFisca\Payload;

use Drupal\webform_openfisca\OpenFisca\Payload;
use Psr\Http\Message\ResponseInterface;

/**
 * OpenFisca response payload.
 */
class ResponsePayload extends Payload {

  /**
   * Create a payload from JSON in an HTTP Response.
   *
   * @param \Psr\Http\Message\ResponseInterface|null $response
   *   The response.
   *
   * @return self|null
   *   The payload, or NULL upon error.
   */
  public static function fromHttpResponse(?ResponseInterface $response) : ?static {
    return ($response instanceof ResponseInterface) ? static::fromJson($response->getBody()->getContents()) : NULL;
  }

}
