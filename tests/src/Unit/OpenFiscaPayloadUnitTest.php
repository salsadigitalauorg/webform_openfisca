<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload;
use Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Tests the OpenFisca Payload classes.
 *
 * @group webform_openfisca
 * @coversDefaultClass \Drupal\webform_openfisca\OpenFisca\Payload
 */
class OpenFiscaPayloadUnitTest extends UnitTestCase {

  use UnitTestTrait;

  /**
   * Tests the OpenFisca RequestPayload.
   *
   * @coversDefaultClass \Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload
   */
  public function testOpenFiscaRequestPayload(): void {
    $json = $this->loadFixture('payload/request.json');
    $payload = RequestPayload::fromJson($json);

    $output = <<<JSON
{"persons":{"Person":{"australian_citizen_or_permanent_resident":{"2024-10-31":"No"},"benefit_youth_allowance_for_jobseekers_maybe_eligible":{"2024-10-31":null},"benefit_jobseekers_maybe_eligible":{"2024-10-31":null},"benefit_abstudy_maybe_eligible":{"2024-10-31":null},"benefit_youth_allowance_maybe_eligible":{"2024-10-31":null},"benefit_austudy_maybe_eligible":{"2024-10-31":null},"benefit_farm_household_allowance_maybe_eligible":{"2024-10-31":null},"benefit_parental_leave_pay_maybe_eligible":{"2024-10-31":null},"benefit_disability_support_payment_maybe_eligible":{"2024-10-31":null},"benefit_carer_payment_maybe_eligible":{"2024-10-31":null},"benefit_age_pension_maybe_eligible":{"2024-10-31":null},"benefit_child_care_subsidy_maybe_eligible":{"2024-10-31":null},"benefit_parenting_payment_maybe_eligible":{"2024-10-31":null},"benefit_family_tax_benefit_maybe_eligible":{"2024-10-31":null},"benefit_eligible":{"2024-10-31":null},"exit":{"2024-10-31":null}}}}
JSON;
    $this->assertEquals($output, $payload->toJson());

    $data = $payload->getData();
    $this->assertArrayHasKey('persons', $data);

    $this->assertTrue($payload->keyPathExists('persons.Person.australian_citizen_or_permanent_resident.2024-10-31'), 'Key path persons.Person.australian_citizen_or_permanent_resident.2024-10-31 does not exist.');
    $this->assertTrue($payload->keyPathExists('persons.Person.benefit_youth_allowance_for_jobseekers_maybe_eligible.2024-10-31'), 'Key path persons.Person.benefit_youth_allowance_for_jobseekers_maybe_eligible.2024-10-31 does not exist.');
    $this->assertTrue($payload->keyPathExists('persons.Person.benefit_jobseekers_maybe_eligible'), 'Key path persons.Person.benefit_jobseekers_maybe_eligible does not exist.');

    $this->assertTrue($payload->keyPathExists(['persons', 'Person', 'australian_citizen_or_permanent_resident', '2024-10-31']), 'Key path array persons.Person.australian_citizen_or_permanent_resident.2024-10-31 does not exist.');
    $this->assertTrue($payload->keyPathExists(['persons', 'Person', 'australian_citizen_or_permanent_resident']), 'Key path array persons.Person.australian_citizen_or_permanent_resident does not exist.');

    $this->assertEquals('No', $payload->getValue('persons.Person.australian_citizen_or_permanent_resident.2024-10-31'));
    $this->assertEquals('No', $payload->getValue(['persons', 'Person', 'australian_citizen_or_permanent_resident', '2024-10-31']));
    $this->assertNull($payload->getValue('persons.Person.benefit_youth_allowance_for_jobseekers_maybe_eligible.2024-10-31'));
    $this->assertNull($payload->getValue(['persons', 'Person', 'benefit_youth_allowance_for_jobseekers_maybe_eligible', '2024-10-31']));

    $payload->setValue('persons.Person.benefit_youth_allowance_for_jobseekers_maybe_eligible.2024-10-31', 'Yes');
    $this->assertEquals('Yes', $payload->getValue('persons.Person.benefit_youth_allowance_for_jobseekers_maybe_eligible.2024-10-31'));
    $payload->setValue(['persons', 'Person', 'benefit_youth_allowance_for_jobseekers_maybe_eligible', '2024-10-31'], 'No');
    $this->assertEquals('No', $payload->getValue(['persons', 'Person', 'benefit_youth_allowance_for_jobseekers_maybe_eligible', '2024-10-31']));

    $this->assertEquals(['persons', 'Person'], $payload->findKey('Person'));
    $this->assertEquals('persons.Person', $payload->findKeyPath('Person'));
    $this->assertNull($payload->findKey('persons.Person'));
    $this->assertNull($payload->findKeyPath('persons.Person'));
    $this->assertEquals(['persons', 'Person', 'benefit_age_pension_maybe_eligible'], $payload->findKey('benefit_age_pension_maybe_eligible'));
    $this->assertEquals('persons.Person.benefit_age_pension_maybe_eligible', $payload->findKeyPath('benefit_age_pension_maybe_eligible'));
    $this->assertEquals(['persons', 'Person', 'benefit_age_pension_maybe_eligible', '2024-10-31'], $payload->findKey('2024-10-31', ['persons', 'Person', 'benefit_age_pension_maybe_eligible']));
    $this->assertEquals('persons.Person.australian_citizen_or_permanent_resident.2024-10-31', $payload->findKeyPath('2024-10-31'));
    $this->assertNull($payload->findKey('persons.Person.benefit_age_pension_maybe_eligible', ['persons', 'Person']));
    $this->assertNull($payload->findKeyPath('persons.Person.benefit_age_pension_maybe_eligible', ['persons', 'Person']));
    $this->assertNull($payload->findKey('persons.Person.benefit_age_pension_maybe_eligible.2024-10-31', ['persons', 'Person', 'benefit_age_pension_maybe_eligible', '2024-10-31']));
    $this->assertNull($payload->findKeyPath('persons.Person.benefit_age_pension_maybe_eligible.2024-10-31', ['persons', 'Person', 'benefit_age_pension_maybe_eligible', '2024-10-31']));

    $this->assertFalse($payload->hasDebugData('test'));
    $this->assertNull($payload->getDebugData('test'));
    $payload->setDebugData('test', 'test value');
    $this->assertTrue($payload->hasDebugData('test'));
    $this->assertEquals('test value', $payload->getDebugData('test'));
    $this->assertEquals(['test' => 'test value'], $payload->getAllDebugData());
    $payload->unsetDebugData('test');
    $this->assertFalse($payload->hasDebugData('test'));
    $payload->setDebugData('test', 'test value');
    $payload->setDebugData('another-test', 'another test value');
    $payload->unsetAllDebugData();
    $this->assertSame(count($payload->getAllDebugData()), 0);
  }

  /**
   * Tests the OpenFisca ResponsePayload.
   *
   * @coversDefaultClass \Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload
   */
  public function testOpenFiscaResponsePayload(): void {
    $this->assertNull(ResponsePayload::fromHttpResponse(NULL));

    $json = $this->loadFixture('payload/response.json');

    $body = $this->prophesize(StreamInterface::class);
    $body->getContents()->willReturn($json);
    $response = $this->prophesize(ResponseInterface::class);
    $response->getBody()->willReturn($body->reveal());

    $payload = ResponsePayload::fromHttpResponse($response->reveal());
    $data = $payload->getData();
    $this->assertArrayHasKey('persons', $data);

    $this->assertEquals('No', $payload->getValue('persons.Person.australian_citizen_or_permanent_resident.2024-10-31'));
    $this->assertFalse($payload->getValue('persons.Person.benefit_youth_allowance_for_jobseekers_maybe_eligible.2024-10-31'));
    $this->assertEquals(0, $payload->getValue('persons.Person.benefit_family_tax_benefit_maybe_eligible.2024-10-31'));

    $this->assertEquals(['persons', 'Person', 'exit'], $payload->findKey('exit'));
  }

}
