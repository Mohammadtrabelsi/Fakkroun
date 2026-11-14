<?php

return [
    'account_types' => [
        0 => [
            'name' => 'Free account',
            'points' => [
                0 => 'Play every game mode',
                1 => 'Submit questions',
                2 => 'Ads are shown at calm moments, never while you answer',
            ],
        ],
        1 => [
            'name' => 'Paid account (ad-free)',
            'points' => [
                0 => 'Everything in the free account',
                1 => 'No ads while you play',
                2 => 'Rooms you host are ad-free for everyone',
                3 => 'Paid in cash for now; other payment methods later',
            ],
        ],
    ],
    'contents' => 'Contents',
    'draft_notice' => 'Draft: these terms are being reviewed and may change before launch.',
    'eyebrow' => 'General terms',
    'formats' => [
        'title' => 'Where ads appear',
        'format' => 'Format',
        'where' => 'Where',
        'counted' => 'What is counted',
        'rows' => [
            0 => [
                0 => 'Banner after an answer',
                1 => 'Under the answer explanation, once the question is closed',
                2 => 'One display per player, only once it was really on screen',
            ],
            1 => [
                0 => 'Banner on results',
                1 => 'On the results page, after the game',
                2 => 'One display per player, only once it was really on screen',
            ],
            2 => [
                0 => 'Lobby banner',
                1 => 'In the waiting room, before the game starts',
                2 => 'One display per player, only once it was really on screen',
            ],
            3 => [
                0 => 'Sponsored question',
                1 => 'One question of the game, reviewed like any other',
                2 => 'One per room that plays it',
            ],
            4 => [
                0 => 'Sponsored round',
                1 => 'A themed round of questions',
                2 => 'One per room that plays it',
            ],
        ],
    ],
    'formats.counted' => 'Formats.counted',
    'formats.format' => 'Formats.format',
    'formats.rows' => 'Formats.rows',
    'formats.title' => 'Formats.title',
    'formats.where' => 'Formats.where',
    'lead' => 'The rules for playing on Fakkroun, contributing questions and advertising with us.',
    'packs' => [
        'title' => 'Advertising packs',
        'price_placeholder' => 'Price and contents: [PACK DETAILS]',
        'items' => [
            0 => [
                'name' => 'Essential',
                'for' => 'For associations, schools and small organisations',
            ],
            1 => [
                'name' => 'Professional',
                'for' => 'For companies and larger campaigns',
            ],
            2 => [
                'name' => 'Enterprise',
                'for' => 'For national campaigns and custom needs',
            ],
        ],
        'rules' => [
            0 => 'Packs are for advertiser accounts only. Player accounts are free or paid (see Accounts) and never buy packs.',
            1 => 'Advertisers pay per pack, not per click.',
            2 => 'Each pack contains a number of ad displays and sponsored questions or rounds. Only confirmed displays are counted.',
            3 => 'Every purchase gets an invoice, and advertisers can see a statement of what was used.',
            4 => 'Ads are never shown while a question is open. No sound, no video and no pop-ups.',
        ],
    ],
    'packs.items' => 'Packs.items',
    'packs.price_placeholder' => 'Packs.price placeholder',
    'packs.rules' => 'Packs.rules',
    'packs.title' => 'Packs.title',
    'policy' => [
        'caption' => 'Advertiser policy at a glance',
        'principle' => 'Principle',
        'accepted' => 'We accept',
        'refused' => 'We never accept',
        'rows' => [
            0 => [
                'principle' => 'Halal',
                'accepted' => [
                    0 => 'Halal products and services',
                    1 => 'Halal-certified food',
                    2 => 'Ethical, interest-free finance',
                ],
                'refused' => [
                    0 => 'Alcohol',
                    1 => 'Pork and non-halal meat products',
                    2 => 'Gambling, betting and lotteries',
                    3 => 'Interest-based loans and speculative trading',
                    4 => 'Tobacco, vaping and drugs',
                ],
            ],
            1 => [
                'principle' => 'Respect for every religion',
                'accepted' => [
                    0 => 'Messages that respect all faiths and beliefs',
                    1 => 'Cultural and heritage content',
                ],
                'refused' => [
                    0 => 'Content that mocks or attacks any religion, belief or sacred symbol',
                    1 => 'Religious or political campaigning',
                ],
            ],
            2 => [
                'principle' => 'Humanity and dignity',
                'accepted' => [
                    0 => 'Inclusive messages',
                    1 => 'Causes that help people and the planet',
                ],
                'refused' => [
                    0 => 'Hate, racism or discrimination',
                    1 => 'Violence and weapons',
                    2 => 'Exploitation of people or animals',
                ],
            ],
            3 => [
                'principle' => 'Kids-friendly',
                'accepted' => [
                    0 => 'Education, science, environment, culture and sport',
                    1 => 'Healthy living',
                ],
                'refused' => [
                    0 => 'Adult, sexual or dating content',
                    1 => 'Frightening or shocking images',
                    2 => 'Pressure to buy, or tricks that push a click',
                    3 => 'Collecting children\'s personal data',
                ],
            ],
            4 => [
                'principle' => 'Honesty',
                'accepted' => [
                    0 => 'True, checkable claims',
                    1 => 'The advertiser\'s name shown clearly',
                ],
                'refused' => [
                    0 => 'False or misleading claims',
                    1 => 'Unproven health promises',
                    2 => 'Get-rich-quick offers',
                ],
            ],
        ],
    ],
    'policy.accepted' => 'Policy.accepted',
    'policy.caption' => 'Policy.caption',
    'policy.principle' => 'Policy.principle',
    'policy.refused' => 'Policy.refused',
    'policy.rows' => 'Policy.rows',
    'sections' => [
        'service' => [
            'title' => 'The service',
            'paragraphs' => [
                0 => 'Fakkroun is a multiplayer quiz platform in Arabic, French and English about knowledge, culture, the environment and the blue and green economy.',
                1 => 'Games, scores and answers are run and checked by our servers. Results shown at the end of a game are final.',
            ],
        ],
        'age' => [
            'title' => 'Who can play',
            'paragraphs' => [
                0 => 'Fakkroun is general-culture content for everyone aged :age and over.',
                1 => 'Players who are under the age of digital consent in their country need permission from a parent or guardian. In a class, the teacher who runs the room is responsible for the group.',
            ],
        ],
        'accounts' => [
            'title' => 'Accounts',
            'paragraphs' => [
                0 => 'You need an account to host or join a game. Keep your password private; you are responsible for what happens with your account.',
                1 => 'A room hosted by a paid account is ad-free for every player in it, including free accounts.',
                2 => 'There are two kinds of account:',
            ],
        ],
        'fair_play' => [
            'title' => 'Fair play and community questions',
            'paragraphs' => [
                0 => 'Play fairly: no bots, no shared accounts in the same room, and no attempts to read answers before they are revealed.',
                1 => 'Questions you submit must be your own work, respectful and supported by a reliable source. Every question is reviewed by a moderator before anyone plays it. We may edit, refuse or remove questions.',
            ],
        ],
        'advertising' => [
            'title' => 'Advertising policy',
            'paragraphs' => [
                0 => 'Ads help keep Fakkroun free. We only accept advertisers whose products, services and messages are halal, respect every religion and belief, respect human dignity, and are suitable for children.',
                1 => 'Every ad is checked by a person before any player sees it. We may refuse, pause or remove any ad that does not follow this policy, at any time.',
                2 => 'Breaches of this policy are handled in three steps: the first breach brings a written warning, the second a call from our team, and the third suspends the advertiser account. Each breach stops the campaign concerned. While an account is suspended, its remaining credits are frozen.',
            ],
        ],
        'privacy' => [
            'title' => 'Privacy',
            'paragraphs' => [
                0 => 'Ads are chosen by the topic and language of the game, never by building a profile of a player.',
                1 => 'Advertisers never receive players\' personal data. They only see totals, such as how many times their ad was shown.',
            ],
        ],
        'changes' => [
            'title' => 'Changes and contact',
            'paragraphs' => [
                0 => 'We may update these terms. When the changes are important, we will tell you in the app before they apply.',
                1 => 'Questions about these terms: :email',
            ],
        ],
    ],
    'title' => 'Terms of use',
    'updated' => 'Last updated: :date',
];
