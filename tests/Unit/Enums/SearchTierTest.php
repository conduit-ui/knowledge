<?php

declare(strict_types=1);

use App\Enums\SearchTier;

describe('SearchTier', function (): void {
    it('has four cases', function (): void {
        expect(SearchTier::cases())->toHaveCount(4);
    });

    it('has correct string values', function (): void {
        expect(SearchTier::Working->value)->toBe('working');
        expect(SearchTier::Recent->value)->toBe('recent');
        expect(SearchTier::Structured->value)->toBe('structured');
        expect(SearchTier::Archive->value)->toBe('archive');
    });

    it('returns human-readable labels', function (): void {
        expect(SearchTier::Working->label())->toBe('Working Context');
        expect(SearchTier::Recent->label())->toBe('Recent (14 days)');
        expect(SearchTier::Structured->label())->toBe('Structured Storage');
        expect(SearchTier::Archive->label())->toBe('Archive');
    });

    it('returns search order from narrow to wide', function (): void {
        $order = SearchTier::searchOrder();

        expect($order)->toHaveCount(4);
        expect($order[0])->toBe(SearchTier::Working);
        expect($order[1])->toBe(SearchTier::Recent);
        expect($order[2])->toBe(SearchTier::Structured);
        expect($order[3])->toBe(SearchTier::Archive);
    });

    it('can be created from string value', function (): void {
        expect(SearchTier::from('working'))->toBe(SearchTier::Working);
        expect(SearchTier::from('recent'))->toBe(SearchTier::Recent);
        expect(SearchTier::from('structured'))->toBe(SearchTier::Structured);
        expect(SearchTier::from('archive'))->toBe(SearchTier::Archive);
    });

    it('returns null for invalid value with tryFrom', function (): void {
        expect(SearchTier::tryFrom('invalid'))->toBeNull();
        expect(SearchTier::tryFrom(''))->toBeNull();
    });
});
