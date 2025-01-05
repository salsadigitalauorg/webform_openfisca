<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca;

/**
 * Interface for RAC content helper.
 */
interface RacContentHelperInterface {

  /**
   * Find a matching RAC Redirect rule for a webform.
   *
   * @param string $webform_id
   *   The webform ID.
   * @param array $matching_values
   *   The variable/value pairs to match with RAC rules.
   *
   * @return string|null
   *   The redirect URI.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   Potential exception if the node does not have a canonical route.
   */
  public function findRacRedirectForWebform(string $webform_id, array $matching_values): ?string;

}
