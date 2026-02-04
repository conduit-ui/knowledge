<?php

declare(strict_types=1);

use App\Enums\ObservationType;

describe('ObservationType', function (): void {
    it('has all expected cases', function (): void {
        $cases = ObservationType::cases();

        expect($cases)->toHaveCount(6)
            ->and($cases)->toContain(ObservationType::Bugfix)
            ->and($cases)->toContain(ObservationType::Feature)
            ->and($cases)->toContain(ObservationType::Refactor)
            ->and($cases)->toContain(ObservationType::Discovery)
            ->and($cases)->toContain(ObservationType::Decision)
            ->and($cases)->toContain(ObservationType::Change);
    });

    it('has correct string values', function (): void {
        expect(ObservationType::Bugfix->value)->toBe('bugfix')
            ->and(ObservationType::Feature->value)->toBe('feature')
            ->and(ObservationType::Refactor->value)->toBe('refactor')
            ->and(ObservationType::Discovery->value)->toBe('discovery')
            ->and(ObservationType::Decision->value)->toBe('decision')
            ->and(ObservationType::Change->value)->toBe('change');
    });

    it('can be created from string value', function (): void {
        expect(ObservationType::from('bugfix'))->toBe(ObservationType::Bugfix)
            ->and(ObservationType::from('feature'))->toBe(ObservationType::Feature)
            ->and(ObservationType::from('refactor'))->toBe(ObservationType::Refactor)
            ->and(ObservationType::from('discovery'))->toBe(ObservationType::Discovery)
            ->and(ObservationType::from('decision'))->toBe(ObservationType::Decision)
            ->and(ObservationType::from('change'))->toBe(ObservationType::Change);
    });

    it('throws exception for invalid value', function (): void {
        expect(fn (): \App\Enums\ObservationType => ObservationType::from('invalid'))
            ->toThrow(ValueError::class);
    });

    it('can be used in tryFrom safely', function (): void {
        expect(ObservationType::tryFrom('bugfix'))->toBe(ObservationType::Bugfix)
            ->and(ObservationType::tryFrom('invalid'))->toBeNull();
    });

    it('can be used in match expressions', function (): void {
        $type = ObservationType::Feature;

        $result = match ($type) {
            ObservationType::Bugfix => 'fix',
            ObservationType::Feature => 'feat',
            ObservationType::Refactor => 'refactor',
            ObservationType::Discovery => 'discover',
            ObservationType::Decision => 'decide',
            ObservationType::Change => 'change',
        };

        expect($result)->toBe('feat');
    });

    it('can be compared', function (): void {
        expect(ObservationType::Bugfix === ObservationType::Bugfix)->toBeTrue()
            ->and(ObservationType::Bugfix === ObservationType::Feature)->toBeFalse();
    });

    it('is backed by string', function (): void {
        $reflection = new ReflectionEnum(ObservationType::class);

        expect($reflection->isBacked())->toBeTrue()
            ->and($reflection->getBackingType()->getName())->toBe('string');
    });
});
