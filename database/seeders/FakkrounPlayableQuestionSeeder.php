<?php

namespace Database\Seeders;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Domain\Questions\ModerationService;
use App\Domain\Questions\QuestionService;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * A playable trilingual question set (en, fr, ar). Demo tier only
 * (DatabaseSeeder::DEMO).
 *
 * Unlike FakkrounDemoQuestionSeeder, these questions carry a reference and
 * go through the real moderation flow (approve + publish by the admin, then
 * every translation approved), so they land in the question pool and a demo
 * room can start a game straight away.
 *
 * Each entry: category slug, difficulty, correct (0-based), reference URL,
 * and per language: question, four answers in display order, explanation (or null).
 */
class FakkrounPlayableQuestionSeeder extends Seeder
{
    public const QUESTIONS = [
        ['climate-change', 'easy', 1, 'https://www.ipcc.ch/report/ar6/wg1/', [
            'en' => ['Which gas contributes the most to human-caused global warming?', ['Oxygen', 'Carbon dioxide (CO₂)', 'Nitrogen', 'Helium'], 'Burning coal, oil and gas releases CO₂, the largest single driver of the warming observed since the industrial era.'],
            'fr' => ['Quel gaz contribue le plus au réchauffement climatique d’origine humaine ?', ['L’oxygène', 'Le dioxyde de carbone (CO₂)', 'L’azote', 'L’hélium'], 'La combustion du charbon, du pétrole et du gaz libère du CO₂, principal moteur du réchauffement observé depuis l’ère industrielle.'],
            'ar' => ['ما الغاز الذي يساهم أكثر من غيره في الاحترار العالمي الناتج عن النشاط البشري؟', ['الأكسجين', 'ثاني أكسيد الكربون', 'النيتروجين', 'الهيليوم'], 'يطلق حرق الفحم والنفط والغاز ثاني أكسيد الكربون، وهو المحرك الرئيسي للاحترار المسجَّل منذ العصر الصناعي.'],
        ]],
        ['climate-change', 'medium', 2, 'https://unfccc.int/process-and-meetings/the-paris-agreement', [
            'en' => ['The Paris Agreement aims to keep warming well below 2 °C while pursuing efforts to limit it to…', ['0.5 °C', '1 °C', '1.5 °C', '3 °C'], null],
            'fr' => ['L’accord de Paris vise à maintenir le réchauffement bien en dessous de 2 °C tout en poursuivant l’effort pour le limiter à…', ['0,5 °C', '1 °C', '1,5 °C', '3 °C'], null],
            'ar' => ['يهدف اتفاق باريس إلى إبقاء الاحترار دون درجتين مئويتين بكثير مع مواصلة الجهود لحصره في…', ['0.5 درجة مئوية', 'درجة مئوية واحدة', '1.5 درجة مئوية', '3 درجات مئوية'], null],
        ]],
        ['renewable-energy', 'easy', 3, 'https://www.iea.org/topics/renewables', [
            'en' => ['Which of these energy sources is NOT renewable?', ['Solar power', 'Wind power', 'Hydropower', 'Natural gas'], 'Natural gas is a fossil fuel: it formed over millions of years and is used up far faster than it is replaced.'],
            'fr' => ['Laquelle de ces sources d’énergie n’est PAS renouvelable ?', ['L’énergie solaire', 'L’énergie éolienne', 'L’hydroélectricité', 'Le gaz naturel'], 'Le gaz naturel est un combustible fossile : il s’est formé en des millions d’années et se consomme bien plus vite qu’il ne se renouvelle.'],
            'ar' => ['أيّ مصادر الطاقة التالية ليس متجددًا؟', ['الطاقة الشمسية', 'طاقة الرياح', 'الطاقة الكهرومائية', 'الغاز الطبيعي'], 'الغاز الطبيعي وقود أحفوري تكوّن على مدى ملايين السنين ويُستهلك أسرع بكثير مما يتجدد.'],
        ]],
        ['renewable-energy', 'medium', 0, 'https://www.masen.ma/en/projects/noor-ouarzazate', [
            'en' => ['Near which city is the Noor solar complex, one of the largest in Africa?', ['Ouarzazate', 'Sfax', 'Alexandria', 'Tamanrasset'], null],
            'fr' => ['Près de quelle ville se trouve le complexe solaire Noor, l’un des plus grands d’Afrique ?', ['Ouarzazate', 'Sfax', 'Alexandrie', 'Tamanrasset'], null],
            'ar' => ['بالقرب من أي مدينة يقع مجمّع نور للطاقة الشمسية، أحد أكبر المجمّعات في إفريقيا؟', ['ورزازات', 'صفاقس', 'الإسكندرية', 'تمنراست'], null],
        ]],
        ['environmental-protection', 'easy', 2, 'https://whc.unesco.org/en/list/8', [
            'en' => ['Which Tunisian national park, a UNESCO World Heritage site, is a key stop for migrating birds?', ['Bou-Hedma', 'Chambi', 'Ichkeul', 'El Feija'], 'Lake Ichkeul and its marshes host hundreds of thousands of ducks, geese and storks every winter.'],
            'fr' => ['Quel parc national tunisien, inscrit au patrimoine mondial de l’UNESCO, est une étape clé pour les oiseaux migrateurs ?', ['Bou-Hedma', 'Chambi', 'Ichkeul', 'El Feija'], 'Le lac Ichkeul et ses marais accueillent chaque hiver des centaines de milliers de canards, d’oies et de cigognes.'],
            'ar' => ['أيّ منتزه وطني تونسي مدرج في قائمة التراث العالمي لليونسكو يُعدّ محطة رئيسية للطيور المهاجرة؟', ['بوهدمة', 'الشعانبي', 'إشكل', 'الفايجة'], 'تستقبل بحيرة إشكل ومستنقعاتها كل شتاء مئات الآلاف من البط والإوز واللقالق.'],
        ]],
        ['environmental-protection', 'medium', 2, 'https://oceanservice.noaa.gov/hazards/marinedebris/', [
            'en' => ['Roughly how long can a plastic bottle last in the sea before breaking down?', ['5 years', '50 years', 'About 450 years', 'It never enters the sea'], 'Plastic does not biodegrade: it slowly breaks into microplastics that stay in the water and food chain.'],
            'fr' => ['Combien de temps environ une bouteille en plastique peut-elle durer en mer avant de se dégrader ?', ['5 ans', '50 ans', 'Environ 450 ans', 'Elle n’arrive jamais en mer'], 'Le plastique ne se biodégrade pas : il se fragmente lentement en microplastiques qui restent dans l’eau et la chaîne alimentaire.'],
            'ar' => ['كم من الوقت تقريبًا يمكن أن تبقى قارورة بلاستيكية في البحر قبل أن تتحلل؟', ['5 سنوات', '50 سنة', 'نحو 450 سنة', 'لا تصل إلى البحر أبدًا'], 'لا يتحلل البلاستيك حيويًا، بل يتفتت ببطء إلى جسيمات دقيقة تبقى في الماء وفي السلسلة الغذائية.'],
        ]],
        ['green-economy', 'easy', 0, 'https://www.ellenmacarthurfoundation.org/topics/circular-economy-introduction/overview', [
            'en' => ['What is the main goal of a “circular economy”?', ['Keeping products and materials in use and designing out waste', 'Producing as many new goods as possible', 'Burning waste to make electricity', 'Shipping waste to other countries'], null],
            'fr' => ['Quel est l’objectif principal de « l’économie circulaire » ?', ['Garder produits et matériaux en usage et concevoir sans déchets', 'Produire le plus de biens neufs possible', 'Brûler les déchets pour produire de l’électricité', 'Expédier les déchets vers d’autres pays'], null],
            'ar' => ['ما الهدف الرئيسي من «الاقتصاد الدائري»؟', ['إبقاء المنتجات والمواد قيد الاستعمال والتصميم دون نفايات', 'إنتاج أكبر قدر ممكن من السلع الجديدة', 'حرق النفايات لإنتاج الكهرباء', 'شحن النفايات إلى بلدان أخرى'], null],
        ]],
        ['green-economy', 'medium', 1, 'https://environment.ec.europa.eu/topics/waste-and-recycling/waste-framework-directive_en', [
            'en' => ['In the waste hierarchy, which option comes first?', ['Recycling', 'Prevention (reducing waste)', 'Energy recovery', 'Landfill'], 'The best waste is the waste never produced; reuse, recycling and recovery follow, with disposal last.'],
            'fr' => ['Dans la hiérarchie des déchets, quelle option vient en premier ?', ['Le recyclage', 'La prévention (réduire les déchets)', 'La valorisation énergétique', 'La mise en décharge'], 'Le meilleur déchet est celui qu’on ne produit pas ; viennent ensuite le réemploi, le recyclage et la valorisation, l’élimination en dernier.'],
            'ar' => ['في تسلسل إدارة النفايات، أيّ خيار يأتي أولًا؟', ['إعادة التدوير', 'الوقاية (التقليل من النفايات)', 'الاسترجاع الطاقي', 'الردم في المصبّات'], 'أفضل النفايات هي التي لا تُنتَج أصلًا؛ تليها إعادة الاستعمال ثم التدوير ثم الاسترجاع، والتخلص في الأخير.'],
        ]],
        ['marine-biodiversity', 'hard', 3, 'https://www.iucnredlist.org/species/13653/117647375', [
            'en' => ['Which marine mammal, once common on Tunisian coasts, is among the most endangered in the Mediterranean?', ['Bottlenose dolphin', 'Fin whale', 'Sperm whale', 'Mediterranean monk seal'], null],
            'fr' => ['Quel mammifère marin, autrefois commun sur les côtes tunisiennes, est parmi les plus menacés de Méditerranée ?', ['Le grand dauphin', 'Le rorqual commun', 'Le cachalot', 'Le phoque moine de Méditerranée'], null],
            'ar' => ['أيّ حيوان ثديي بحري كان شائعًا على السواحل التونسية ويُعدّ من أكثر الأنواع المهددة في البحر المتوسط؟', ['الدلفين قاروري الأنف', 'الحوت الزعنفي', 'حوت العنبر', 'فقمة الراهب المتوسطية'], null],
        ]],
        ['marine-biodiversity', 'medium', 1, 'https://www.fisheries.noaa.gov/national/marine-life-distress/how-plastic-affects-sea-turtles', [
            'en' => ['Why do sea turtles often swallow plastic bags?', ['They use them to build nests', 'They mistake them for jellyfish', 'They are attracted by the smell of oil', 'They eat them to stay afloat'], null],
            'fr' => ['Pourquoi les tortues marines avalent-elles souvent des sacs en plastique ?', ['Pour construire leurs nids', 'Elles les confondent avec des méduses', 'L’odeur du pétrole les attire', 'Pour rester à la surface'], null],
            'ar' => ['لماذا تبتلع السلاحف البحرية الأكياس البلاستيكية في أغلب الأحيان؟', ['لتبني بها أعشاشها', 'لأنها تظنها قناديل بحر', 'لأن رائحة النفط تجذبها', 'لتبقى طافية'], null],
        ]],
        ['sustainable-development', 'medium', 2, 'https://sdgs.un.org/goals/goal14', [
            'en' => ['Which Sustainable Development Goal is “Life below water”?', ['SDG 6', 'SDG 13', 'SDG 14', 'SDG 15'], 'SDG 14 is about conserving and sustainably using the oceans, seas and marine resources.'],
            'fr' => ['Quel objectif de développement durable s’intitule « Vie aquatique » ?', ['L’ODD 6', 'L’ODD 13', 'L’ODD 14', 'L’ODD 15'], 'L’ODD 14 vise à conserver et exploiter de manière durable les océans, les mers et les ressources marines.'],
            'ar' => ['أيّ هدف من أهداف التنمية المستدامة يحمل عنوان «الحياة تحت الماء»؟', ['الهدف 6', 'الهدف 13', 'الهدف 14', 'الهدف 15'], 'يتعلق الهدف 14 بحفظ المحيطات والبحار والموارد البحرية واستخدامها على نحو مستدام.'],
        ]],
        ['blue-economy', 'easy', 2, 'https://www.usgs.gov/special-topics/water-science-school/science/how-much-water-there-earth', [
            'en' => ['About how much of the Earth’s surface is covered by oceans?', ['30%', '50%', '71%', '90%'], null],
            'fr' => ['Quelle part de la surface de la Terre est couverte par les océans, environ ?', ['30 %', '50 %', '71 %', '90 %'], null],
            'ar' => ['ما النسبة التقريبية لسطح الأرض التي تغطيها المحيطات؟', ['30%', '50%', '71%', '90%'], null],
        ]],
        ['general-knowledge', 'easy', 0, null, [
            'en' => ['What is the capital city of Tunisia?', ['Tunis', 'Sousse', 'Sfax', 'Kairouan'], null],
            'fr' => ['Quelle est la capitale de la Tunisie ?', ['Tunis', 'Sousse', 'Sfax', 'Kairouan'], null],
            'ar' => ['ما هي عاصمة تونس؟', ['تونس', 'سوسة', 'صفاقس', 'القيروان'], null],
        ]],
        ['geography', 'easy', 1, null, [
            'en' => ['Which is the longest river in Africa?', ['Congo', 'Nile', 'Niger', 'Zambezi'], null],
            'fr' => ['Quel est le plus long fleuve d’Afrique ?', ['Le Congo', 'Le Nil', 'Le Niger', 'Le Zambèze'], null],
            'ar' => ['ما أطول نهر في إفريقيا؟', ['الكونغو', 'النيل', 'النيجر', 'الزمبيزي'], null],
        ]],
        ['science-technology', 'easy', 3, null, [
            'en' => ['What is the chemical formula of water?', ['CO₂', 'O₂', 'NaCl', 'H₂O'], null],
            'fr' => ['Quelle est la formule chimique de l’eau ?', ['CO₂', 'O₂', 'NaCl', 'H₂O'], null],
            'ar' => ['ما الصيغة الكيميائية للماء؟', ['CO₂', 'O₂', 'NaCl', 'H₂O'], null],
        ]],
        ['history', 'medium', 2, null, [
            'en' => ['In which year did Tunisia gain its independence?', ['1946', '1952', '1956', '1962'], null],
            'fr' => ['En quelle année la Tunisie a-t-elle obtenu son indépendance ?', ['1946', '1952', '1956', '1962'], null],
            'ar' => ['في أي سنة نالت تونس استقلالها؟', ['1946', '1952', '1956', '1962'], null],
        ]],
    ];

    public function run(): void
    {
        $admin = User::role('admin')->first();

        if ($admin === null) {
            $this->command?->warn('No admin user: skipping playable questions.');

            return;
        }

        $questions = app(QuestionService::class);
        $moderation = app(ModerationService::class);
        $published = 0;

        foreach (self::QUESTIONS as [$slug, $difficulty, $correct, $reference, $texts]) {
            $category = Category::query()->where('slug', $slug)->first();

            if ($category === null) {
                continue;
            }

            [$body, $choices, $explanation] = $texts['en'];
            $translations = collect($texts)->except('en')
                ->map(fn (array $text) => ['body' => $text[0], 'choices' => $text[1], 'explanation' => $text[2]])
                ->all();

            try {
                $question = $questions->create([
                    'category_id' => $category->id,
                    'difficulty' => $difficulty,
                    'language' => 'en',
                    'body' => $body,
                    'choices' => $choices,
                    'correct' => $correct,
                    'explanation' => $explanation,
                    'reference_url' => $reference,
                ], $admin, QuestionSource::Admin, QuestionStatus::Pending, $translations);
            } catch (ValidationException) {
                // Already seeded (exact duplicate): re-running the seeder is safe.
                continue;
            }

            $moderation->approve($question, $admin, publish: true);

            $question->translations()
                ->where('language', '!=', $question->origin_language)
                ->where('status', TranslationStatus::Pending->value)
                ->get()
                ->each(fn ($translation) => $moderation->approveTranslation($translation, $admin));

            $published++;
        }

        $this->command?->info("Playable questions published: {$published}.");
    }
}
