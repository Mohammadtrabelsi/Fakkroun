<?php

// Draft terms of use. Have them reviewed by a lawyer before launch.
return [
    'eyebrow' => 'General terms',
    'title' => 'Terms of use',
    'lead' => 'The rules for playing on Fakkroun, contributing questions and advertising with us.',
    'updated' => 'Last updated: :date',
    'draft_notice' => 'Draft: these terms are being reviewed and may change before launch.',
    'contents' => 'Contents',
    'sections' => [
        'service' => [
            'title' => 'The service',
            'paragraphs' => [
                'Fakkroun is a multiplayer quiz platform in Arabic, French and English about knowledge, culture, the environment and the blue and green economy.',
                'Games, scores and answers are run and checked by our servers. Results shown at the end of a game are final.',
            ],
        ],
        'age' => [
            'title' => 'Who can play',
            'paragraphs' => [
                'Fakkroun is general-culture content for everyone aged :age and over.',
                'Players who are under the age of digital consent in their country need permission from a parent or guardian. In a class, the teacher who runs the room is responsible for the group.',
            ],
        ],
        'accounts' => [
            'title' => 'Accounts',
            'paragraphs' => [
                'You need an account to host or join a game. Keep your password private; you are responsible for what happens with your account.',
                'There are two kinds of account:',
            ],
        ],
        'fair_play' => [
            'title' => 'Fair play and community questions',
            'paragraphs' => [
                'Play fairly: no bots, no shared accounts in the same room, and no attempts to read answers before they are revealed.',
                'Questions you submit must be your own work, respectful and supported by a reliable source. Every question is reviewed by a moderator before anyone plays it. We may edit, refuse or remove questions.',
            ],
        ],
        'advertising' => [
            'title' => 'Advertising policy',
            'paragraphs' => [
                'Ads help keep Fakkroun free. We only accept advertisers whose products, services and messages are halal, respect every religion and belief, respect human dignity, and are suitable for children.',
                'Every ad is checked by a person before any player sees it. We may refuse, pause or remove any ad that does not follow this policy, at any time.',
            ],
        ],
        'privacy' => [
            'title' => 'Privacy',
            'paragraphs' => [
                'Ads are chosen by the topic and language of the game, never by building a profile of a player.',
                'Advertisers never receive players\' personal data. They only see totals, such as how many times their ad was shown.',
            ],
        ],
        'changes' => [
            'title' => 'Changes and contact',
            'paragraphs' => [
                'We may update these terms. When the changes are important, we will tell you in the app before they apply.',
                'Questions about these terms: :email',
            ],
        ],
    ],
    'account_types' => [
        [
            'name' => 'Free account',
            'points' => [
                'Play every game mode',
                'Submit questions',
                'Ads are shown at calm moments, never while you answer',
            ],
        ],
        [
            'name' => 'Paid account (ad-free)',
            'points' => [
                'Everything in the free account',
                'No ads while you play',
                'Coming soon: price to be announced',
            ],
        ],
    ],
    'policy' => [
        'caption' => 'Advertiser policy at a glance',
        'principle' => 'Principle',
        'accepted' => 'We accept',
        'refused' => 'We never accept',
        'rows' => [
            [
                'principle' => 'Halal',
                'accepted' => [
                    'Halal products and services',
                    'Halal-certified food',
                    'Ethical, interest-free finance',
                ],
                'refused' => [
                    'Alcohol',
                    'Pork and non-halal meat products',
                    'Gambling, betting and lotteries',
                    'Interest-based loans and speculative trading',
                    'Tobacco, vaping and drugs',
                ],
            ],
            [
                'principle' => 'Respect for every religion',
                'accepted' => [
                    'Messages that respect all faiths and beliefs',
                    'Cultural and heritage content',
                ],
                'refused' => [
                    'Content that mocks or attacks any religion, belief or sacred symbol',
                    'Religious or political campaigning',
                ],
            ],
            [
                'principle' => 'Humanity and dignity',
                'accepted' => [
                    'Inclusive messages',
                    'Causes that help people and the planet',
                ],
                'refused' => [
                    'Hate, racism or discrimination',
                    'Violence and weapons',
                    'Exploitation of people or animals',
                ],
            ],
            [
                'principle' => 'Kids-friendly',
                'accepted' => [
                    'Education, science, environment, culture and sport',
                    'Healthy living',
                ],
                'refused' => [
                    'Adult, sexual or dating content',
                    'Frightening or shocking images',
                    'Pressure to buy, or tricks that push a click',
                    'Collecting children\'s personal data',
                ],
            ],
            [
                'principle' => 'Honesty',
                'accepted' => [
                    'True, checkable claims',
                    'The advertiser\'s name shown clearly',
                ],
                'refused' => [
                    'False or misleading claims',
                    'Unproven health promises',
                    'Get-rich-quick offers',
                ],
            ],
        ],
    ],
    'formats' => [
        'title' => 'Where ads appear',
        'format' => 'Format',
        'where' => 'Where',
        'counted' => 'What is counted',
        'rows' => [
            [
                'Banner after an answer',
                'Under the answer explanation, once the question is closed',
                'One display per player, only once it was really on screen',
            ],
            [
                'Banner on results',
                'On the results page, after the game',
                'One display per player, only once it was really on screen',
            ],
            [
                'Lobby banner',
                'In the waiting room, before the game starts',
                'One display per player, only once it was really on screen',
            ],
            [
                'Sponsored question',
                'One question of the game, reviewed like any other',
                'One per room that plays it',
            ],
            [
                'Sponsored round',
                'A themed round of questions',
                'One per room that plays it',
            ],
        ],
    ],
    'packs' => [
        'title' => 'Advertising packs',
        'price_placeholder' => 'Price and contents: [PACK DETAILS]',
        'items' => [
            [
                'name' => 'Essential',
                'for' => 'For associations, schools and small organisations',
            ],
            [
                'name' => 'Professional',
                'for' => 'For companies and larger campaigns',
            ],
            [
                'name' => 'Enterprise',
                'for' => 'For national campaigns and custom needs',
            ],
        ],
        'rules' => [
            'Advertisers pay per pack, not per click.',
            'Each pack contains a number of ad displays and sponsored questions or rounds. Only confirmed displays are counted.',
            'Every purchase gets an invoice, and advertisers can see a statement of what was used.',
            'Ads are never shown while a question is open. No sound, no video and no pop-ups.',
        ],
    ],
];
