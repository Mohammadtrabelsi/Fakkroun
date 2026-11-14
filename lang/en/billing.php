<?php

return [
    'cash_instructions' => 'Payment is in cash for now. Pay a member of the Fakkroun team; your paid account starts as soon as they record the payment.',
    'plans' => [
        'title' => 'Paid accounts',
        'description' => 'Users with a paid, ad-free account. Payment is in cash for now: record each cash payment here. Advertiser packs are separate (Advertising).',
        'price' => 'Current price: :price per month',
        'not_on_sale' => 'The paid account is not on sale: set its price in Game settings → Accounts.',
        'email' => 'User e-mail',
        'months' => 'Months',
        'receipt' => 'Receipt number',
        'amount' => 'Amount received (minor units)',
        'record' => 'Record cash payment',
        'recorded' => 'Cash payment recorded. Ad-free until :date.',
        'user' => 'User',
        'until' => 'Ad-free until',
        'cancel' => 'End paid account',
        'cancelled' => 'Paid account ended.',
        'none' => 'No paid accounts yet.',
        'unknown_user' => 'No user with this e-mail.',
    ],
    'errors' => [
        'plan_not_available' => 'The paid account is not on sale yet.',
        'not_pending' => 'This payment is no longer pending.',
        'duplicate_reference' => 'This receipt number was already recorded.',
    ],
];
