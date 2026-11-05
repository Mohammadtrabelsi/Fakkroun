# User story 16: Smart ads in the game

> **Status:** future work, not started · **Effort:** large (delivered in slices, see §7)
> **Builds on:** the advertising system on `develop` (wallets, ledger, campaigns, confirmed impressions, sponsored questions, reconciliation)
> **Depends on:** [`03`](03-next-step-run-ci.md) (CI green); [`04`](04-next-step-realtime-reverb.md) helps but isn't required

## 1. Epic

**As the platform**, we want ads that feel like part of learning, not an interruption. Each ad should be relevant to what the player has just learned, shown at a calm moment, never too often, and safe for young players. Advertisers (NGOs, schools, universities, associations, companies) should reach the right audience and see honest, confirmed results.

"Smart" here means **contextual, well-timed, capped, measured and safe**. It does **not** mean tracking or profiling individual players.

### What exists today (starting point)

- One placement: a banner under the answer reveal (`AdService::offer`, placement `reveal`).
- Targeting by category and language only. The campaign is picked at random among eligible ones.
- Frequency rules (every N questions, a cap per player per game, minimum seconds between ads) set by admins in **Game settings**.
- Billing only after the browser confirms the ad was on screen for at least 1 second, once per delivery. Sponsored questions are billed once per room.
- No click tracking, no creative rotation by performance, no room-level ad controls.

## 2. Personas

| Persona | Need |
| --- | --- |
| **Player** (teen or adult) | Ads that don't break concentration or slow the game, and are sometimes useful ("learn more about this topic"). |
| **Teacher / host** | Control over ads in a classroom room, including turning them off. |
| **Advertiser** (NGO, school, company) | Reach players interested in their cause, in their language, and see what actually worked. |
| **Admin** | Keep ads relevant, safe and honest, protect players' privacy, and keep billing exact. |

## 3. User stories and acceptance criteria

### S1. Contextual match to the question just answered
**As a player**, after the answer to a question about the ocean is revealed, I want any ad I see to be about that topic, so it adds to what I just learned.

- *Given* a running campaign targets the question's category (or subcategory) and language, *when* an ad is offered at the reveal, *then* that campaign is preferred over campaigns with no targeting.
- *Given* advertisers set topic keywords on a creative, *when* a keyword matches the question's category, subcategory or text, *then* the creative scores higher. The score is computed on the server and the rule is documented.
- *Given* no relevant campaign exists, *then* no ad is shown, rather than an unrelated one. Admins can relax this with a setting (`advertising.allow_untargeted`).

### S2. "Learn more" moments
**As a player**, I want an optional "Learn more" card from a relevant organisation next to the explanation, so I can follow up after the game.

- The card appears only in the reveal panel, never while the question is open.
- Links open in a new tab, after the game or on request, and never interrupt answering.
- Every link is `https://` and was reviewed by an admin (as creatives are today).

### S3. Calm placements only
**As a player**, I never want an ad while I'm answering.

- Allowed placements: `reveal` (exists), `post_game` (results page, below "What we learned"), and `lobby` (waiting room, host-controlled).
- **Never** during the question phase, never covering the countdown or answer buttons, no sound, no autoplay video, no pop-ups.
- Each placement has its own frequency rules in **Game settings**. A placement can be turned off.

### S4. Fatigue control
**As a player**, I don't want to see the same ad again and again.

- Don't show the same creative to the same player more than `advertising.max_same_creative_per_day` times a day.
- Rotate among a campaign's approved creatives.
- Existing caps still apply: per game, every N questions, minimum seconds between ads.

### S5. Performance-aware rotation
**As an advertiser**, I want my best creatives shown more often.

- Track **clicks** on "Learn more" through a server redirect: `/ads/click/{token}`, recorded once per delivery and bound to the same player as the delivery token.
- Rotation favours creatives with a higher confirmed click-through rate while still showing new creatives (explore/exploit with a minimum share for each). The method and its parameters are admin settings and are documented.
- Clicks are **not billed** in this story. Pricing per click, if ever wanted, is a separate business decision (§6).

### S6. Room-level controls for hosts and schools
**As a teacher**, I want to run an ad-free room for my class.

- New room setting `ads`: `standard` (default), `sponsored_only` (sponsored questions but no banners), or `off`.
- Admins choose who may set `off`: everyone, verified schools only, or nobody (`advertising.ad_free_rooms` setting).
- Sponsored questions still respect `include_sponsored`.

### S7. Safe for young players
**As an admin**, I want ads to stay appropriate for every age.

- Targeting is **contextual only**: room category, question topic and language. No behavioural profiles and no tracking across games or sites.
- Advertisers never receive personal data. The dashboard shows only aggregates, and breakdowns under `advertising.min_report_group` players are merged into "Other" so no individual can be identified.
- Creatives keep the admin review step. Admins can block categories of advertisers or topics platform-wide.

### S8. AI-assisted review (assist, never approve)
**As an admin**, I want help reviewing creatives quickly.

- When a creative is submitted, the existing AI driver checks it against the platform rules (relevance to the targeted categories, no misleading claims, a suitable tone for young audiences) and adds **notes** to the review screen.
- The AI never approves or rejects. A person always decides. Without `ANTHROPIC_API_KEY` the feature is simply hidden.

### S9. Honest reporting for advertisers
**As an advertiser**, I want to see what actually happened.

- The dashboard adds: offered vs confirmed impressions (kept apart), clicks and click-through rate per creative, results by placement, and reach (distinct players, aggregated).
- Every figure comes from recorded events. Nothing is estimated or projected.

### S10. Billing stays exact
**As an admin**, I want new placements to follow the same billing rules.

- New placements reuse the delivery-token, confirmation and `billing_reference` pattern. Reconciliation covers them too.
- Tests prove: one charge per confirmed display; no charge for unconfirmed, expired or other-player tokens; no negative balance; a refresh or reconnect never charges twice.

## 4. Non-functional requirements

- **Performance:** picking an ad adds no more than one indexed query per offer. Creative scores are cached per campaign and refreshed by a scheduled job.
- **Accessibility:** ads are labelled "Sponsored" in all three languages, have alt text, and are reachable and dismissible by keyboard. They never take focus away from the answers.
- **RTL:** ad layouts mirror correctly in Arabic.
- **Privacy:** no third-party ad scripts and no external trackers. Everything is served from Fakkroun.

## 5. Data model sketch (to confirm during design)

| Change | Purpose |
| --- | --- |
| `advertising_creatives.keywords` (JSON) | Topic match for S1 |
| `ad_deliveries.placement` values `post_game`, `lobby` | S3 |
| `ad_clicks` (delivery_id unique, clicked_at) | S5, S9 |
| `creative_scores` (creative_id, impressions, clicks, score, refreshed_at) | S5 rotation cache |
| `rooms.settings.ads` | S6 |
| New settings: `allow_untargeted`, `max_same_creative_per_day`, `ad_free_rooms`, `min_report_group`, per-placement caps | S1, S4, S6, S7 |

## 6. Questions for the owner

1. Should clicks ever be billed (pay per click), or stay a reporting metric only?
2. Who may create ad-free rooms: everyone, verified schools only, or nobody?
3. Should the lobby show ads at all, given it's where hosts present the room to a class?
4. Is there a list of advertiser categories that are never accepted (for example gambling or alcohol)?
5. Is a minimum player age required for any placement?

## 7. Delivery slices

1. **Relevance:** S1 + S4 (contextual scoring and fatigue caps on the existing `reveal` placement).
2. **Placements:** S3 (`post_game`, then `lobby`) + S6 (room ad controls).
3. **Learning moments:** S2 + click tracking from S5 + S9 reporting.
4. **Optimisation:** S5 rotation by performance.
5. **Assistance:** S8 AI review notes.
6. **Throughout:** S7 and S10 are acceptance criteria for every slice, not a separate slice.

## 8. Out of scope

- Third-party ad networks, programmatic bidding, retargeting, or any cross-site tracking.
- Video or audio ads.
- Online payment for advertisers (purchases stay admin-recorded until a gateway is chosen).
