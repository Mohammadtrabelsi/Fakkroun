# Next step 09: Advertising, credits and billing (Phase 5)

> **Priority:** after gameplay is stable · **Effort:** large · **Spec:** §17–§21
> **Depends on:** 03, 04 (display acknowledgements ride on the real-time channel)
>
> **Update:** implemented in `claude/fakkroun-next-steps` (wallets, ledger, campaigns, confirmed impressions, sponsored questions, reconciliation, dashboards). Impressions are confirmed through Livewire, not Reverb. Not yet executed; see [`14`](14-final-report.md).

## Non-negotiables (spec §18)

- **Two independent consumption units:** impressions (per player, per question, *confirmed displayed*) and sponsored-question deliveries (once per room delivery). Separate wallets, never mixed.
- A **server broadcast is not an impression.** An impression exists only after the client confirms display with a server-issued, single-use delivery token.
- An **immutable ledger** with a unique `billing_reference` per billable event. Corrections are new reversal entries, never edits.
- **No negative balances, no double billing**, including under refresh, reconnect, retries and parallel workers.
- **Advertisers never influence answers, scoring or rankings.** Sponsored questions go through normal moderation and scoring.

## Design (assessment §5.6, §8.2)

- Tables: `advertisers`, `advertising_packages`, `advertiser_wallets` (unique advertiser × credit type, unsigned balance), `advertising_ledger_entries` (append-only, the model refuses updates and deletes), `advertising_campaigns`, `advertising_creatives`, `ad_deliveries`, `advertising_impressions` (unique `delivery_id`, unique `billing_reference`), `sponsored_question_deliveries` (unique `game_question_id`), `campaign_daily_stats`.
- `AdSelector` runs when a question opens: eligible creative by category, language, schedule and frequency caps (per player per round, per question, minimum interval, per session, sponsored questions per round). Never on every question by default.
- `ImpressionService::confirm(token)`: verify → in one transaction, insert the impression, run a conditional wallet decrement (`UPDATE … SET balance = balance - 1 WHERE … AND balance >= 1`), and insert the ledger entry. Zero affected rows marks the campaign *exhausted*.
- Sponsored questions are `questions.is_sponsored = true` + `campaign_id`, selected through the pool's `is_sponsored` column within the per-round limit, and labelled in the UI.
- `ReconciliationService` (scheduled) checks impressions and deliveries against ledger rows by `billing_reference`, and wallet balances against ledger sums, then reports differences to admins.
- Advertiser dashboard: campaigns, balances, consumption, impressions and deliveries by day, category and language, and billing history, with filters.
- Optional AI checks (spec §21) flag misleading claims and mismatched languages for human review. They never approve on their own.

## Tests (spec §29)

Campaign creation, credit purchase, impression recording, sponsored recording, separate balances, duplicate billing prevention (the same token twice, two workers), budget exhaustion mid-game, ledger reconciliation. Concurrency tests run in the MySQL CI job from [`03`](03-next-step-run-ci.md).
