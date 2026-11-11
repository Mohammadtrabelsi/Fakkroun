<?php

namespace Tests\Feature\Questions;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\ModerationService;
use App\Domain\Questions\QuestionPool;
use App\Domain\Questions\QuestionService;
use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

class QuestionLifecycleTest extends QuestionBankTestCase
{
    private function create(array $overrides = [], array $translations = []): Question
    {
        return app(QuestionService::class)->create($this->content($overrides), $this->admin, QuestionSource::Admin, QuestionStatus::Pending, $translations);
    }

    public function test_a_question_has_four_stable_choices_and_the_correct_one_is_an_id(): void
    {
        $question = $this->create();

        $this->assertCount(4, $question->choices);
        $this->assertSame($question->choices[2]->id, $question->correct_choice_id);
        $origin = $question->originTranslation();
        $this->assertSame('Pacific', $origin->choiceText($question->correct_choice_id));
        $this->assertSame(TranslationStatus::Pending, $origin->status);
        $this->assertSame(1, $question->revisions()->count());
    }

    public function test_structural_rules_are_enforced(): void
    {
        foreach ([
            ['choices' => ['A', 'B', 'C']],
            ['choices' => ['Same', 'same', 'C', 'D']],
            ['correct' => 4],
            ['body' => 'Short'],
            ['difficulty' => 'extreme'],
            ['language' => 'de'],
            ['reference_url' => 'not-a-url'],
        ] as $invalid) {
            try {
                $this->create($invalid);
                $this->fail('Expected a validation error for '.json_encode($invalid));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_subcategory_must_belong_to_the_category(): void
    {
        $other = Category::factory()->create();
        $foreign = Category::factory()->create(['parent_id' => $other->id]);

        $this->expectException(ValidationException::class);
        $this->create(['subcategory_id' => $foreign->id]);
    }

    public function test_exact_duplicates_are_rejected_even_with_different_case_and_order(): void
    {
        $this->create();

        $this->expectException(ValidationException::class);
        $this->create(['body' => 'which OCEAN is the largest on earth', 'choices' => ['Arctic', 'Pacific', 'Indian', 'Atlantic'], 'correct' => 1]);
    }

    public function test_similar_questions_are_flagged_for_the_moderator(): void
    {
        $first = $this->create();
        $report = app(QuestionService::class)->inspect($this->content(['body' => 'Which is the largest ocean on Earth?', 'choices' => ['A1', 'B1', 'C1', 'D1']]));

        $this->assertArrayHasKey($first->id, $report['similar']);
    }

    public function test_answer_in_question_is_a_warning_not_an_error(): void
    {
        $report = app(QuestionService::class)->inspect($this->content(['body' => 'Is the Pacific the largest ocean of all?']));

        $this->assertContains(__('questions.warnings.answer_in_question'), $report['warnings']);
    }

    public function test_approve_and_publish_makes_the_question_playable_in_its_language(): void
    {
        $question = $this->create();

        app(ModerationService::class)->approve($question, $this->admin, publish: true);

        $question->refresh();
        $this->assertSame(QuestionStatus::Published, $question->status);
        $this->assertSame($this->admin->id, $question->reviewed_by);
        $this->assertSame(['en'], app(QuestionPool::class)->languages($question));
    }

    public function test_translations_are_approved_independently(): void
    {
        $question = $this->create([], [
            'fr' => ['body' => 'Quel est le plus grand océan de la Terre ?', 'choices' => ['Atlantique', 'Indien', 'Pacifique', 'Arctique']],
            'ar' => ['body' => 'ما أكبر محيط على الأرض؟', 'choices' => ['الأطلسي', 'الهندي', 'الهادئ', 'المتجمد']],
        ]);
        $moderation = app(ModerationService::class);
        $moderation->approve($question, $this->admin, publish: true);

        $this->assertSame(['en'], app(QuestionPool::class)->languages($question));

        $moderation->approveTranslation($question->translation('fr'), $this->admin);
        $this->assertSame(['en', 'fr'], app(QuestionPool::class)->languages($question));

        $moderation->rejectTranslation($question->translation('ar'), $this->admin, 'Wrong term');
        $this->assertSame(['en', 'fr'], app(QuestionPool::class)->languages($question));
        $this->assertSame(TranslationStatus::Rejected, $question->fresh()->translation('ar')->status);
    }

    public function test_editing_an_approved_question_sends_it_back_for_review(): void
    {
        $question = $this->create([], ['fr' => ['body' => 'Quel est le plus grand océan de la Terre ?', 'choices' => ['Atlantique', 'Indien', 'Pacifique', 'Arctique']]]);
        $moderation = app(ModerationService::class);
        $moderation->approve($question, $this->admin, publish: true);
        $moderation->approveTranslation($question->translation('fr'), $this->admin);

        $edited = app(QuestionService::class)->updateContent($question, $this->content(['body' => 'Which ocean covers the largest area on Earth?']), $this->admin, 'Clearer wording');

        $this->assertSame(QuestionStatus::Pending, $edited->status);
        $this->assertSame(2, $edited->version);
        $this->assertSame([], app(QuestionPool::class)->languages($edited));
        $this->assertSame(TranslationStatus::Pending, $edited->translation('fr')->status);
        $this->assertSame(2, $edited->revisions()->count());
        $this->assertSame('Which ocean is the largest on Earth?', $edited->revisions()->where('version', 1)->first()->snapshot['translations']['en']['body']);
    }

    public function test_saving_unchanged_content_changes_nothing(): void
    {
        $question = $this->create();
        app(ModerationService::class)->approve($question, $this->admin, publish: true);

        $same = app(QuestionService::class)->updateContent($question->fresh(), $this->content(), $this->admin);

        $this->assertSame(QuestionStatus::Published, $same->status);
        $this->assertSame(1, $same->version);
    }

    public function test_admin_policy_categories_need_an_administrator_to_approve(): void
    {
        $category = Category::factory()->create(['validation_policy' => 'admin']);
        $moderator = $this->userWithRole(UserRole::Moderator);
        $moderator->moderatedCategories()->attach($category);
        $question = $this->create(['category_id' => $category->id]);

        $moderation = app(ModerationService::class);
        $this->assertTrue($moderation->canModerate($moderator, $question));
        $this->assertFalse($moderation->canApprove($moderator, $question));

        try {
            $moderation->approve($question, $moderator);
            $this->fail('Moderator approved an admin-policy question.');
        } catch (AuthorizationException) {
        }

        // …but may still reject it.
        $moderation->reject($question, $moderator, 'Source missing');
        $this->assertSame(QuestionStatus::Rejected, $question->fresh()->status);
    }

    public function test_moderators_cannot_touch_categories_outside_their_scope(): void
    {
        $moderator = $this->userWithRole(UserRole::Moderator);
        $question = $this->create();

        $this->expectException(AuthorizationException::class);
        app(ModerationService::class)->startReview($question, $moderator);
    }

    public function test_assigned_moderator_approves_in_moderator_policy_categories(): void
    {
        $moderator = $this->userWithRole(UserRole::Moderator);
        $moderator->moderatedCategories()->attach($this->category);
        $question = $this->create();

        app(ModerationService::class)->startReview($question, $moderator);
        app(ModerationService::class)->approve($question, $moderator);

        $this->assertSame(QuestionStatus::Approved, $question->fresh()->status);
    }

    public function test_reference_is_required_before_approval_where_the_category_says_so(): void
    {
        $this->category->update(['requires_reference' => true]);
        $question = $this->create(['reference_url' => null]);

        $this->expectException(ValidationException::class);
        app(ModerationService::class)->approve($question, $this->admin);
    }

    public function test_rejection_needs_a_reason_and_is_logged(): void
    {
        $question = $this->create();

        try {
            app(ModerationService::class)->reject($question, $this->admin, '  ');
            $this->fail('Rejected without a reason.');
        } catch (DomainActionException) {
        }

        app(ModerationService::class)->reject($question, $this->admin, 'Outdated figures');
        $this->assertSame('Outdated figures', $question->fresh()->rejection_reason);
        $this->assertDatabaseHas('question_moderation_logs', ['question_id' => $question->id, 'action' => 'rejected', 'reason' => 'Outdated figures']);
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $question = $this->create();

        $this->expectException(DomainActionException::class);
        app(ModerationService::class)->publish($question, $this->admin);
    }

    public function test_unpublish_removes_the_question_from_play_but_keeps_it(): void
    {
        $question = $this->create();
        app(ModerationService::class)->approve($question, $this->admin, publish: true);

        app(ModerationService::class)->unpublish($question, $this->admin, 'Under revision');

        $this->assertSame(QuestionStatus::Unpublished, $question->fresh()->status);
        $this->assertSame([], app(QuestionPool::class)->languages($question));
        $this->assertNotNull(Question::find($question->id));
    }

    public function test_deleting_removes_from_the_pool(): void
    {
        $question = Question::factory()->published()->create(['category_id' => $this->category->id]);
        $this->assertSame(['en'], app(QuestionPool::class)->languages($question));

        app(QuestionService::class)->delete($question, $this->admin);

        $this->assertSoftDeleted($question);
        $this->assertSame([], app(QuestionPool::class)->languages($question));
    }

    public function test_admin_pages_render_and_editor_creates_a_question(): void
    {
        Question::factory()->published()->create(['category_id' => $this->category->id]);

        $this->actingAs($this->admin)->get(route('admin.questions.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.questions.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.moderation'))->assertOk();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.questions.editor')
            ->set('categoryId', (string) $this->category->id)
            ->set('body', 'Which planet is known as the red planet?')
            ->set('choices', ['Venus', 'Mars', 'Jupiter', 'Mercury'])
            ->set('correct', '1')
            ->call('save')
            ->assertHasNoErrors();

        $question = Question::query()->latest('id')->first();
        $this->assertSame('Mars', $question->originTranslation()->choiceText($question->correct_choice_id));
        $this->actingAs($this->admin)->get(route('admin.questions.edit', $question))->assertOk()->assertSee('red planet');
    }

    public function test_players_cannot_open_question_management(): void
    {
        $player = $this->userWithRole(UserRole::Player);

        $this->actingAs($player)->get(route('admin.questions.index'))->assertForbidden();
        $this->actingAs($this->userWithRole(UserRole::Moderator))->get(route('admin.questions.index'))->assertForbidden();
        $this->actingAs($this->userWithRole(UserRole::Moderator))->get(route('admin.moderation'))->assertOk();
    }
}
