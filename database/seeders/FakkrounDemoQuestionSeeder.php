<?php

namespace Database\Seeders;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\ModerationService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\QuestionPool;
use App\Domain\Questions\QuestionService;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A small, published, trilingual question set so a fresh install can play
 * a game straight away. Demo tier only (DatabaseSeeder::DEMO). Facts are
 * deliberately well established and each has a reference.
 *
 * Each entry: category slug, difficulty, correct (0-based), reference, and
 * per language: question text + four answers in display order.
 */
class FakkrounDemoQuestionSeeder extends Seeder
{
    public const QUESTIONS = [
        ['marine-biodiversity', 'easy', 1, 'https://en.wikipedia.org/wiki/Blue_whale', [
            'en' => ['What is the largest animal known to have ever lived?', ['African elephant', 'Blue whale', 'Whale shark', 'Giant squid']],
            'fr' => ['Quel est le plus grand animal connu ayant jamais vécu ?', ['L’éléphant d’Afrique', 'La baleine bleue', 'Le requin-baleine', 'Le calmar géant']],
            'ar' => ['ما أكبر حيوان معروف عاش على الإطلاق؟', ['الفيل الأفريقي', 'الحوت الأزرق', 'القرش الحوتي', 'الحبار العملاق']],
        ]],
        ['marine-biodiversity', 'medium', 2, 'https://en.wikipedia.org/wiki/Sea_turtle', [
            'en' => ['How many species of sea turtle live in the world’s oceans today?', ['Three', 'Five', 'Seven', 'Twelve']],
            'fr' => ['Combien d’espèces de tortues marines vivent aujourd’hui dans les océans ?', ['Trois', 'Cinq', 'Sept', 'Douze']],
            'ar' => ['كم عدد أنواع السلاحف البحرية التي تعيش اليوم في محيطات العالم؟', ['ثلاثة', 'خمسة', 'سبعة', 'اثنا عشر']],
        ]],
        ['marine-biodiversity', 'medium', 0, 'https://en.wikipedia.org/wiki/Coral_bleaching', [
            'en' => ['What is the main cause of mass coral bleaching?', ['Rising sea temperatures', 'Too many fish on the reef', 'Strong moonlight', 'Low tides alone']],
            'fr' => ['Quelle est la principale cause du blanchissement massif des coraux ?', ['La hausse de la température de la mer', 'Trop de poissons sur le récif', 'Une forte lumière lunaire', 'Les marées basses seules']],
            'ar' => ['ما السبب الرئيسي لابيضاض الشعاب المرجانية على نطاق واسع؟', ['ارتفاع درجة حرارة البحر', 'كثرة الأسماك على الشعاب', 'ضوء القمر القوي', 'الجزر وحده']],
        ]],
        ['blue-economy', 'easy', 2, 'https://en.wikipedia.org/wiki/Pacific_Ocean', [
            'en' => ['Which is the largest ocean on Earth?', ['Atlantic Ocean', 'Indian Ocean', 'Pacific Ocean', 'Arctic Ocean']],
            'fr' => ['Quel est le plus grand océan de la Terre ?', ['L’océan Atlantique', 'L’océan Indien', 'L’océan Pacifique', 'L’océan Arctique']],
            'ar' => ['ما أكبر محيط على الأرض؟', ['المحيط الأطلسي', 'المحيط الهندي', 'المحيط الهادئ', 'المحيط المتجمد الشمالي']],
        ]],
        ['blue-economy', 'easy', 1, 'https://en.wikipedia.org/wiki/Ocean', [
            'en' => ['Roughly what share of the Earth’s surface is covered by oceans?', ['About 50%', 'About 71%', 'About 85%', 'About 30%']],
            'fr' => ['Quelle part de la surface de la Terre est environ couverte par les océans ?', ['Environ 50 %', 'Environ 71 %', 'Environ 85 %', 'Environ 30 %']],
            'ar' => ['ما النسبة التقريبية لمساحة سطح الأرض التي تغطيها المحيطات؟', ['نحو 50%', 'نحو 71%', 'نحو 85%', 'نحو 30%']],
        ]],
        ['renewable-energy', 'easy', 3, 'https://en.wikipedia.org/wiki/Solar_cell', [
            'en' => ['Which device turns sunlight directly into electricity?', ['Wind turbine', 'Steam turbine', 'Fuel cell', 'Photovoltaic cell']],
            'fr' => ['Quel dispositif transforme directement la lumière du soleil en électricité ?', ['L’éolienne', 'La turbine à vapeur', 'La pile à combustible', 'La cellule photovoltaïque']],
            'ar' => ['ما الجهاز الذي يحوّل ضوء الشمس مباشرة إلى كهرباء؟', ['توربين الرياح', 'التوربين البخاري', 'خلية الوقود', 'الخلية الكهروضوئية']],
        ]],
        ['renewable-energy', 'easy', 0, 'https://en.wikipedia.org/wiki/Geothermal_energy', [
            'en' => ['Which renewable energy uses heat from inside the Earth?', ['Geothermal energy', 'Tidal energy', 'Biomass', 'Wind energy']],
            'fr' => ['Quelle énergie renouvelable utilise la chaleur de l’intérieur de la Terre ?', ['La géothermie', 'L’énergie marémotrice', 'La biomasse', 'L’énergie éolienne']],
            'ar' => ['ما الطاقة المتجددة التي تستخدم الحرارة من باطن الأرض؟', ['الطاقة الحرارية الجوفية', 'طاقة المد والجزر', 'الكتلة الحيوية', 'طاقة الرياح']],
        ]],
        ['climate-change', 'easy', 1, 'https://en.wikipedia.org/wiki/Greenhouse_gas', [
            'en' => ['Which gas contributes most to human-caused global warming?', ['Oxygen', 'Carbon dioxide', 'Nitrogen', 'Argon']],
            'fr' => ['Quel gaz contribue le plus au réchauffement climatique d’origine humaine ?', ['L’oxygène', 'Le dioxyde de carbone', 'L’azote', 'L’argon']],
            'ar' => ['ما الغاز الذي يساهم أكثر من غيره في الاحترار العالمي الناتج عن النشاط البشري؟', ['الأكسجين', 'ثاني أكسيد الكربون', 'النيتروجين', 'الأرغون']],
        ]],
        ['climate-change', 'medium', 2, 'https://en.wikipedia.org/wiki/Paris_Agreement', [
            'en' => ['In which year was the Paris Agreement on climate change adopted?', ['1997', '2009', '2015', '2021']],
            'fr' => ['En quelle année l’accord de Paris sur le climat a-t-il été adopté ?', ['1997', '2009', '2015', '2021']],
            'ar' => ['في أي عام اعتُمد اتفاق باريس بشأن تغيّر المناخ؟', ['1997', '2009', '2015', '2021']],
        ]],
        ['sustainable-development', 'medium', 0, 'https://sdgs.un.org/goals', [
            'en' => ['How many Sustainable Development Goals did the United Nations adopt in 2015?', ['17', '8', '12', '25']],
            'fr' => ['Combien d’objectifs de développement durable les Nations unies ont-elles adoptés en 2015 ?', ['17', '8', '12', '25']],
            'ar' => ['كم عدد أهداف التنمية المستدامة التي اعتمدتها الأمم المتحدة عام 2015؟', ['17', '8', '12', '25']],
        ]],
        ['environmental-protection', 'easy', 3, 'https://en.wikipedia.org/wiki/Waste_hierarchy', [
            'en' => ['What do the “three Rs” of waste stand for?', ['Repair, rent, return', 'Remove, replace, rebuild', 'Rest, relax, recover', 'Reduce, reuse, recycle']],
            'fr' => ['Que signifient les « 3 R » des déchets ?', ['Réparer, louer, rendre', 'Retirer, remplacer, reconstruire', 'Reposer, relâcher, récupérer', 'Réduire, réutiliser, recycler']],
            'ar' => ['ماذا تعني «القواعد الثلاث» للتعامل مع النفايات؟', ['الإصلاح والاستئجار والإرجاع', 'الإزالة والاستبدال وإعادة البناء', 'الراحة والاسترخاء والتعافي', 'التقليل وإعادة الاستخدام وإعادة التدوير']],
        ]],
        ['green-economy', 'easy', 1, 'https://en.wikipedia.org/wiki/Circular_economy', [
            'en' => ['What is the main aim of a circular economy?', ['Extracting more raw materials', 'Keeping materials in use and cutting waste', 'Producing goods that last one season', 'Moving all factories abroad']],
            'fr' => ['Quel est le principal objectif de l’économie circulaire ?', ['Extraire davantage de matières premières', 'Garder les matériaux en usage et réduire les déchets', 'Produire des biens qui durent une saison', 'Délocaliser toutes les usines']],
            'ar' => ['ما الهدف الرئيسي للاقتصاد الدائري؟', ['استخراج مزيد من المواد الخام', 'إبقاء المواد قيد الاستخدام وتقليل النفايات', 'إنتاج سلع تدوم موسمًا واحدًا', 'نقل جميع المصانع إلى الخارج']],
        ]],
        ['geography', 'easy', 0, 'https://en.wikipedia.org/wiki/Tunis', [
            'en' => ['What is the capital of Tunisia?', ['Tunis', 'Sfax', 'Sousse', 'Kairouan']],
            'fr' => ['Quelle est la capitale de la Tunisie ?', ['Tunis', 'Sfax', 'Sousse', 'Kairouan']],
            'ar' => ['ما عاصمة تونس؟', ['تونس', 'صفاقس', 'سوسة', 'القيروان']],
        ]],
        ['geography', 'easy', 2, 'https://en.wikipedia.org/wiki/Nile', [
            'en' => ['Which is the longest river in Africa?', ['Congo', 'Niger', 'Nile', 'Zambezi']],
            'fr' => ['Quel est le plus long fleuve d’Afrique ?', ['Le Congo', 'Le Niger', 'Le Nil', 'Le Zambèze']],
            'ar' => ['ما أطول نهر في أفريقيا؟', ['الكونغو', 'النيجر', 'النيل', 'الزامبيزي']],
        ]],
        ['science-technology', 'easy', 1, 'https://en.wikipedia.org/wiki/Properties_of_water', [
            'en' => ['What is the chemical formula of water?', ['CO2', 'H2O', 'O2', 'NaCl']],
            'fr' => ['Quelle est la formule chimique de l’eau ?', ['CO2', 'H2O', 'O2', 'NaCl']],
            'ar' => ['ما الصيغة الكيميائية للماء؟', ['CO2', 'H2O', 'O2', 'NaCl']],
        ]],
        ['history', 'medium', 3, 'https://en.wikipedia.org/wiki/Ibn_Khaldun', [
            'en' => ['In which city was Ibn Khaldun, author of the Muqaddimah, born?', ['Cairo', 'Fez', 'Granada', 'Tunis']],
            'fr' => ['Dans quelle ville est né Ibn Khaldoun, auteur de la Muqaddima ?', ['Le Caire', 'Fès', 'Grenade', 'Tunis']],
            'ar' => ['في أي مدينة وُلد ابن خلدون صاحب المقدمة؟', ['القاهرة', 'فاس', 'غرناطة', 'تونس']],
        ]],
        ['general-knowledge', 'easy', 0, 'https://en.wikipedia.org/wiki/Leap_year', [
            'en' => ['How many days are there in a leap year?', ['366', '365', '364', '360']],
            'fr' => ['Combien de jours compte une année bissextile ?', ['366', '365', '364', '360']],
            'ar' => ['كم يومًا في السنة الكبيسة؟', ['366', '365', '364', '360']],
        ]],
        ['sports', 'easy', 2, 'https://en.wikipedia.org/wiki/Association_football', [
            'en' => ['How many players does each football (soccer) team have on the pitch?', ['Nine', 'Ten', 'Eleven', 'Twelve']],
            'fr' => ['Combien de joueurs chaque équipe de football a-t-elle sur le terrain ?', ['Neuf', 'Dix', 'Onze', 'Douze']],
            'ar' => ['كم لاعبًا لكل فريق كرة قدم على أرض الملعب؟', ['تسعة', 'عشرة', 'أحد عشر', 'اثنا عشر']],
        ]],
    ];

    public function run(): void
    {
        $admin = User::role('admin')->first();

        if ($admin === null) {
            $this->command?->warn('No admin user: skipping demo questions.');

            return;
        }

        $questions = app(QuestionService::class);
        $moderation = app(ModerationService::class);
        $created = 0;

        foreach (self::QUESTIONS as [$slug, $difficulty, $correct, $reference, $texts]) {
            $category = Category::query()->where('slug', $slug)->first();

            if ($category === null || Question::query()->where('reference_url', $reference)->where('category_id', $category->id)->exists()) {
                continue;
            }

            [$body, $choices] = $texts['en'];
            $translations = collect($texts)->except('en')->map(fn (array $text) => ['body' => $text[0], 'choices' => $text[1]])->all();

            $question = $questions->create([
                'category_id' => $category->id,
                'difficulty' => $difficulty,
                'language' => 'en',
                'body' => $body,
                'choices' => $choices,
                'correct' => $correct,
                'reference_url' => $reference,
            ], $admin, QuestionSource::Admin, QuestionStatus::Draft, $translations);

            $moderation->approve($question, $admin, publish: true);

            foreach ($question->translations()->where('language', '!=', 'en')->get() as $translation) {
                $moderation->approveTranslation($translation, $admin);
            }

            app(QuestionPool::class)->sync($question);
            $created++;
        }

        $this->command?->info("Demo questions ready: {$created} created.");
    }
}
