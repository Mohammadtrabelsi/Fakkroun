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
- Clicks are **never billed**. Advertisers pay per pack (S11); clicks are a reporting and rotation signal only.

### S6. Free and paid player accounts (ad-free)
**As a player**, I can keep a free account that shows ads, or pay for an ad-free account.

- New account plan on `users`: `free` (default) or `paid`, with `plan_expires_at`. Payment for player plans needs a gateway, which is still to be chosen (assessment D7).
- A **paid** player is never offered a banner (`reveal`, `post_game`, `lobby`), and no impression is ever billed for them.
- Rooms (**decided, built**): when the **host** has a paid account, the room has no banners and no sponsored questions or rounds, so a whole class plays ad-free. In a free host's room, a paid guest sees no banners, but sponsored questions and rounds stay (they are shared game content), always labelled "Sponsored".
- **Built:** `users.plan` / `plan_expires_at`, `User::hasPaidPlan()`, the abstract `PaymentProvider` with a cash driver, **Admin → Paid accounts** to record cash payments, and `php artisan plans:grant {email} {receipt} --months=N`. Still to build: a player-facing "Go ad-free" page.
- The Terms page already describes both plans (`/terms#accounts`); the paid plan is marked "coming soon".

### S7. Safe for young players
**As an admin**, I want ads to stay appropriate for every age.

- Targeting is **contextual only**: room category, question topic and language. No behavioural profiles and no tracking across games or sites.
- Advertisers never receive personal data. The dashboard shows only aggregates, and breakdowns under `advertising.min_report_group` players are merged into "Other" so no individual can be identified.
- Creatives keep the admin review step. Admins can block categories of advertisers or topics platform-wide.
- Players can be as young as **8** (general-culture content), so **every** ad must be suitable for children. There is no age-gated placement.

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

### S11. Packs and invoices (pay per pack, never per click)
**As an advertiser**, I buy a pack and receive an invoice; I pay for what the pack contains, not for clicks.

- Three packs, created by admins in **Admin → Advertising → Packages**: **Essential** (associations, schools, small organisations), **Professional** (companies, larger campaigns), **Enterprise** (national campaigns, custom needs). Contents and prices are admin data: `[PACK DETAILS]` until you set them.
- A pack holds a number of confirmed ad displays plus sponsored questions and/or sponsored rounds (separate balances, as today).
- **Invoices:** every recorded purchase produces a numbered PDF invoice (`spatie/laravel-pdf` is already installed). Numbers are sequential and never reused; an invoice is never edited, only cancelled by a credit note.
- **Usage statement:** a monthly PDF per advertiser counting the confirmed displays, sponsored questions and rounds used, from the ledger.
- Admin can download any invoice; an advertiser only their own.

### S12. Sponsored rounds
**As an advertiser**, I want a themed round of questions presented by my organisation.

- Depends on rounds ([`07`](07-next-step-gameplay-depth.md) §4). A sponsored round is a set of reviewed questions on one theme, introduced by "This round is presented by …".
- Billed **once per room** that plays it, from a new `sponsored_round` credit balance, with billing reference `sponsored-round:<game id>:<round>`.
- At most one sponsored round per game; never in a paid host's room (S6).

### S13. Advertiser policy (halal, respectful, kids-friendly)
**As an admin**, I accept only advertisers that follow the published policy.

- The policy is part of the general Terms of use: the chart at `/terms#advertising` (halal; respect for every religion; humanity and dignity; kids-friendly; honesty).
- The advertiser dashboard and the admin review screen link to it.
- Approving a campaign requires the reviewer to tick "Checked against the advertising policy"; the tick is stored with the review (`reviewed_by`, `reviewed_at`).
- A campaign found in breach is stopped at once. **Built:** three steps (warning, call, suspension) with frozen credits during suspension; see §6.

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
| `users.plan`, `users.plan_expires_at` | S6 |
| `advertising_invoices` (number unique, advertiser, purchase entry, pdf path, issued_at, cancelled_by) | S11 |
| `sponsored_round` credit type and deliveries | S12 |
| `advertising_campaigns.policy_checked` | S13 |
| New settings: `allow_untargeted`, `max_same_creative_per_day`, `min_report_group`, per-placement caps | S1, S4, S7 |

## 6. Decisions (owner, 2026-11-05)

| Question | Decision |
| --- | --- |
| Pay per click? | **No.** Advertisers pay **per pack** (Essential, Professional, Enterprise). Confirmed ads are counted and invoiced (S11). |
| Ad-free rooms | Through **paid player accounts**: free accounts see ads, paid accounts don't (S6). |
| Formats | Banners at calm moments, **sponsored questions** and **sponsored rounds** (S3, S12). |
| Accepted advertisers | Only **halal** advertisers who respect every religion and human dignity and are **kids-friendly**. Published as a chart in the Terms of use (S13). |
| Minimum age | No age-gated placement. Players from **8** years; every ad must suit children. |

Decided on 2026-11-05 (second round):

| Question | Decision | Built |
| --- | --- | --- |
| Policy breaches and unused credits | **1st** breach: written warning. **2nd**: our team calls the advertiser. **3rd**: the advertiser account is suspended. Each breach stops the campaign; credits are kept but **frozen** while suspended. | `PolicyEnforcementService`, Admin → Advertising (breach button, calls to make, reactivate), advertiser notices, Terms |
| Paid host | **Yes:** a room hosted by a paid account is ad-free for everyone (no banners, no sponsored questions or rounds). | `AdService::offer`, `GameEngine::start` |
| Payment provider | **Abstract interface; cash only for now**, other methods later. | `PaymentProvider` interface, `CashPaymentProvider`, `PaymentService`, **Admin → Paid accounts**, `plans:grant` command |
| Who buys packs | Packs (Essential, Professional, Enterprise) are for **advertiser accounts only**. Player/user accounts are free (with ads) or paid (ad-free). | Terms wording; packs stay in Admin → Advertising |
| Paid-account price | **A platform setting**, not code or env. | Game settings → Accounts (`accounts.paid_plan_price_minor`, `accounts.paid_plan_currency`) |

Still open:

1. Pack contents and prices (`[PACK DETAILS]`); set the paid-account price in Game settings (0 means not on sale).
2. Which non-cash payment method to add later, behind `PaymentProvider`.
3. Legal review of the Terms of use before launch, planned. This is the first version; the page shows a draft notice until `FAKKROUN_TERMS_DRAFT=false`.

## 7. Delivery slices

1. **Relevance:** S1 + S4 (contextual scoring and fatigue caps on the existing `reveal` placement). **Built**, together with the S13 policy tick: see §9.
2. **Placements and plans:** S3 (`post_game`, then `lobby`) + S6 (free/paid accounts).
3. **Packs and invoices:** S11 + S13 (policy tick on approval).
4. **Learning moments:** S2 + click tracking from S5 + S9 reporting.
5. **Sponsored rounds:** S12 (after rounds in [`07`](07-next-step-gameplay-depth.md)).
6. **Optimisation:** S5 rotation by performance.
7. **Assistance:** S8 AI review notes.
8. **Throughout:** S7 and S10 are acceptance criteria for every slice, not a separate slice.

## 8. Out of scope

- Third-party ad networks, programmatic bidding, retargeting, or any cross-site tracking.
- Video or audio ads.
- Online payment for advertisers (purchases stay admin-recorded, with invoices, until a gateway is chosen).

## 9. Built so far

| Slice | What exists | Where |
| --- | --- | --- |
| 1 (S1) | Relevance score per creative: +4 campaign targets the question's category or subcategory, +1 targets the room language, +1 creative written in that language, +2 per keyword found in the question or its topic names (at most 3). Highest score wins; untargeted ads (score 0) run only while `advertising.allow_untargeted` is on. Arabic diacritics are ignored when matching. | `AdRelevance`, `AdService::pick`, creative keywords on the advertiser dashboard |
| 1 (S4) | `advertising.max_same_creative_per_day` (default 3); ties go to the creative the player has seen least today, so a campaign's creatives rotate. | `AdService::pick`, Game settings |
| S13 | Approval needs the "Checked against the advertising policy" tick; who and when are stored. | `CampaignService::approve`, Admin → Advertising |
| S6, S13 enforcement | Free/paid accounts, ad-free rooms for paid hosts, cash payments, three-strike policy. | See §6 |

Next slices, in order: 2 (post-game and lobby placements), 3 (invoices), 4 ("Learn more" and click tracking), 5 (sponsored rounds), 6 (rotation by performance), 7 (AI review notes).
