<?php

namespace Database\Seeders;

use App\Enums\ValidationPolicy;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The initial Fakkroun topics (spec §6), in display order. Environmental and
 * scientific topics require administrator approval and a reference (spec
 * §7.4); general topics allow moderator approval.
 *
 * Idempotent: rows are matched by slug and only missing ones are created, so
 * re-seeding never overwrites what administrators changed.
 */
class FakkrounCategorySeeder extends Seeder
{
    /** The seven planet themes that lead the home page grid. */
    public const PRIORITY = [
        'green-economy', 'blue-economy', 'environmental-protection', 'climate-change',
        'renewable-energy', 'marine-biodiversity', 'sustainable-development',
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    public const CATEGORIES = [
        [
            'slug' => 'green-economy', 'icon' => 'leaf', 'color' => '#2f855a',
            'name' => ['en' => 'Green Economy', 'fr' => 'Économie verte', 'ar' => 'الاقتصاد الأخضر'],
            'description' => ['en' => 'Circular production, green jobs, recycling and sustainable farming.', 'fr' => 'Production circulaire, emplois verts, recyclage et agriculture durable.', 'ar' => 'الإنتاج الدائري والوظائف الخضراء وإعادة التدوير والزراعة المستدامة.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'blue-economy', 'icon' => 'wave', 'color' => '#2b6cb0',
            'name' => ['en' => 'Blue Economy', 'fr' => 'Économie bleue', 'ar' => 'الاقتصاد الأزرق'],
            'description' => ['en' => 'Fishing, ports, aquaculture and coastal life that depend on a healthy sea.', 'fr' => 'Pêche, ports, aquaculture et vie côtière qui dépendent d’une mer en bonne santé.', 'ar' => 'الصيد والموانئ وتربية الأحياء المائية والحياة الساحلية التي تعتمد على بحر سليم.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'environmental-protection', 'icon' => 'shield-leaf', 'color' => '#38a169',
            'name' => ['en' => 'Environmental Protection', 'fr' => 'Protection de l’environnement', 'ar' => 'حماية البيئة'],
            'description' => ['en' => 'Pollution, waste, protected areas and the everyday choices that matter.', 'fr' => 'Pollution, déchets, aires protégées et gestes du quotidien qui comptent.', 'ar' => 'التلوث والنفايات والمناطق المحمية والخيارات اليومية المهمة.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'renewable-energy', 'icon' => 'sun', 'color' => '#d69e2e',
            'name' => ['en' => 'Renewable Energy', 'fr' => 'Énergies renouvelables', 'ar' => 'الطاقات المتجددة'],
            'description' => ['en' => 'Sun, wind, water and the grid that connects them.', 'fr' => 'Soleil, vent, eau et le réseau qui les relie.', 'ar' => 'الشمس والرياح والماء والشبكة التي تربط بينها.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'climate-change', 'icon' => 'thermometer', 'color' => '#c05621',
            'name' => ['en' => 'Climate Change', 'fr' => 'Changement climatique', 'ar' => 'التغيّر المناخي'],
            'description' => ['en' => 'Causes, consequences and how the Mediterranean is already feeling it.', 'fr' => 'Causes, conséquences et ce que la Méditerranée ressent déjà.', 'ar' => 'الأسباب والعواقب وكيف بدأ البحر المتوسط يشعر بها بالفعل.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'marine-biodiversity', 'icon' => 'turtle', 'color' => '#319795',
            'name' => ['en' => 'Marine Biodiversity', 'fr' => 'Biodiversité marine', 'ar' => 'التنوع البيولوجي البحري'],
            'description' => ['en' => 'Sea turtles, seagrass meadows, fish nurseries and the species we share the coast with.', 'fr' => 'Tortues marines, herbiers, nurseries de poissons et espèces avec qui nous partageons la côte.', 'ar' => 'السلاحف البحرية ومروج الأعشاب البحرية وحضانات الأسماك والأنواع التي نتقاسم معها الساحل.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'sustainable-development', 'icon' => 'globe', 'color' => '#2c7a7b',
            'name' => ['en' => 'Sustainable Development', 'fr' => 'Développement durable', 'ar' => 'التنمية المستدامة'],
            'description' => ['en' => 'The Sustainable Development Goals and what they mean close to home.', 'fr' => 'Les objectifs de développement durable et ce qu’ils signifient près de chez nous.', 'ar' => 'أهداف التنمية المستدامة وما تعنيه في محيطنا القريب.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'general-knowledge', 'icon' => 'lightbulb', 'color' => '#805ad5',
            'name' => ['en' => 'General Knowledge', 'fr' => 'Culture générale', 'ar' => 'المعلومات العامة'],
            'description' => ['en' => 'Everyday knowledge.', 'fr' => 'Connaissances du quotidien.', 'ar' => 'معارف الحياة اليومية.'],
            'policy' => ValidationPolicy::Moderator, 'reference' => false,
        ],
        [
            'slug' => 'science-technology', 'icon' => 'atom', 'color' => '#3182ce',
            'name' => ['en' => 'Science & Technology', 'fr' => 'Sciences et technologies', 'ar' => 'العلوم والتكنولوجيا'],
            'description' => ['en' => 'Scientific and technological knowledge.', 'fr' => 'Connaissances scientifiques et technologiques.', 'ar' => 'المعارف العلمية والتكنولوجية.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'history', 'icon' => 'scroll', 'color' => '#975a16',
            'name' => ['en' => 'History', 'fr' => 'Histoire', 'ar' => 'التاريخ'],
            'description' => ['en' => 'Historical events and civilisations.', 'fr' => 'Événements historiques et civilisations.', 'ar' => 'الأحداث التاريخية والحضارات.'],
            'policy' => ValidationPolicy::Moderator, 'reference' => true,
        ],
        [
            'slug' => 'geography', 'icon' => 'map', 'color' => '#4a5568',
            'name' => ['en' => 'Geography', 'fr' => 'Géographie', 'ar' => 'الجغرافيا'],
            'description' => ['en' => 'Countries, regions and geography.', 'fr' => 'Pays, régions et géographie.', 'ar' => 'الدول والمناطق والجغرافيا.'],
            'policy' => ValidationPolicy::Moderator, 'reference' => false,
        ],
        [
            'slug' => 'culture', 'icon' => 'palette', 'color' => '#b83280',
            'name' => ['en' => 'Culture', 'fr' => 'Culture', 'ar' => 'الثقافة'],
            'description' => ['en' => 'Art, literature and traditions.', 'fr' => 'Art, littérature et traditions.', 'ar' => 'الفن والأدب والتقاليد.'],
            'policy' => ValidationPolicy::Moderator, 'reference' => false,
        ],
        [
            'slug' => 'sports', 'icon' => 'trophy', 'color' => '#e53e3e',
            'name' => ['en' => 'Sports', 'fr' => 'Sports', 'ar' => 'الرياضة'],
            'description' => ['en' => 'Sports and sporting events.', 'fr' => 'Sports et événements sportifs.', 'ar' => 'الرياضة والأحداث الرياضية.'],
            'policy' => ValidationPolicy::Moderator, 'reference' => false,
        ],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $position => $category) {
            $priority = in_array($category['slug'], self::PRIORITY, true);

            if (Category::withTrashed()->where('slug', $category['slug'])->exists()) {
                continue;
            }

            Category::query()->create([
                'slug' => $category['slug'],
                'name' => $category['name'],
                'description' => $category['description'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'sort_order' => $position + 1,
                'validation_policy' => $category['policy'],
                'requires_reference' => $category['reference'],
                'allow_community_submissions' => true,
                'is_active' => true,
                'is_priority' => $priority,
            ]);
        }
    }
}
