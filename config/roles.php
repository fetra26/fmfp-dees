<?php

/**
 * Définition centralisée des rôles et permissions de l'application FMFP-DEES.
 *
 * Cette configuration est consommée par le RolesAndPermissionsSeeder.
 * Toute évolution des droits passe par ce fichier — pas de hardcoding dans le code métier.
 *
 * Convention de nommage des permissions : "verbe.ressource[.scope]"
 *   ex : "projects.create", "payments.create", "projects.view.assigned"
 *
 * Source : Cahier des charges DEES, §1.3 Personas et §5.2 Sécurité.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    | Liste exhaustive des permissions du système, groupées par domaine métier.
    */

    'permissions' => [

        // --- Entreprises (épopée 1) ---
        'companies.view',
        'companies.create',
        'companies.update',
        'companies.delete',

        // --- Projets (épopée 2) ---
        'projects.view',
        'projects.create',
        'projects.update',
        'projects.delete',
        'projects.export',

        // --- Conventions et DANO (épopée 2) ---
        'conventions.manage',
        'danos.create',
        'danos.view',

        // --- Financements et paiements (épopée 3) - RÉSERVÉ DAF ---
        'financings.view',
        'financings.manage',
        'payments.view',
        'payments.create',
        'payments.update',
        'payments.delete',

        // --- Alertes et relances (épopée 4) ---
        'alerts.view',
        'reminders.create',
        'reminders.view',

        // --- Suivi terrain (épopée 5) ---
        'field_monitoring.view',
        'field_monitoring.create',
        'field_monitoring.update',

        // --- Rapport technique et évaluation (épopée 6) ---
        'reports.view',
        'reports.create',
        'reports.update',
        'reports.assign_evaluator',       // affecter un évaluateur (admin DEES)
        'reports.validate',               // valider un rapport (évaluateur interne)
        'reservations.create',            // formuler des réserves
        'reservations.view',

        // --- Prévisionnel et réalisation (épopée 7) ---
        'forecasts.manage',
        'realizations.manage',

        // --- Tableau de bord et statistiques (épopée 8) ---
        'dashboard.view',
        'statistics.view',
        'statistics.export',

        // --- Import / Export (épopée 9) ---
        'imports.execute',
        'exports.execute',

        // --- Administration système ---
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'roles.manage',
        'references.manage',              // référentiels : guichets, secteurs, régions, statuts
        'audit_log.view',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rôles
    |--------------------------------------------------------------------------
    | Pour chaque rôle, la liste des permissions accordées.
    | Utiliser '*' pour donner toutes les permissions (réservé à l'admin).
    */

    'roles' => [

        'admin' => [
            'label' => 'Super Administrateur',
            'description' => 'Accès total : gère utilisateurs, départements, rôles, référentiels et supervise les imports.',
            'permissions' => ['*'],
        ],

        'project_manager' => [
            'label' => 'Chargé de projet (DEES)',
            'description' => 'Saisit et met à jour les informations générales des projets, entreprises, suivi terrain et réalisation.',
            'permissions' => [
                'companies.view', 'companies.create', 'companies.update',
                'projects.view', 'projects.create', 'projects.update', 'projects.export',
                'conventions.manage', 'danos.create', 'danos.view',
                'financings.view',                 // lecture seule sur les données financières
                'payments.view',                   // lecture seule
                'alerts.view', 'reminders.create', 'reminders.view',
                'field_monitoring.view', 'field_monitoring.create', 'field_monitoring.update',
                'reports.view', 'reports.create', 'reports.update',
                'reservations.view',
                'forecasts.manage', 'realizations.manage',
                'dashboard.view', 'statistics.view',
                'exports.execute',
            ],
        ],

        'evaluateur' => [
            'label' => 'Évaluateur',
            'description' => 'Évalue les projets, formule les réserves, valide les rapports techniques.',
            'permissions' => [
                'companies.view',
                'projects.view',
                'danos.view',
                'financings.view',
                'payments.view',
                'alerts.view', 'reminders.view',
                'field_monitoring.view',
                'reports.view', 'reports.create', 'reports.update', 'reports.validate',
                'reservations.create', 'reservations.view',
                'forecasts.manage', 'realizations.manage',
                'dashboard.view', 'statistics.view',
            ],
        ],

        'daf' => [
            'label' => 'Équipe DAF',
            'description' => 'Responsable exclusif de la saisie et du contrôle des données financières.',
            'permissions' => [
                'companies.view',
                'projects.view',
                'danos.view',
                'financings.view', 'financings.manage',
                'payments.view', 'payments.create', 'payments.update', 'payments.delete',
                'alerts.view',
                'reports.view',
                'dashboard.view', 'statistics.view',
                'exports.execute',
            ],
        ],

        'direction' => [
            'label' => 'Direction',
            'description' => 'Consulte le tableau de bord et les statistiques. Pas de droit de saisie opérationnelle.',
            'permissions' => [
                'companies.view',
                'projects.view',
                'danos.view',
                'financings.view',
                'payments.view',
                'alerts.view', 'reminders.view',
                'field_monitoring.view',
                'reports.view',
                'reservations.view',
                'dashboard.view', 'statistics.view', 'statistics.export',
                'audit_log.view',
            ],
        ],
    ],
];
