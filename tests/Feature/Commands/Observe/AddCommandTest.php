<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;

describe('observe:add command', function (): void {
    it('creates an observation with minimal required fields', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--narrative' => 'This is a test observation narrative',
        ])->assertSuccessful();

        expect(Observation::count())->toBe(1);

        $observation = Observation::first();
        expect($observation->title)->toBe('Test Observation');
        expect($observation->narrative)->toBe('This is a test observation narrative');
        expect($observation->type)->toBe(ObservationType::Discovery);
        expect($observation->session)->toBeInstanceOf(Session::class);
    });

    it('creates observation with all options', function (): void {
        $session = Session::factory()->create();

        $this->artisan('observe:add', [
            'title' => 'Feature Implementation',
            '--type' => 'feature',
            '--concept' => 'Authentication',
            '--session' => $session->id,
            '--narrative' => 'Implemented OAuth 2.0 authentication',
            '--facts' => ['provider=Google', 'scopes=email,profile'],
            '--files-read' => ['app/Http/Controllers/AuthController.php', 'config/services.php'],
            '--files-modified' => ['routes/web.php', 'config/auth.php'],
        ])->assertSuccessful();

        expect(Observation::count())->toBe(1);

        $observation = Observation::first();
        expect($observation->title)->toBe('Feature Implementation');
        expect($observation->type)->toBe(ObservationType::Feature);
        expect($observation->concept)->toBe('Authentication');
        expect($observation->session_id)->toBe($session->id);
        expect($observation->narrative)->toBe('Implemented OAuth 2.0 authentication');
        expect($observation->facts)->toBeArray();
        expect($observation->facts['provider'])->toBe('Google');
        expect($observation->facts['scopes'])->toBe('email,profile');
        expect($observation->files_read)->toBe([
            'app/Http/Controllers/AuthController.php',
            'config/services.php',
        ]);
        expect($observation->files_modified)->toBe([
            'routes/web.php',
            'config/auth.php',
        ]);
    });

    it('creates ephemeral session when session not provided', function (): void {
        expect(Session::count())->toBe(0);

        $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--narrative' => 'Test narrative',
        ])->assertSuccessful();

        expect(Session::count())->toBe(1);
        expect(Observation::count())->toBe(1);

        $session = Session::first();
        $observation = Observation::first();

        expect($observation->session_id)->toBe($session->id);
        expect($session->project)->toBe('ephemeral');
    });

    it('uses existing session when provided', function (): void {
        $session = Session::factory()->create();

        $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--session' => $session->id,
            '--narrative' => 'Test narrative',
        ])->assertSuccessful();

        expect(Session::count())->toBe(1);
        expect(Observation::count())->toBe(1);

        $observation = Observation::first();
        expect($observation->session_id)->toBe($session->id);
    });

    it('validates type must be valid ObservationType', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--type' => 'invalid-type',
            '--narrative' => 'Test narrative',
        ])->assertFailed();

        expect(Observation::count())->toBe(0);
    });

    it('requires title argument', function (): void {
        expect(function () {
            $this->artisan('observe:add');
        })->toThrow(\RuntimeException::class, 'Not enough arguments');

        expect(Observation::count())->toBe(0);
    });

    it('requires narrative option', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Test Observation',
        ])->assertFailed();

        expect(Observation::count())->toBe(0);
    });

    it('accepts multiple facts in key=value format', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--narrative' => 'Test narrative',
            '--facts' => ['key1=value1', 'key2=value2', 'key3=value3'],
        ])->assertSuccessful();

        $observation = Observation::first();
        expect($observation->facts)->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ]);
    });

    it('ignores facts without equals sign', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--narrative' => 'Test narrative',
            '--facts' => ['valid=fact', 'invalidfact', 'another=valid'],
        ])->assertSuccessful();

        $observation = Observation::first();
        expect($observation->facts)->toBe([
            'valid' => 'fact',
            'another' => 'valid',
        ]);
    });

    it('handles facts with empty keys or values', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--narrative' => 'Test narrative',
            '--facts' => ['valid=fact', '=emptykey', 'emptyvalue=', 'good=value'],
        ])->assertSuccessful();

        $observation = Observation::first();
        expect($observation->facts)->toBe([
            'valid' => 'fact',
            'good' => 'value',
        ]);
    });

    it('accepts bugfix type', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Bug Fix',
            '--type' => 'bugfix',
            '--narrative' => 'Fixed authentication bug',
        ])->assertSuccessful();

        $observation = Observation::first();
        expect($observation->type)->toBe(ObservationType::Bugfix);
    });

    it('accepts refactor type', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Refactor',
            '--type' => 'refactor',
            '--narrative' => 'Refactored service layer',
        ])->assertSuccessful();

        $observation = Observation::first();
        expect($observation->type)->toBe(ObservationType::Refactor);
    });

    it('accepts decision type', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Architecture Decision',
            '--type' => 'decision',
            '--narrative' => 'Decided to use PostgreSQL',
        ])->assertSuccessful();

        $observation = Observation::first();
        expect($observation->type)->toBe(ObservationType::Decision);
    });

    it('accepts change type', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Configuration Change',
            '--type' => 'change',
            '--narrative' => 'Updated cache driver',
        ])->assertSuccessful();

        $observation = Observation::first();
        expect($observation->type)->toBe(ObservationType::Change);
    });

    it('validates session must exist when provided', function (): void {
        $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--session' => 'non-existent-uuid',
            '--narrative' => 'Test narrative',
        ])->assertFailed();

        expect(Observation::count())->toBe(0);
    });

    it('displays success message with observation details', function (): void {
        $output = $this->artisan('observe:add', [
            'title' => 'Test Observation',
            '--type' => 'feature',
            '--concept' => 'Testing',
            '--narrative' => 'Test narrative',
        ]);

        $output->assertSuccessful();
        $output->expectsOutput('Observation created successfully with ID: 1');
    });
});
