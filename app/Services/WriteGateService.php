<?php

declare(strict_types=1);

namespace App\Services;

class WriteGateService
{
    /** @var array<string, bool> */
    private array $enabledCriteria;

    /**
     * @param  array<string, bool>|null  $criteria  Override criteria (null = use config defaults)
     */
    public function __construct(?array $criteria = null)
    {
        $this->enabledCriteria = $criteria ?? $this->loadCriteria();
    }

    /**
     * Evaluate a knowledge entry against the write gate criteria.
     *
     * @param  array<string, mixed>  $entry
     * @return array{passed: bool, matched: array<string>, reason: string}
     */
    public function evaluate(array $entry): array
    {
        $matched = [];

        foreach ($this->enabledCriteria as $criterion => $enabled) {
            if (! $enabled) {
                continue;
            }

            if ($this->matchesCriterion($criterion, $entry)) {
                $matched[] = $criterion;
            }
        }

        if ($matched === []) {
            return [
                'passed' => false,
                'matched' => [],
                'reason' => 'Entry does not meet any write gate criteria. Knowledge must demonstrate at least one of: '
                    .$this->formatEnabledCriteria()
                    .'. Use --force to bypass.',
            ];
        }

        return [
            'passed' => true,
            'matched' => $matched,
            'reason' => '',
        ];
    }

    /**
     * Get the currently enabled criteria.
     *
     * @return array<string, bool>
     */
    public function getEnabledCriteria(): array
    {
        return $this->enabledCriteria;
    }

    /**
     * Check if an entry matches a specific criterion.
     *
     * @param  array<string, mixed>  $entry
     */
    private function matchesCriterion(string $criterion, array $entry): bool
    {
        return match ($criterion) {
            'behavioral_impact' => $this->hasBehavioralImpact($entry),
            'commitment_weight' => $this->hasCommitmentWeight($entry),
            'decision_rationale' => $this->hasDecisionRationale($entry),
            'durable_facts' => $this->hasDurableFacts($entry),
            'explicit_instruction' => $this->hasExplicitInstruction($entry),
            default => false,
        };
    }

    /**
     * Behavioral Impact: Entry describes something that changes how the system or team operates.
     * Signals: high/critical priority, deployment/security/architecture categories.
     *
     * @param  array<string, mixed>  $entry
     */
    private function hasBehavioralImpact(array $entry): bool
    {
        $priority = $entry['priority'] ?? 'medium';
        if (in_array($priority, ['critical', 'high'], true)) {
            return true;
        }

        $category = $entry['category'] ?? null;
        if (in_array($category, ['deployment', 'security', 'architecture'], true)) {
            return true;
        }

        $text = strtolower($entry['title'].' '.$entry['content']);
        $signals = ['must ', 'always ', 'never ', 'required', 'breaking change', 'migration'];

        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Commitment Weight: Entry represents a decision that constrains future choices.
     * Signals: architecture category, high confidence, validated status.
     *
     * @param  array<string, mixed>  $entry
     */
    private function hasCommitmentWeight(array $entry): bool
    {
        $category = $entry['category'] ?? null;
        $confidence = $entry['confidence'] ?? 0;

        if ($category === 'architecture' && $confidence >= 80) {
            return true;
        }

        $status = $entry['status'] ?? 'draft';
        if ($status === 'validated') {
            return true;
        }

        $text = strtolower($entry['title'].' '.$entry['content']);
        $signals = ['we chose', 'we decided', 'adopted', 'standard', 'convention', 'commitment'];

        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decision Rationale: Entry explains *why* something was decided.
     * Signals: content with reasoning patterns, architecture/debugging categories.
     *
     * @param  array<string, mixed>  $entry
     */
    private function hasDecisionRationale(array $entry): bool
    {
        $text = strtolower($entry['title'].' '.$entry['content']);
        $signals = [
            'because', 'reason', 'rationale', 'trade-off', 'tradeoff',
            'alternative', 'pros and cons', 'decided against', 'opted for',
            'why we', 'the decision',
        ];

        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Durable Facts: Entry captures stable, long-lived knowledge.
     * Signals: high confidence, validated status, specific categories.
     *
     * @param  array<string, mixed>  $entry
     */
    private function hasDurableFacts(array $entry): bool
    {
        $confidence = $entry['confidence'] ?? 0;
        if ($confidence >= 80) {
            return true;
        }

        $status = $entry['status'] ?? 'draft';
        $category = $entry['category'] ?? null;
        if ($status === 'validated' && $category !== null) {
            return true;
        }

        $text = strtolower($entry['title'].' '.$entry['content']);
        $signals = [
            'specification', 'api contract', 'schema', 'protocol',
            'invariant', 'constraint', 'requirement', 'documentation',
        ];

        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Explicit Instruction: Entry contains a direct instruction or rule.
     * Signals: imperative language, rule patterns, tags indicating instructions.
     *
     * @param  array<string, mixed>  $entry
     */
    private function hasExplicitInstruction(array $entry): bool
    {
        $tags = $entry['tags'] ?? [];
        $instructionTags = ['rule', 'instruction', 'policy', 'guideline', 'standard', 'process'];

        foreach ($tags as $tag) {
            if (in_array(strtolower($tag), $instructionTags, true)) {
                return true;
            }
        }

        $text = strtolower($entry['title'].' '.$entry['content']);
        $signals = [
            'you must', 'do not', 'don\'t', 'ensure that', 'make sure',
            'rule:', 'policy:', 'always use', 'never use', 'step 1',
        ];

        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, bool>
     */
    private function loadCriteria(): array
    {
        /** @var array<string, bool> $configured */
        $configured = config('write-gate.criteria', []);

        if ($configured !== []) {
            return $configured;
        }

        return self::defaultCriteria();
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultCriteria(): array
    {
        return [
            'behavioral_impact' => true,
            'commitment_weight' => true,
            'decision_rationale' => true,
            'durable_facts' => true,
            'explicit_instruction' => true,
        ];
    }

    private function formatEnabledCriteria(): string
    {
        $labels = [
            'behavioral_impact' => 'Behavioral Impact',
            'commitment_weight' => 'Commitment Weight',
            'decision_rationale' => 'Decision Rationale',
            'durable_facts' => 'Durable Facts',
            'explicit_instruction' => 'Explicit Instruction',
        ];

        $enabled = array_filter($this->enabledCriteria);

        return implode(', ', array_map(
            fn (string $key): string => $labels[$key] ?? $key,
            array_keys($enabled)
        ));
    }
}
