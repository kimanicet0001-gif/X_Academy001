-- =====================================================================
-- X_Academy — Migration v7
-- Complète le profil étudiant : identité, naissance, adresses,
-- nationalité (phone et email existaient déjà depuis la migration v5).
-- =====================================================================

USE x_academy;

ALTER TABLE students
    ADD COLUMN last_name        VARCHAR(80)  NULL AFTER full_name,
    ADD COLUMN first_name       VARCHAR(80)  NULL AFTER last_name,
    ADD COLUMN birth_date       DATE         NULL AFTER first_name,
    ADD COLUMN nationality      VARCHAR(80)  NULL AFTER birth_date,
    ADD COLUMN current_address  VARCHAR(255) NULL AFTER phone,
    ADD COLUMN home_address     VARCHAR(255) NULL AFTER current_address;

-- full_name reste la colonne d'affichage (listes, PDF, recherche) ; elle
-- est automatiquement recalculée à partir de last_name + first_name dès
-- que l'étudiant complète ou modifie son profil (voir complete_profile.php).
