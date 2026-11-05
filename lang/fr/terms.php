<?php

// Draft terms of use. Have them reviewed by a lawyer before launch.
return [
    'eyebrow' => 'Conditions générales',
    'title' => 'Conditions d’utilisation',
    'lead' => 'Les règles pour jouer sur Fakkroun, proposer des questions et faire de la publicité chez nous.',
    'updated' => 'Dernière mise à jour : :date',
    'draft_notice' => 'Brouillon : ces conditions sont en cours de relecture et peuvent changer avant le lancement.',
    'contents' => 'Sommaire',
    'sections' => [
        'service' => [
            'title' => 'Le service',
            'paragraphs' => [
                'Fakkroun est une plateforme de quiz multijoueur en arabe, français et anglais sur la connaissance, la culture, l’environnement et l’économie bleue et verte.',
                'Les parties, les scores et les réponses sont gérés et vérifiés par nos serveurs. Les résultats affichés en fin de partie sont définitifs.',
            ],
        ],
        'age' => [
            'title' => 'Qui peut jouer',
            'paragraphs' => [
                'Fakkroun propose du contenu de culture générale pour tous, à partir de :age ans.',
                'Les joueurs qui n’ont pas atteint l’âge du consentement numérique de leur pays ont besoin de l’accord d’un parent ou tuteur. En classe, l’enseignant qui anime la salle est responsable du groupe.',
            ],
        ],
        'accounts' => [
            'title' => 'Comptes',
            'paragraphs' => [
                'Un compte est nécessaire pour créer ou rejoindre une partie. Gardez votre mot de passe secret ; vous êtes responsable de l’usage de votre compte.',
                'Il existe deux types de compte :',
            ],
        ],
        'fair_play' => [
            'title' => 'Fair-play et questions de la communauté',
            'paragraphs' => [
                'Jouez loyalement : pas de robots, pas de comptes partagés dans la même salle, aucune tentative de lire les réponses avant leur révélation.',
                'Les questions que vous proposez doivent être de vous, respectueuses et appuyées par une source fiable. Chaque question est validée par un modérateur avant d’être jouée. Nous pouvons modifier, refuser ou retirer des questions.',
            ],
        ],
        'advertising' => [
            'title' => 'Politique publicitaire',
            'paragraphs' => [
                'La publicité permet à Fakkroun de rester gratuit. Nous n’acceptons que des annonceurs dont les produits, services et messages sont halal, respectent toutes les religions et croyances, respectent la dignité humaine et conviennent aux enfants.',
                'Chaque publicité est vérifiée par une personne avant d’être montrée. Nous pouvons refuser, suspendre ou retirer à tout moment une publicité qui ne respecte pas cette politique.',
            ],
        ],
        'privacy' => [
            'title' => 'Vie privée',
            'paragraphs' => [
                'Les publicités sont choisies selon le thème et la langue de la partie, jamais en construisant un profil du joueur.',
                'Les annonceurs ne reçoivent jamais les données personnelles des joueurs. Ils ne voient que des totaux, par exemple le nombre d’affichages de leur publicité.',
            ],
        ],
        'changes' => [
            'title' => 'Modifications et contact',
            'paragraphs' => [
                'Nous pouvons mettre à jour ces conditions. Pour tout changement important, nous vous prévenons dans l’application avant son entrée en vigueur.',
                'Questions sur ces conditions : :email',
            ],
        ],
    ],
    'account_types' => [
        [
            'name' => 'Compte gratuit',
            'points' => [
                'Tous les modes de jeu',
                'Proposer des questions',
                'Des publicités à des moments calmes, jamais pendant que vous répondez',
            ],
        ],
        [
            'name' => 'Compte payant (sans publicité)',
            'points' => [
                'Tout le compte gratuit',
                'Aucune publicité pendant le jeu',
                'Bientôt disponible : prix communiqué plus tard',
            ],
        ],
    ],
    'policy' => [
        'caption' => 'La politique annonceurs en un coup d’œil',
        'principle' => 'Principe',
        'accepted' => 'Nous acceptons',
        'refused' => 'Nous n’acceptons jamais',
        'rows' => [
            [
                'principle' => 'Halal',
                'accepted' => [
                    'Produits et services halal',
                    'Alimentation certifiée halal',
                    'Finance éthique, sans intérêt',
                ],
                'refused' => [
                    'Alcool',
                    'Porc et viandes non halal',
                    'Jeux d’argent, paris et loteries',
                    'Crédits à intérêt et spéculation',
                    'Tabac, vapotage et drogues',
                ],
            ],
            [
                'principle' => 'Respect de toutes les religions',
                'accepted' => [
                    'Messages respectueux de toutes les fois et croyances',
                    'Contenus culturels et patrimoniaux',
                ],
                'refused' => [
                    'Contenus qui moquent ou attaquent une religion, une croyance ou un symbole sacré',
                    'Propagande religieuse ou politique',
                ],
            ],
            [
                'principle' => 'Humanité et dignité',
                'accepted' => [
                    'Messages inclusifs',
                    'Causes qui aident les gens et la planète',
                ],
                'refused' => [
                    'Haine, racisme ou discrimination',
                    'Violence et armes',
                    'Exploitation des personnes ou des animaux',
                ],
            ],
            [
                'principle' => 'Adapté aux enfants',
                'accepted' => [
                    'Éducation, sciences, environnement, culture et sport',
                    'Mode de vie sain',
                ],
                'refused' => [
                    'Contenus pour adultes, sexuels ou de rencontre',
                    'Images effrayantes ou choquantes',
                    'Pression à l’achat ou astuces qui forcent le clic',
                    'Collecte de données personnelles d’enfants',
                ],
            ],
            [
                'principle' => 'Honnêteté',
                'accepted' => [
                    'Affirmations vraies et vérifiables',
                    'Nom de l’annonceur clairement affiché',
                ],
                'refused' => [
                    'Affirmations fausses ou trompeuses',
                    'Promesses de santé non prouvées',
                    'Offres d’argent facile',
                ],
            ],
        ],
    ],
    'formats' => [
        'title' => 'Où apparaissent les publicités',
        'format' => 'Format',
        'where' => 'Où',
        'counted' => 'Ce qui est compté',
        'rows' => [
            [
                'Bannière après une réponse',
                'Sous l’explication, une fois la question fermée',
                'Un affichage par joueur, seulement s’il a vraiment été à l’écran',
            ],
            [
                'Bannière des résultats',
                'Sur la page des résultats, après la partie',
                'Un affichage par joueur, seulement s’il a vraiment été à l’écran',
            ],
            [
                'Bannière de la salle d’attente',
                'Dans la salle d’attente, avant la partie',
                'Un affichage par joueur, seulement s’il a vraiment été à l’écran',
            ],
            [
                'Question sponsorisée',
                'Une question de la partie, validée comme les autres',
                'Une par salle qui la joue',
            ],
            [
                'Manche sponsorisée',
                'Une manche de questions sur un thème',
                'Une par salle qui la joue',
            ],
        ],
    ],
    'packs' => [
        'title' => 'Offres publicitaires',
        'price_placeholder' => 'Prix et contenu : [PACK DETAILS]',
        'items' => [
            [
                'name' => 'Essentiel',
                'for' => 'Pour les associations, écoles et petites structures',
            ],
            [
                'name' => 'Professionnel',
                'for' => 'Pour les entreprises et les campagnes plus larges',
            ],
            [
                'name' => 'Entreprise',
                'for' => 'Pour les campagnes nationales et les besoins sur mesure',
            ],
        ],
        'rules' => [
            'Les annonceurs paient par offre, pas au clic.',
            'Chaque offre comprend un nombre d’affichages et de questions ou manches sponsorisées. Seuls les affichages confirmés sont comptés.',
            'Chaque achat donne lieu à une facture, et l’annonceur peut consulter un relevé de sa consommation.',
            'Aucune publicité pendant qu’une question est ouverte. Pas de son, pas de vidéo, pas de fenêtre surgissante.',
        ],
    ],
];
