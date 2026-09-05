# NLP Phase 0 Baseline

Phase 0 objective: freeze current behavior, capture known failures, and define measurable success criteria before refactoring.

## Baseline Branch

- Local baseline branch: `baseline/nlp-phase0`
- Use this branch pointer as the "before" reference while implementing later phases.

## Baseline Test Inputs

- Questions file: `tests/nlp-phase0-baseline-questions.json`
- Intent expectations: `tests/nlp-phase0-baseline-expected-intents.json`

These are designed to capture:
- current stock/inventory scope failures
- representative order/product/customer/date paths
- unsupported/ambiguous queries that should not silently guess

## Run Commands

From `tests/`:

```bash
npm run test:nlp:phase0
```

or:

```bash
npm run test:nlp:phase0:headless
```

If needed, force URL explicitly:

```bash
PLUGIN_URL="http://localhost:8080/wp-admin/admin.php?page=unmai-analytix-for-woocommerce" npm run test:nlp:phase0
```

## What to Capture During Baseline

For each run, record:

1. Intent check failures
2. Execution mismatches (wrong route/tool for intent)
3. Scope contradictions:
   - answer says "all" but returned rows are partial
4. Empty/unsupported handling quality

## Phase 0 Metrics (Baseline Targets)

Track these in every run:

- **Intent Match Rate**  
  `% of baseline questions where intent matches expected partial intent`

- **Execution Route Accuracy**  
  `% of questions where returned tool behavior matches expected entity/operation/scope`

- **Contradiction Rate**  
  `% of responses with semantic contradiction (example: "all products" with one row when total > 1)`

- **Clarification Rate**  
  `% of ambiguous prompts where system asks clarification instead of guessing`

- **Regression Count**  
  `# of previously-good baseline questions that fail after a change`

## Baseline Run Log Template

Use this table in PR descriptions:

| Run Date | Commit | Intent Match | Route Accuracy | Contradiction Rate | Clarification Rate | Regressions |
|---|---|---:|---:|---:|---:|---:|
| YYYY-MM-DD | `<sha>` | 0/0 | 0/0 | 0/0 | 0/0 | 0 |

## Phase 0 Exit Criteria

- Baseline files are committed and reproducible.
- Team can run the same baseline set locally and compare metrics.
- Known failures are explicitly listed and not hidden as flaky behavior.
