<?php

return [
    'cash_instructions' => 'Le paiement se fait en espèces pour le moment. Payez un membre de l’équipe Fakkroun ; votre compte payant démarre dès qu’il enregistre le paiement.',
    'plans' => [
        'title' => 'Comptes payants',
        'description' => 'Utilisateurs avec un compte payant sans publicité. Le paiement se fait en espèces pour le moment : enregistrez ici chaque paiement. Les offres annonceurs sont à part (Publicité).',
        'price' => 'Prix actuel : :price par mois',
        'not_on_sale' => 'Le compte payant n’est pas en vente : fixez son prix dans Paramètres du jeu → Comptes.',
        'email' => 'E-mail de l’utilisateur',
        'months' => 'Mois',
        'receipt' => 'Numéro de reçu',
        'amount' => 'Montant reçu (unités mineures)',
        'record' => 'Enregistrer le paiement en espèces',
        'recorded' => 'Paiement enregistré. Sans publicité jusqu’au :date.',
        'user' => 'Utilisateur',
        'until' => 'Sans publicité jusqu’au',
        'cancel' => 'Mettre fin au compte payant',
        'cancelled' => 'Compte payant terminé.',
        'none' => 'Aucun compte payant pour le moment.',
        'unknown_user' => 'Aucun utilisateur avec cet e-mail.',
    ],
    'errors' => [
        'plan_not_available' => 'Le compte payant n’est pas encore en vente.',
        'not_pending' => 'Ce paiement n’est plus en attente.',
        'duplicate_reference' => 'Ce numéro de reçu est déjà enregistré.',
    ],
];
