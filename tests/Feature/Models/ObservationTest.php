<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;

describe('Observation Model', function (): void {
    it('can be created with factory', function (): void {
        $observation = Observation::factory()->create();

        expect($observation)->toBeInstanceOf(Observation::class);
        expect($observation->id)->toBeInt();
    });

    it('has fillable attributes', function (): void {
        $session = Session::factory()->create();

        $observation = Observation::factory()->forSession($session)->create([
            'title' => 'Test Observation',
            'subtitle' => 'Test Subtitle',
            'narrative' => 'Test narrative content',
            'concept' => 'testing',
            'type' => ObservationType::Feature,
        ]);

        expect($observation->title)->toBe('Test Observation');
        expect($observation->subtitle)->toBe('Test Subtitle');
        expect($observation->narrative)->toBe('Test narrative content');
        expect($observation->concept)->toBe('testing');
        expect($observation->type)->toBe(ObservationType::Feature);
    });

    it('casts type to ObservationType enum', function (): void {
        $observation = Observation::factory()->bugfix()->create();

        expect($observation->type)->toBeInstanceOf(ObservationType::class);
        expect($observation->type)->toBe(ObservationType::Bugfix);
    });

    it('casts json fields to arrays', function (): void {
        $observation = Observation::factory()->create([
            'facts' => ['key' => 'value'],
            'files_read' => ['file1.php', 'file2.php'],
            'files_modified' => ['file3.php'],
            'tools_used' => ['Read', 'Write', 'Bash'],
        ]);

        expect($observation->facts)->toBeArray();
        expect($observation->facts)->toBe(['key' => 'value']);
        expect($observation->files_read)->toBeArray();
        expect($observation->files_modified)->toBeArray();
        expect($observation->tools_used)->toBeArray();
    });

    it('belongs to a session', function (): void {
        $session = Session::factory()->create();
        $observation = Observation::factory()->forSession($session)->create();

        expect($observation->session)->toBeInstanceOf(Session::class);
        expect($observation->session->id)->toBe($session->id);
    });

    it('has token tracking fields', function (): void {
        $observation = Observation::factory()->create([
            'work_tokens' => 1500,
            'read_tokens' => 8000,
        ]);

        expect($observation->work_tokens)->toBe(1500);
        expect($observation->read_tokens)->toBe(8000);
    });

    it('can be created with different types', function (): void {
        $bugfix = Observation::factory()->bugfix()->create();
        $feature = Observation::factory()->feature()->create();
        $discovery = Observation::factory()->discovery()->create();
        $decision = Observation::factory()->decision()->create();

        expect($bugfix->type)->toBe(ObservationType::Bugfix);
        expect($feature->type)->toBe(ObservationType::Feature);
        expect($discovery->type)->toBe(ObservationType::Discovery);
        expect($decision->type)->toBe(ObservationType::Decision);
    });

    it('can be created with specific concept', function (): void {
        $observation = Observation::factory()->withConcept('authentication')->create();

        expect($observation->concept)->toBe('authentication');
    });

    it('allows null optional fields', function (): void {
        $observation = Observation::factory()->create([
            'subtitle' => null,
            'concept' => null,
            'facts' => null,
            'files_read' => null,
            'files_modified' => null,
            'tools_used' => null,
        ]);

        expect($observation->subtitle)->toBeNull();
        expect($observation->concept)->toBeNull();
        expect($observation->facts)->toBeNull();
        expect($observation->files_read)->toBeNull();
        expect($observation->files_modified)->toBeNull();
        expect($observation->tools_used)->toBeNull();
    });

    it('has default token values', function (): void {
        $session = Session::factory()->create();

        $observation = Observation::create([
            'session_id' => $session->id,
            'type' => ObservationType::Feature,
            'title' => 'Test',
            'narrative' => 'Test narrative',
        ]);

        // Refresh to get database defaults
        $observation->refresh();

        expect($observation->work_tokens)->toBe(0);
        expect($observation->read_tokens)->toBe(0);
    });

    it('stores all observation types from enum', function (): void {
        $session = Session::factory()->create();

        foreach (ObservationType::cases() as $type) {
            $observation = Observation::factory()->forSession($session)->create([
                'type' => $type,
            ]);

            expect($observation->type)->toBe($type);
        }
    });
});
