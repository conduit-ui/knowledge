<?php

declare(strict_types=1);

use App\Services\WriteGateService;

describe('WriteGateService', function (): void {
    describe('behavioral_impact criterion', function (): void {
        it('passes entries with critical priority', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'priority' => 'critical',
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('behavioral_impact');
        });

        it('passes entries with high priority', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'priority' => 'high',
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('behavioral_impact');
        });

        it('passes entries with security category', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'category' => 'security',
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('behavioral_impact');
        });

        it('passes entries with deployment category', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'category' => 'deployment',
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('behavioral_impact');
        });

        it('passes entries with architecture category', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'category' => 'architecture',
            ]);

            expect($result['passed'])->toBeTrue();
        });

        it('passes entries with behavioral signal words', function (): void {
            $gate = new WriteGateService;
            $signals = ['must use this', 'always run tests', 'never deploy on friday', 'required for all', 'breaking change detected', 'migration needed'];

            foreach ($signals as $signal) {
                $result = $gate->evaluate([
                    'title' => 'Entry',
                    'content' => "This is {$signal} in production",
                ]);

                expect($result['passed'])->toBeTrue("Failed for signal: {$signal}");
            }
        });
    });

    describe('commitment_weight criterion', function (): void {
        it('passes architecture entries with high confidence', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'category' => 'architecture',
                'confidence' => 80,
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('commitment_weight');
        });

        it('passes validated entries', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'status' => 'validated',
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('commitment_weight');
        });

        it('passes entries with commitment language', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'We decided to use PostgreSQL for all new services',
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('commitment_weight');
        });
    });

    describe('decision_rationale criterion', function (): void {
        it('passes entries explaining why decisions were made', function (): void {
            $gate = new WriteGateService;
            $signals = [
                'because the latency was too high',
                'the reason for this choice',
                'rationale behind the architecture',
                'we considered the trade-off',
                'alternative approaches were evaluated',
                'pros and cons of each approach',
                'decided against using microservices',
                'opted for a monolith',
                'why we chose Laravel',
            ];

            foreach ($signals as $signal) {
                $result = $gate->evaluate([
                    'title' => 'Entry',
                    'content' => $signal,
                ]);

                expect($result['passed'])->toBeTrue("Failed for signal: {$signal}");
                expect($result['matched'])->toContain('decision_rationale');
            }
        });
    });

    describe('durable_facts criterion', function (): void {
        it('passes entries with high confidence', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'confidence' => 85,
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('durable_facts');
        });

        it('passes validated entries with a category', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Generic title',
                'content' => 'Generic content',
                'status' => 'validated',
                'category' => 'testing',
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toContain('durable_facts');
        });

        it('passes entries with durable fact language', function (): void {
            $gate = new WriteGateService;
            $signals = [
                'the api contract requires json',
                'schema definition for users table',
                'protocol for service communication',
                'invariant: balance must never be negative',
                'constraint on the data model',
            ];

            foreach ($signals as $signal) {
                $result = $gate->evaluate([
                    'title' => 'Entry',
                    'content' => $signal,
                ]);

                expect($result['passed'])->toBeTrue("Failed for signal: {$signal}");
                expect($result['matched'])->toContain('durable_facts');
            }
        });
    });

    describe('explicit_instruction criterion', function (): void {
        it('passes entries with instruction tags', function (): void {
            $gate = new WriteGateService;
            $instructionTags = ['rule', 'instruction', 'policy', 'guideline', 'standard', 'process'];

            foreach ($instructionTags as $tag) {
                $result = $gate->evaluate([
                    'title' => 'Entry',
                    'content' => 'Generic content here',
                    'tags' => [$tag],
                ]);

                expect($result['passed'])->toBeTrue("Failed for tag: {$tag}");
                expect($result['matched'])->toContain('explicit_instruction');
            }
        });

        it('passes entries with imperative language', function (): void {
            $gate = new WriteGateService;
            $signals = [
                'you must run tests before merging',
                'do not use raw SQL queries',
                "don't commit secrets to git",
                'ensure that all endpoints are authenticated',
                'make sure to validate inputs',
                'always use prepared statements',
                'never use eval in production',
            ];

            foreach ($signals as $signal) {
                $result = $gate->evaluate([
                    'title' => 'Entry',
                    'content' => $signal,
                ]);

                expect($result['passed'])->toBeTrue("Failed for signal: {$signal}");
                expect($result['matched'])->toContain('explicit_instruction');
            }
        });
    });

    describe('gate rejection', function (): void {
        it('rejects entries that match no criteria', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Misc note',
                'content' => 'Talked to Bob about lunch plans',
                'priority' => 'low',
                'confidence' => 10,
            ]);

            expect($result['passed'])->toBeFalse();
            expect($result['matched'])->toBeEmpty();
            expect($result['reason'])->toContain('does not meet any write gate criteria');
            expect($result['reason'])->toContain('--force');
        });

        it('includes enabled criteria names in rejection reason', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Irrelevant',
                'content' => 'Nothing useful',
                'priority' => 'low',
                'confidence' => 10,
            ]);

            expect($result['reason'])->toContain('Behavioral Impact');
            expect($result['reason'])->toContain('Durable Facts');
        });
    });

    describe('configurable criteria', function (): void {
        it('respects disabled criteria', function (): void {
            $gate = new WriteGateService([
                'behavioral_impact' => false,
                'commitment_weight' => false,
                'decision_rationale' => false,
                'durable_facts' => false,
                'explicit_instruction' => true,
            ]);

            // This entry would pass behavioral_impact but it's disabled
            $result = $gate->evaluate([
                'title' => 'Entry',
                'content' => 'Generic stuff',
                'priority' => 'critical',
            ]);

            expect($result['passed'])->toBeFalse();
            expect($result['matched'])->not->toContain('behavioral_impact');
        });

        it('passes when entry matches an enabled criterion', function (): void {
            $gate = new WriteGateService([
                'behavioral_impact' => false,
                'commitment_weight' => false,
                'decision_rationale' => true,
                'durable_facts' => false,
                'explicit_instruction' => false,
            ]);

            $result = $gate->evaluate([
                'title' => 'Why we chose Qdrant',
                'content' => 'The rationale was performance and simplicity',
            ]);

            expect($result['passed'])->toBeTrue();
            expect($result['matched'])->toBe(['decision_rationale']);
        });

        it('rejects everything when all criteria are disabled', function (): void {
            $gate = new WriteGateService([
                'behavioral_impact' => false,
                'commitment_weight' => false,
                'decision_rationale' => false,
                'durable_facts' => false,
                'explicit_instruction' => false,
            ]);

            $result = $gate->evaluate([
                'title' => 'Critical security fix',
                'content' => 'You must apply this patch immediately because of a vulnerability',
                'priority' => 'critical',
                'confidence' => 100,
                'status' => 'validated',
                'category' => 'security',
                'tags' => ['rule'],
            ]);

            expect($result['passed'])->toBeFalse();
        });
    });

    describe('multiple criteria matching', function (): void {
        it('can match multiple criteria simultaneously', function (): void {
            $gate = new WriteGateService;
            $result = $gate->evaluate([
                'title' => 'Security policy',
                'content' => 'You must always validate inputs because of injection risks',
                'priority' => 'critical',
                'confidence' => 95,
                'status' => 'validated',
                'category' => 'security',
                'tags' => ['policy'],
            ]);

            expect($result['passed'])->toBeTrue();
            expect(count($result['matched']))->toBeGreaterThan(1);
        });
    });

    describe('default criteria', function (): void {
        it('returns all five criteria enabled by default', function (): void {
            $defaults = WriteGateService::defaultCriteria();

            expect($defaults)->toBe([
                'behavioral_impact' => true,
                'commitment_weight' => true,
                'decision_rationale' => true,
                'durable_facts' => true,
                'explicit_instruction' => true,
            ]);
        });
    });

    describe('loadCriteria fallback', function (): void {
        it('falls back to defaultCriteria when config returns empty array', function (): void {
            config(['write-gate.criteria' => []]);

            // Instantiate without explicit criteria so it loads from config
            $gate = new WriteGateService;

            expect($gate->getEnabledCriteria())->toBe(WriteGateService::defaultCriteria());
        });
    });

    describe('getEnabledCriteria', function (): void {
        it('returns the configured criteria', function (): void {
            $criteria = [
                'behavioral_impact' => true,
                'commitment_weight' => false,
                'decision_rationale' => true,
                'durable_facts' => false,
                'explicit_instruction' => true,
            ];
            $gate = new WriteGateService($criteria);

            expect($gate->getEnabledCriteria())->toBe($criteria);
        });
    });
});
