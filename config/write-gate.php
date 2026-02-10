<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Write Gate Criteria
    |--------------------------------------------------------------------------
    |
    | Each criterion can be enabled or disabled. An entry must match at least
    | one enabled criterion to pass the write gate. Use --force to bypass.
    |
    | These defaults can be overridden per-project via ~/.knowledge/config.json:
    |   { "write_gate": { "criteria": { "behavioral_impact": false } } }
    |
    */
    'criteria' => [
        'behavioral_impact' => (bool) env('WRITE_GATE_BEHAVIORAL_IMPACT', true),
        'commitment_weight' => (bool) env('WRITE_GATE_COMMITMENT_WEIGHT', true),
        'decision_rationale' => (bool) env('WRITE_GATE_DECISION_RATIONALE', true),
        'durable_facts' => (bool) env('WRITE_GATE_DURABLE_FACTS', true),
        'explicit_instruction' => (bool) env('WRITE_GATE_EXPLICIT_INSTRUCTION', true),
    ],
];
