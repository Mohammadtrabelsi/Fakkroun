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
    /**
     * @var array<int, array<string, mixed>>
     */
    public const CATEGORIES = [
        [
            'slug' => 'green-economy', 'icon' => 'leaf', 'color' => '#2f855a',
            'name' => ['en' => 'Green Economy', 'fr' => 'Économie verte', 'ar' => 'الاقتصاد الأخضر'],
            'description' => ['en' => 'Sustainable economic development.', 'fr' => 'Développement économique durable.', 'ar' => 'التنمية الاقتصادية المستدامة.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'blue-economy', 'icon' => 'wave', 'color' => '#2b6cb0',
            'name' => ['en' => 'Blue Economy', 'fr' => 'Économie bleue', 'ar' => 'الاقتصاد الأزرق'],
            'description' => ['en' => 'Marine resources and sustainable maritime activities.', 'fr' => 'Ressources marines et activités maritimes durables.', 'ar' => 'الموارد البحرية والأنشطة البحرية المستدامة.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'environmental-protection', 'icon' => 'shield-leaf', 'color' => '#38a169',
            'name' => ['en' => 'Environmental Protection', 'fr' => 'Protection de l’environnement', 'ar' => 'حماية البيئة'],
            'description' => ['en' => 'Pollution, waste and conservation.', 'fr' => 'Pollution, déchets et conservation.', 'ar' => 'التلوث والنفايات والحفاظ على البيئة.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'renewable-energy', 'icon' => 'sun', 'color' => '#d69e2e',
            'name' => ['en' => 'Renewable Energy', 'fr' => 'Énergies renouvelables', 'ar' => 'الطاقة المتجددة'],
            'description' => ['en' => 'Solar, wind and clean energy.', 'fr' => 'Énergie solaire, éolienne et propre.', 'ar' => 'الطاقة الشمسية وطاقة الرياح والطاقة النظيفة.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'climate-change', 'icon' => 'thermometer', 'color' => '#c05621',
            'name' => ['en' => 'Climate Change', 'fr' => 'Changement climatique', 'ar' => 'تغيّر المناخ'],
            'description' => ['en' => 'Climate science and adaptation.', 'fr' => 'Science du climat et adaptation.', 'ar' => 'علوم المناخ والتكيّف معه.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'marine-biodiversity', 'icon' => 'turtle', 'color' => '#319795',
            'name' => ['en' => 'Marine Biodiversity', 'fr' => 'Biodiversité marine', 'ar' => 'التنوع البيولوجي البحري'],
            'description' => ['en' => 'Marine species and ecosystems.', 'fr' => 'Espèces et écosystèmes marins.', 'ar' => 'الأنواع والنظم البيئية البحرية.'],
            'policy' => ValidationPolicy::Admin, 'reference' => true,
        ],
        [
            'slug' => 'sustainable-development', 'icon' => 'globe', 'color' => '#2c7a7b',
            'name' => ['en' => 'Sustainable Development', 'fr' => 'Développement durable', 'ar' => 'التنمية المستدامة'],
            'description' => ['en' => 'The Sustainable Development Goals.', 'fr' => 'Les objectifs de développement durable.', 'ar' => 'أهداف التنمية المستدامة.'],
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
            ]);
        }
    }
}
