<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Configuration\Defaults;

final class DefaultsTest extends TestCase
{
    // --- get() / all() ---

    public function testGetKnownKeyReturnsItsValue(): void
    {
        $defaults = new Defaults();

        $result = $defaults->get('max_retries');

        $this->assertTrue($result['found']);
        $this->assertSame(3, $result['value']);
    }

    public function testGetUnknownKeyIsNotFound(): void
    {
        $defaults = new Defaults();

        $result = $defaults->get('made_up_key');

        $this->assertFalse($result['found']);
        $this->assertNull($result['value']);
    }

    public function testAllReturnsEveryDeclaredDefault(): void
    {
        $defaults = new Defaults();

        $all = $defaults->all();

        $this->assertSame(true, $all['least_privilege']);
        $this->assertSame(true, $all['destructive_action_requires_authorization']);
        $this->assertSame(3, $all['max_retries']);
        $this->assertSame(true, $all['validate_after_material_phase']);
        $this->assertSame(true, $all['structured_event_logging']);
        $this->assertSame('project_local', $all['output_location']);
        $this->assertSame(true, $all['deterministic_planning']);
    }

    // --- isMandatory() ---

    public function testGovernanceAndSecurityDefaultsAreMandatory(): void
    {
        $defaults = new Defaults();

        $this->assertTrue($defaults->isMandatory('least_privilege'));
        $this->assertTrue($defaults->isMandatory('destructive_action_requires_authorization'));
        $this->assertTrue($defaults->isMandatory('validate_after_material_phase'));
        $this->assertTrue($defaults->isMandatory('structured_event_logging'));
    }

    public function testOtherDefaultsAreNotMandatory(): void
    {
        $defaults = new Defaults();

        $this->assertFalse($defaults->isMandatory('max_retries'));
        $this->assertFalse($defaults->isMandatory('output_location'));
        $this->assertFalse($defaults->isMandatory('deterministic_planning'));
    }

    // --- validateOverride(): shape ---

    public function testOverrideOfAnUnknownKeyIsRejected(): void
    {
        $defaults = new Defaults();

        $result = $defaults->validateOverride('made_up_key', 'anything', 'project_settings.json');

        $this->assertSame('unknown_key', $result['outcome']);
    }

    public function testOverrideWithoutASourceIsInvalid(): void
    {
        $defaults = new Defaults();

        $result = $defaults->validateOverride('max_retries', 5, null);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('source', $result['error']);
    }

    public function testOverrideWithAnEmptySourceIsInvalid(): void
    {
        $defaults = new Defaults();

        $result = $defaults->validateOverride('max_retries', 5, '');

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- validateOverride(): the mandatory-weakening gate ---

    public function testOverridingANonMandatoryDefaultIsAccepted(): void
    {
        $defaults = new Defaults();

        $result = $defaults->validateOverride('max_retries', 5, 'project_settings.json');

        $this->assertSame('accepted', $result['outcome']);
        $this->assertSame(5, $result['value']);
    }

    public function testWeakeningAMandatoryDefaultIsRejected(): void
    {
        $defaults = new Defaults();

        $result = $defaults->validateOverride('least_privilege', false, 'project_settings.json');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('mandatory', $result['error']);
    }

    public function testReaffirmingAMandatoryDefaultAtItsOwnValueIsAccepted(): void
    {
        $defaults = new Defaults();

        $result = $defaults->validateOverride('destructive_action_requires_authorization', true, 'project_settings.json');

        $this->assertSame('accepted', $result['outcome']);
    }

    #[DataProvider('mandatoryKeyProvider')]
    public function testEveryMandatoryKeyRejectsBeingWeakenedToFalse(string $key): void
    {
        $defaults = new Defaults();

        $result = $defaults->validateOverride($key, false, 'project_settings.json');

        $this->assertSame('rejected', $result['outcome']);
    }

    public static function mandatoryKeyProvider(): array
    {
        return [
            ['least_privilege'],
            ['destructive_action_requires_authorization'],
            ['validate_after_material_phase'],
            ['structured_event_logging'],
        ];
    }

    public function testOverridingANonBooleanNonMandatoryDefaultNeverTriggersTheWeakeningCheck(): void
    {
        $defaults = new Defaults();

        $result = $defaults->validateOverride('output_location', '/tmp/custom', 'project_settings.json');

        $this->assertSame('accepted', $result['outcome']);
    }
}
