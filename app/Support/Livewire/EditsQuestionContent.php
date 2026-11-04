<?php

namespace App\Support\Livewire;

use App\Services\CategoryService;
use App\Models\Category;
use Livewire\Attributes\Computed;

/**
 * Form state shared by the admin question editor and the player submission
 * form (see resources/views/partials/questions/fields.blade.php).
 */
trait EditsQuestionContent
{
    public string $categoryId = '';

    public string $subcategoryId = '';

    public string $difficulty = 'medium';

    public string $language = '';

    public string $body = '';

    /** @var array<int, string> */
    public array $choices = ['', '', '', ''];

    public string $correct = '0';

    public string $explanation = '';

    public string $referenceUrl = '';

    public string $referenceText = '';

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = '';
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function categoryOptions(): array
    {
        return app(CategoryService::class)->tree(activeOnly: true)
            ->filter(fn (Category $category) => $this->categoryFilter($category))
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function subcategoryOptions(): array
    {
        if ($this->categoryId === '') {
            return [];
        }

        return Category::query()
            ->where('parent_id', (int) $this->categoryId)
            ->where('is_active', true)
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name])
            ->all();
    }

    /**
     * Which top-level categories the form offers.
     */
    protected function categoryFilter(Category $category): bool
    {
        return true;
    }

    /**
     * Form state in QuestionService's content shape.
     *
     * @return array<string, mixed>
     */
    protected function contentData(): array
    {
        return [
            'category_id' => $this->categoryId !== '' ? (int) $this->categoryId : null,
            'subcategory_id' => $this->subcategoryId !== '' ? (int) $this->subcategoryId : null,
            'difficulty' => $this->difficulty,
            'language' => $this->language,
            'body' => $this->body,
            'choices' => array_values($this->choices),
            'correct' => (int) $this->correct,
            'explanation' => $this->explanation !== '' ? $this->explanation : null,
            'reference_url' => $this->referenceUrl !== '' ? $this->referenceUrl : null,
            'reference_text' => $this->referenceText !== '' ? $this->referenceText : null,
        ];
    }
}
