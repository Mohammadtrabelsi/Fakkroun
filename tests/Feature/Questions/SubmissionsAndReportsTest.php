<?php

namespace Tests\Feature\Questions;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\ReportReason;
use App\Domain\Questions\Enums\ReportStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\ModerationService;
use App\Domain\Questions\Notifications\QuestionReviewed;
use App\Domain\Questions\ReportService;
use App\Domain\Questions\SubmissionService;
use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

class SubmissionsAndReportsTest extends QuestionBankTestCase
{
    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = $this->userWithRole(UserRole::Player);
    }

    public function test_a_player_submission_enters_the_moderation_queue(): void
    {
        $question = app(SubmissionService::class)->submit($this->player, $this->content());

        $this->assertSame(QuestionStatus::Pending, $question->status);
        $this->assertSame(QuestionSource::Community, $question->source);
        $this->assertSame($this->player->id, $question->creator_id);
        $this->assertSame([], $question->poolEntries()->pluck('language')->all());
    }

    public function test_submission_form_works_and_lists_the_question(): void
    {
        Livewire::actingAs($this->player)
            ->test('pages::questions.submit')
            ->set('categoryId', (string) $this->category->id)
            ->set('difficulty', 'easy')
            ->set('body', 'How many legs does a spider have?')
            ->set('choices', ['Six', 'Eight', 'Ten', 'Twelve'])
            ->set('correct', '1')
            ->set('referenceUrl', 'https://en.wikipedia.org/wiki/Spider')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('questions.submit'));

        $this->actingAs($this->player)->get(route('questions.mine'))->assertOk()->assertSee('How many legs does a spider have?');
    }

    public function test_daily_limit_is_enforced(): void
    {
        app(PlatformSettings::class)->update(['submissions' => ['daily_limit_per_player' => 1]]);
        app(SubmissionService::class)->submit($this->player, $this->content());

        $this->expectException(DomainActionException::class);
        app(SubmissionService::class)->submit($this->player, $this->content(['body' => 'Which is the deepest ocean trench?', 'choices' => ['Mariana', 'Tonga', 'Java', 'Puerto Rico'], 'correct' => 0]));
    }

    public function test_categories_closed_to_the_community_refuse_submissions(): void
    {
        $this->category->update(['allow_community_submissions' => false]);

        $this->expectException(ValidationException::class);
        app(SubmissionService::class)->submit($this->player, $this->content());
    }

    public function test_users_without_the_permission_cannot_submit(): void
    {
        $this->actingAs(User::factory()->create())->get(route('questions.submit'))->assertForbidden();
    }

    public function test_auto_publish_is_off_by_default_and_works_when_enabled(): void
    {
        app(PlatformSettings::class)->update(['submissions' => ['auto_publish' => true]]);

        $question = app(SubmissionService::class)->submit($this->player, $this->content());

        $this->assertSame(QuestionStatus::Published, $question->status);
        $this->assertDatabaseHas('question_moderation_logs', ['question_id' => $question->id, 'action' => 'auto_published', 'actor_id' => null]);
    }

    public function test_author_is_notified_of_the_decision(): void
    {
        Notification::fake();
        $question = app(SubmissionService::class)->submit($this->player, $this->content());

        app(ModerationService::class)->reject($question, $this->admin, 'Please add a source');

        Notification::assertSentTo($this->player, QuestionReviewed::class, fn (QuestionReviewed $n) => $n->outcome === 'rejected' && $n->reason === 'Please add a source');
    }

    public function test_rejected_submissions_can_be_withdrawn_but_published_ones_cannot(): void
    {
        $service = app(SubmissionService::class);
        $question = $service->submit($this->player, $this->content());

        $service->withdraw($this->player, $question);
        $this->assertSoftDeleted($question);

        $published = Question::factory()->published()->create(['creator_id' => $this->player->id, 'source' => 'community', 'category_id' => $this->category->id]);
        $this->expectException(DomainActionException::class);
        $service->withdraw($this->player, $published);
    }

    public function test_submission_api(): void
    {
        $this->actingAs($this->player)->postJson('/api/questions/submissions', $this->content())
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->actingAs($this->player)->getJson('/api/questions/submissions')->assertOk()->assertJsonPath('total', 1);

        $this->actingAs($this->player)->postJson('/api/questions/submissions', $this->content())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_players_can_report_a_question_once_and_moderators_resolve_it(): void
    {
        $question = Question::factory()->published()->create(['category_id' => $this->category->id]);

        $this->actingAs($this->player)->postJson("/api/questions/{$question->uuid}/reports", ['reason' => 'wrong_answer', 'details' => 'It changed in 2024'])
            ->assertCreated();
        $this->actingAs($this->player)->postJson("/api/questions/{$question->uuid}/reports", ['reason' => 'wrong_answer'])
            ->assertUnprocessable();

        $moderator = $this->userWithRole(UserRole::Moderator);
        $moderator->moderatedCategories()->attach($this->category);
        $report = $question->reports()->first();

        app(ReportService::class)->resolve($report, $moderator, ReportStatus::Resolved, 'Fixed');

        $this->assertSame(ReportStatus::Resolved, $report->fresh()->status);
        $this->assertSame(0, app(ReportService::class)->open(null)->total());
    }

    public function test_reports_are_rate_limited(): void
    {
        config(['fakkroun.questions.reports_per_hour' => 1]);
        $service = app(ReportService::class);
        $service->report($this->player, Question::factory()->published()->create(['category_id' => $this->category->id]), ReportReason::Unclear);

        $this->expectException(DomainActionException::class);
        $service->report($this->player, Question::factory()->published()->create(['category_id' => $this->category->id]), ReportReason::Unclear);
    }
}
