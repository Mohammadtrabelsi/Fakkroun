<?php

namespace Database\Seeders;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\QuestionService;
use App\Models\Category;
use App\Services\CategoryService;
use App\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * The six sample questions from the design brief (fakkroun-design/FAKKROUN_PROMPT.md),
 * in en, fr and ar. Demo tier only (DatabaseSeeder::DEMO).
 *
 * Per the brief they are NOT published: they enter the moderation queue as
 * pending ("needs review") without a source, so a moderator must verify the
 * facts and add the reference before they can be approved (categories that
 * require a reference refuse approval without one).
 *
 * Each entry: category slug, difficulty, correct (0-based), and per
 * language: question, four answers in display order, explanation (or null).
 */
class FakkrounDemoQuestionSeeder extends Seeder
{
    /** Display orders applied in turn (index into the brief's answer list). */
    private const ORDERS = [[1, 0, 2, 3], [2, 3, 0, 1], [0, 2, 1, 3], [3, 1, 2, 0], [1, 2, 3, 0], [2, 0, 3, 1]];

    public const QUESTIONS = [
        ['marine-biodiversity', 'medium', 0, [
            'en' => ['Which seagrass, found only in the Mediterranean, forms vast meadows in the Gulf of Gabès?', ['Posidonia oceanica', 'Zostera marina', 'Halophila stipulacea', 'Caulerpa taxifolia'], 'Posidonia grows only in the Mediterranean; its meadows shelter young fish, reduce beach erosion and store carbon in the seabed.'],
            'fr' => ['Quelle plante marine, présente uniquement en Méditerranée, forme de vastes herbiers dans le golfe de Gabès ?', ['Posidonia oceanica', 'Zostera marina', 'Halophila stipulacea', 'Caulerpa taxifolia'], 'La posidonie ne pousse qu’en Méditerranée ; ses herbiers abritent les jeunes poissons, réduisent l’érosion des plages et stockent du carbone dans les fonds marins.'],
            'ar' => ['ما العشب البحري الذي لا يوجد إلا في البحر المتوسط ويكوّن مروجًا واسعة في خليج قابس؟', ['بوسيدونيا أوسيانيكا', 'زوستيرا مارينا', 'هالوفيلا ستيبولاسيا', 'كوليربا تاكسيفوليا'], 'لا تنمو البوسيدونيا إلا في البحر المتوسط؛ تحمي مروجها صغار الأسماك وتحدّ من انجراف الشواطئ وتخزّن الكربون في قاع البحر.'],
        ]],
        ['marine-biodiversity', 'easy', 0, [
            'en' => ['Which sea turtle species nests on the Kuriat Islands, off Monastir?', ['Loggerhead (Caretta caretta)', 'Leatherback', 'Green turtle', 'Hawksbill'], null],
            'fr' => ['Quelle espèce de tortue marine pond sur les îles Kuriat, au large de Monastir ?', ['La caouanne (Caretta caretta)', 'La tortue luth', 'La tortue verte', 'La tortue imbriquée'], null],
            'ar' => ['أي نوع من السلاحف البحرية يعشّش في جزر قوريا قبالة المنستير؟', ['السلحفاة ضخمة الرأس (كاريتا كاريتا)', 'السلحفاة جلدية الظهر', 'السلحفاة الخضراء', 'سلحفاة منقار الصقر'], null],
        ]],
        ['blue-economy', 'easy', 0, [
            'en' => ['What does the “blue economy” mean?', ['Using ocean resources sustainably for growth and jobs while keeping marine ecosystems healthy', 'An economy based only on offshore oil and gas', 'The total value of goods shipped by sea', 'Banking services for coastal towns'], null],
            'fr' => ['Que signifie « l’économie bleue » ?', ['Utiliser durablement les ressources de la mer pour la croissance et l’emploi tout en préservant les écosystèmes marins', 'Une économie fondée uniquement sur le pétrole et le gaz en mer', 'La valeur totale des marchandises transportées par mer', 'Les services bancaires des villes côtières'], null],
            'ar' => ['ماذا يعني «الاقتصاد الأزرق»؟', ['استغلال موارد البحر بشكل مستدام من أجل النمو والتشغيل مع الحفاظ على سلامة النظم البيئية البحرية', 'اقتصاد يقوم فقط على النفط والغاز البحريين', 'القيمة الإجمالية للبضائع المنقولة بحرًا', 'الخدمات المصرفية في المدن الساحلية'], null],
        ]],
        ['sustainable-development', 'easy', 0, [
            'en' => ['How many Sustainable Development Goals did the UN adopt in 2015?', ['17', '8', '12', '21'], null],
            'fr' => ['Combien d’objectifs de développement durable l’ONU a-t-elle adoptés en 2015 ?', ['17', '8', '12', '21'], null],
            'ar' => ['كم عدد أهداف التنمية المستدامة التي اعتمدتها الأمم المتحدة عام 2015؟', ['17', '8', '12', '21'], null],
        ]],
        ['renewable-energy', 'easy', 0, [
            'en' => ['What does a photovoltaic panel turn into electricity?', ['Sunlight', 'Wind', 'Heat from the ground', 'Tides'], null],
            'fr' => ['Qu’est-ce qu’un panneau photovoltaïque transforme en électricité ?', ['La lumière du soleil', 'Le vent', 'La chaleur du sol', 'Les marées'], null],
            'ar' => ['ماذا تحوّل اللوحة الكهروضوئية إلى كهرباء؟', ['ضوء الشمس', 'الرياح', 'حرارة الأرض', 'المد والجزر'], null],
        ]],
        ['blue-economy', 'medium', 0, [
            'en' => ['Which archipelago off Sfax is known for its traditional “charfia” fixed fishing traps?', ['Kerkennah', 'Djerba', 'Zembra', 'Galite'], null],
            'fr' => ['Quel archipel au large de Sfax est connu pour ses pêcheries fixes traditionnelles, les « charfia » ?', ['Kerkennah', 'Djerba', 'Zembra', 'La Galite'], null],
            'ar' => ['أي أرخبيل قبالة صفاقس يشتهر بمصائد «الشرفية» الثابتة التقليدية؟', ['قرقنة', 'جربة', 'زمبرة', 'جالطة'], null],
        ]],
    ];

    public function run(): void
    {
        $admin = app(UserService::class)->query()->role('admin')->first();

        if ($admin === null) {
            $this->command?->warn('No admin user: skipping sample questions.');

            return;
        }

        $questions = app(QuestionService::class);
        $categories = app(CategoryService::class);
        $created = 0;

        foreach (self::QUESTIONS as $index => [$slug, $difficulty, $correct, $texts]) {
            $category = $categories->findBy('slug', $slug);

            if ($category === null) {
                continue;
            }

            // The brief lists the correct answer first; shuffle the display
            // order (the same way in every language) so it is not always "A".
            $order = self::ORDERS[$index % count(self::ORDERS)];
            $reorder = fn (array $answers): array => array_map(fn (int $i) => $answers[$i], $order);
            $correct = (int) array_search($correct, $order, true);

            [$body, $choices, $explanation] = $texts['en'];
            $choices = $reorder($choices);
            $translations = collect($texts)->except('en')
                ->map(fn (array $text) => ['body' => $text[0], 'choices' => $reorder($text[1]), 'explanation' => $text[2]])
                ->all();

            try {
                $questions->create([
                    'category_id' => $category->id,
                    'difficulty' => $difficulty,
                    'language' => 'en',
                    'body' => $body,
                    'choices' => $choices,
                    'correct' => $correct,
                    'explanation' => $explanation,
                ], $admin, QuestionSource::Admin, QuestionStatus::Pending, $translations);
                $created++;
            } catch (ValidationException) {
                // Already seeded (exact duplicate): re-running the seeder is safe.
            }
        }

        $this->command?->info("Sample questions waiting for review: {$created}.");
    }
}
