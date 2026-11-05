<?php

namespace Tests\Feature\Questions;

use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\ModerationService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Notifications\QuestionReviewed;
use App\Domain\Questions\SubmissionService;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

class CommunityFlowTest extends QuestionBankTestCase
{
    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = $this->userWithRole(UserRole::Player);
    }

    private function fillForm($component)
    {
        return $component
            ->set('categoryId', (string) $this->category->id)
            ->set('difficulty', 'easy')
            ->set('body', 'Which archipelago off Sfax is known for charfia fishing traps?')
            ->set('choices', ['Kerkennah', 'Djerba', 'Zembra', 'Galite'])
            ->set('correct', '0')
            ->set('referenceUrl', 'https://example.org/charfia');
    }

    public function test_community_page_renders_with_the_design(): void
    {
        $this->actingAs($this->player)->get(route('questions.submit'))
            ->assertOk()
            ->assertSee(__('questions.community.title'))
            ->assertSee(__('questions.community.next_title'))
            ->assertSee(__('questions.community.yours'));
    }

    public function test_a_draft_can_be_saved_reopened_and_sent(): void
    {
        $this->fillForm(Livewire::actingAs($this->player)->test('pages::questions.submit'))->call('saveDraft')->assertHasNoErrors();

        $question = Question::query()->firstOrFail();
        $this->assertSame(QuestionStatus::Draft, $question->status);

        Livewire::withQueryParams(['question' => $question->uuid])->actingAs($this->player)
            ->test('pages::questions.submit')
            ->assertSet('body', 'Which archipelago off Sfax is known for charfia fishing traps?')
            ->set('explanation', 'Palm-frond fences guide fish into traps.')
            ->call('submit')
            ->assertHasNoErrors();

        $question->refresh();
        $this->assertSame(QuestionStatus::Pending, $question->status);
        $this->assertSame('Palm-frond fences guide fish into traps.', $question->originTranslation()->explanation);
        $this->assertSame(1, Question::count());
    }

    public function test_moderators_request_changes_and_authors_resubmit(): void
    {
        Notification::fake();
        $question = app(SubmissionService::class)->submit($this->player, $this->content(['reference_url' => 'https://example.org/src']));

        app(ModerationService::class)->requestChanges($question, $this->admin, 'Please cite the 2023 report.');

        $question->refresh();
        $this->assertSame(QuestionStatus::ChangesRequested, $question->status);
        Notification::assertSentTo($this->player, QuestionReviewed::class, fn ($n) => $n->outcome === 'changes_requested');

        $this->actingAs($this->player)->get(route('questions.submit'))->assertSee(__('questions.status.changes_requested'));

        Livewire::withQueryParams(['question' => $question->uuid])->actingAs($this->player)
            ->test('pages::questions.submit')
            ->assertSet('notice', 'Please cite the 2023 report.')
            ->set('referenceUrl', 'https://example.org/report-2023')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(QuestionStatus::Pending, $question->fresh()->status);
    }

    public function test_request_changes_needs_a_note(): void
    {
        $question = app(SubmissionService::class)->submit($this->player, $this->content());

        $this->expectException(\DomainException::class);
        app(ModerationService::class)->requestChanges($question, $this->admin, ' ');
    }

    public function test_community_rules_short_question_and_required_source(): void
    {
        try {
            app(SubmissionService::class)->submit($this->player, $this->content(['body' => str_repeat('Long question ', 15).'?']));
            $this->fail('Accepted a question over 160 characters.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body', $e->errors());
        }

        try {
            app(SubmissionService::class)->submit($this->player, $this->content(['reference_url' => null]));
            $this->fail('Accepted a question without a source.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reference_url', $e->errors());
        }
    }

    public function test_players_cannot_edit_someone_elses_or_a_published_question(): void
    {
        $other = $this->userWithRole(UserRole::Player);
        $question = app(SubmissionService::class)->saveDraft($this->player, $this->content());

        try {
            app(SubmissionService::class)->saveDraft($other, $this->content(), $question);
            $this->fail('Edited another player’s draft.');
        } catch (AuthorizationException) {
        }

        $published = Question::factory()->published()->create(['creator_id' => $this->player->id, 'source' => 'community', 'category_id' => $this->category->id]);
        $this->expectException(\DomainException::class);
        app(SubmissionService::class)->saveDraft($this->player, $this->content(['body' => 'A different question body here?']), $published);
    }
}
