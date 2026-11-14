<?php

return [
    'account_types' => [
        0 => [
            'name' => 'Compte gratuit',
            'points' => [
                0 => 'Tous les modes de jeu',
                1 => 'Proposer des questions',
                2 => 'Des publicités à des moments calmes, jamais pendant que vous répondez',
            ],
        ],
        1 => [
            'name' => 'Compte payant (sans publicité)',
            'points' => [
                0 => 'Tout le compte gratuit',
                1 => 'Aucune publicité pendant le jeu',
                2 => 'Les salles que vous créez sont sans publicité pour tous',
                3 => 'Paiement en espèces pour le moment ; d’autres moyens plus tard',
            ],
        ],
    ],
    'contents' => 'Sommaire',
    'draft_notice' => 'Brouillon : ces conditions sont en cours de relecture et peuvent changer avant le lancement.',
    'eyebrow' => 'Conditions générales',
    'formats' => [
        'title' => 'Où apparaissent les publicités',
        'format' => 'Format',
        'where' => 'Où',
        'counted' => 'Ce qui est compté',
        'rows' => [
            0 => [
                0 => 'Bannière après une réponse',
                1 => 'Sous l’explication, une fois la question fermée',
                2 => 'Un affichage par joueur, seulement s’il a vraiment été à l’écran',
            ],
            1 => [
                0 => 'Bannière des résultats',
                1 => 'Sur la page des résultats, après la partie',
                2 => 'Un affichage par joueur, seulement s’il a vraiment été à l’écran',
            ],
            2 => [
                0 => 'Bannière de la salle d’attente',
                1 => 'Dans la salle d’attente, avant la partie',
                2 => 'Un affichage par joueur, seulement s’il a vraiment été à l’écran',
            ],
            3 => [
                0 => 'Question sponsorisée',
                1 => 'Une question de la partie, validée comme les autres',
                2 => 'Une par salle qui la joue',
            ],
            4 => [
                0 => 'Manche sponsorisée',
                1 => 'Une manche de questions sur un thème',
                2 => 'Une par salle qui la joue',
            ],
        ],
    ],
    'formats.counted' => '[TODO] Formats.counted',
    'formats.format' => '[TODO] Formats.format',
    'formats.rows' => '[TODO] Formats.rows',
    'formats.title' => '[TODO] Formats.title',
    'formats.where' => '[TODO] Formats.where',
    'lead' => 'Les règles pour jouer sur Fakkroun, proposer des questions et faire de la publicité chez nous.',
    'packs' => [
        'title' => 'Offres publicitaires',
        'price_placeholder' => 'Prix et contenu : [PACK DETAILS]',
        'items' => [
            0 => [
                'name' => 'Essentiel',
                'for' => 'Pour les associations, écoles et petites structures',
            ],
            1 => [
                'name' => 'Professionnel',
                'for' => 'Pour les entreprises et les campagnes plus larges',
            ],
            2 => [
                'name' => 'Entreprise',
                'for' => 'Pour les campagnes nationales et les besoins sur mesure',
            ],
        ],
        'rules' => [
            0 => 'Les offres sont réservées aux comptes annonceurs. Les comptes joueurs sont gratuits ou payants (voir Comptes) et n’achètent jamais d’offre.',
            1 => 'Les annonceurs paient par offre, pas au clic.',
            2 => 'Chaque offre comprend un nombre d’affichages et de questions ou manches sponsorisées. Seuls les affichages confirmés sont comptés.',
            3 => 'Chaque achat donne lieu à une facture, et l’annonceur peut consulter un relevé de sa consommation.',
            4 => 'Aucune publicité pendant qu’une question est ouverte. Pas de son, pas de vidéo, pas de fenêtre surgissante.',
        ],
    ],
    'packs.items' => '[TODO] Packs.items',
    'packs.price_placeholder' => '[TODO] Packs.price placeholder',
    'packs.rules' => '[TODO] Packs.rules',
    'packs.title' => '[TODO] Packs.title',
    'policy' => [
        'caption' => 'La politique annonceurs en un coup d’œil',
        'principle' => 'Principe',
        'accepted' => 'Nous acceptons',
        'refused' => 'Nous n’acceptons jamais',
        'rows' => [
            0 => [
                'principle' => 'Halal',
                'accepted' => [
                    0 => 'Produits et services halal',
                    1 => 'Alimentation certifiée halal',
                    2 => 'Finance éthique, sans intérêt',
                ],
                'refused' => [
                    0 => 'Alcool',
                    1 => 'Porc et viandes non halal',
                    2 => 'Jeux d’argent, paris et loteries',
                    3 => 'Crédits à intérêt et spéculation',
                    4 => 'Tabac, vapotage et drogues',
                ],
            ],
            1 => [
                'principle' => 'Respect de toutes les religions',
                'accepted' => [
                    0 => 'Messages respectueux de toutes les fois et croyances',
                    1 => 'Contenus culturels et patrimoniaux',
                ],
                'refused' => [
                    0 => 'Contenus qui moquent ou attaquent une religion, une croyance ou un symbole sacré',
                    1 => 'Propagande religieuse ou politique',
                ],
            ],
            2 => [
                'principle' => 'Humanité et dignité',
                'accepted' => [
                    0 => 'Messages inclusifs',
                    1 => 'Causes qui aident les gens et la planète',
                ],
                'refused' => [
                    0 => 'Haine, racisme ou discrimination',
                    1 => 'Violence et armes',
                    2 => 'Exploitation des personnes ou des animaux',
                ],
            ],
            3 => [
                'principle' => 'Adapté aux enfants',
                'accepted' => [
                    0 => 'Éducation, sciences, environnement, culture et sport',
                    1 => 'Mode de vie sain',
                ],
                'refused' => [
                    0 => 'Contenus pour adultes, sexuels ou de rencontre',
                    1 => 'Images effrayantes ou choquantes',
                    2 => 'Pression à l’achat ou astuces qui forcent le clic',
                    3 => 'Collecte de données personnelles d’enfants',
                ],
            ],
            4 => [
                'principle' => 'Honnêteté',
                'accepted' => [
                    0 => 'Affirmations vraies et vérifiables',
                    1 => 'Nom de l’annonceur clairement affiché',
                ],
                'refused' => [
                    0 => 'Affirmations fausses ou trompeuses',
                    1 => 'Promesses de santé non prouvées',
                    2 => 'Offres d’argent facile',
                ],
            ],
        ],
    ],
    'policy.accepted' => '[TODO] Policy.accepted',
    'policy.caption' => '[TODO] Policy.caption',
    'policy.principle' => '[TODO] Policy.principle',
    'policy.refused' => '[TODO] Policy.refused',
    'policy.rows' => '[TODO] Policy.rows',
    'sections' => [
        'service' => [
            'title' => 'Le service',
            'paragraphs' => [
                0 => 'Fakkroun est une plateforme de quiz multijoueur en arabe, français et anglais sur la connaissance, la culture, l’environnement et l’économie bleue et verte.',
                1 => 'Les parties, les scores et les réponses sont gérés et vérifiés par nos serveurs. Les résultats affichés en fin de partie sont définitifs.',
            ],
        ],
        'age' => [
            'title' => 'Qui peut jouer',
            'paragraphs' => [
                0 => 'Fakkroun propose du contenu de culture générale pour tous, à partir de :age ans.',
                1 => 'Les joueurs qui n’ont pas atteint l’âge du consentement numérique de leur pays ont besoin de l’accord d’un parent ou tuteur. En classe, l’enseignant qui anime la salle est responsable du groupe.',
            ],
        ],
        'accounts' => [
            'title' => 'Comptes',
            'paragraphs' => [
                0 => 'Un compte est nécessaire pour créer ou rejoindre une partie. Gardez votre mot de passe secret ; vous êtes responsable de l’usage de votre compte.',
                1 => 'Une salle créée par un compte payant est sans publicité pour tous ses joueurs, y compris les comptes gratuits.',
                2 => 'Il existe deux types de compte :',
            ],
        ],
        'fair_play' => [
            'title' => 'Fair-play et questions de la communauté',
            'paragraphs' => [
                0 => 'Jouez loyalement : pas de robots, pas de comptes partagés dans la même salle, aucune tentative de lire les réponses avant leur révélation.',
                1 => 'Les questions que vous proposez doivent être de vous, respectueuses et appuyées par une source fiable. Chaque question est validée par un modérateur avant d’être jouée. Nous pouvons modifier, refuser ou retirer des questions.',
            ],
        ],
        'advertising' => [
            'title' => 'Politique publicitaire',
            'paragraphs' => [
                0 => 'La publicité permet à Fakkroun de rester gratuit. Nous n’acceptons que des annonceurs dont les produits, services et messages sont halal, respectent toutes les religions et croyances, respectent la dignité humaine et conviennent aux enfants.',
                1 => 'Chaque publicité est vérifiée par une personne avant d’être montrée. Nous pouvons refuser, suspendre ou retirer à tout moment une publicité qui ne respecte pas cette politique.',
                2 => 'Les manquements à cette politique sont traités en trois étapes : le premier entraîne un avertissement écrit, le deuxième un appel de notre équipe, et le troisième la suspension du compte annonceur. Chaque manquement arrête la campagne concernée. Pendant la suspension, les crédits restants sont gelés.',
            ],
        ],
        'privacy' => [
            'title' => 'Vie privée',
            'paragraphs' => [
                0 => 'Les publicités sont choisies selon le thème et la langue de la partie, jamais en construisant un profil du joueur.',
                1 => 'Les annonceurs ne reçoivent jamais les données personnelles des joueurs. Ils ne voient que des totaux, par exemple le nombre d’affichages de leur publicité.',
            ],
        ],
        'changes' => [
            'title' => 'Modifications et contact',
            'paragraphs' => [
                0 => 'Nous pouvons mettre à jour ces conditions. Pour tout changement important, nous vous prévenons dans l’application avant son entrée en vigueur.',
                1 => 'Questions sur ces conditions : :email',
            ],
        ],
    ],
    'title' => 'Conditions d’utilisation',
    'updated' => 'Dernière mise à jour : :date',
];
