-- =====================================================================
-- X_Academy — Migration v6
-- Module Professeur & Système de Notes
-- À exécuter après x_academy.sql (+ migrations v2, v4, v5 déjà appliquées)
-- =====================================================================

USE x_academy;

-- ---------------------------------------------------------------------
-- 1. Nouveau rôle PROFESSEUR (réutilise la table users existante —
--    aucune nouvelle table de compte, aucun changement de auth/login.php)
-- ---------------------------------------------------------------------
ALTER TABLE users
    MODIFY role ENUM('admin','head','delegate','professor') NOT NULL;
-- department_id reste NULL pour un professeur : il n'est jamais rattaché
-- à un seul département, ses accès passent uniquement par teaching_assignments.

-- ---------------------------------------------------------------------
-- 2. Unités d'Enseignement (UE) — regroupent plusieurs cours (ECUE)
--    d'une même promotion. Un cours existant devient l'équivalent ECUE.
-- ---------------------------------------------------------------------
CREATE TABLE ues (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promotion_id    INT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    code            VARCHAR(30)  NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ues_promotion FOREIGN KEY (promotion_id)
        REFERENCES promotions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_ue_unique (promotion_id, name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Cours = ECUE : ajout des crédits (base du calcul GPA/points
--    pondérés) et rattachement optionnel à une UE.
-- ---------------------------------------------------------------------
ALTER TABLE courses
    ADD COLUMN credits DECIMAL(4,1) NOT NULL DEFAULT 0 AFTER planned_hours,
    ADD COLUMN ue_id INT UNSIGNED NULL AFTER promotion_id;

ALTER TABLE courses
    ADD CONSTRAINT fk_courses_ue FOREIGN KEY (ue_id)
        REFERENCES ues(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 4. Affectations pédagogiques : un professeur -> un cours précis.
--    Département / filière / promotion / année / semestre se déduisent
--    tous par jointure depuis course_id (déjà scopé à une promotion) —
--    aucune colonne redondante, aucun risque d'incohérence.
-- ---------------------------------------------------------------------
CREATE TABLE teaching_assignments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    professor_id    INT UNSIGNED NOT NULL,
    course_id       INT UNSIGNED NOT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ta_professor FOREIGN KEY (professor_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ta_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uq_ta_unique (professor_id, course_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Notes des étudiants. La contrainte unique (student_id, course_id)
--    suffit à empêcher tout doublon étudiant + cours + année + semestre,
--    puisqu'un cours est déjà scopé à une seule promotion/semestre/année.
-- ---------------------------------------------------------------------
CREATE TABLE student_grades (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id          INT UNSIGNED NOT NULL,
    course_id           INT UNSIGNED NOT NULL,
    score               DECIMAL(5,2) NOT NULL,   -- 0 à 100
    gpa                 DECIMAL(3,2) NULL,        -- dérivé : score / 25, plafonné à 4.00
    weighted_points     DECIMAL(6,2) NULL,        -- dérivé : gpa * credits
    grade               VARCHAR(5)  NULL,         -- dérivé : lettre selon grading_scales
    status              ENUM('draft','submitted','locked') NOT NULL DEFAULT 'draft',
    submitted_by        INT UNSIGNED NULL,        -- professeur ayant soumis
    submitted_at        DATETIME NULL,
    locked_at           DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sg_student FOREIGN KEY (student_id)
        REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_sg_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_sg_submitted_by FOREIGN KEY (submitted_by)
        REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_sg_unique (student_id, course_id),
    CONSTRAINT chk_sg_score CHECK (score >= 0 AND score <= 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Historique des modifications de notes (après verrouillage).
-- ---------------------------------------------------------------------
CREATE TABLE grade_change_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grade_id    INT UNSIGNED NOT NULL,
    old_score   DECIMAL(5,2) NOT NULL,
    new_score   DECIMAL(5,2) NOT NULL,
    changed_by  INT UNSIGNED NULL,
    reason      VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_gch_grade FOREIGN KEY (grade_id)
        REFERENCES student_grades(id) ON DELETE CASCADE,
    CONSTRAINT fk_gch_changed_by FOREIGN KEY (changed_by)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gch_grade (grade_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. Barème note -> lettre, configurable (NE PAS considérer ces valeurs
--    comme définitives : à confirmer avec la direction académique —
--    voir section "Points à valider" du cahier des charges).
-- ---------------------------------------------------------------------
CREATE TABLE grading_scales (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    min_score   DECIMAL(5,2) NOT NULL,
    max_score   DECIMAL(5,2) NOT NULL,
    grade       VARCHAR(5) NOT NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO grading_scales (min_score, max_score, grade, active) VALUES
    (90, 100, 'A',  1),
    (85, 89.99, 'A-', 1),
    (80, 84.99, 'B+', 1),
    (75, 79.99, 'B',  1),
    (70, 74.99, 'B-', 1),
    (65, 69.99, 'C+', 1),
    (60, 64.99, 'C',  1),
    (55, 59.99, 'C-', 1),
    (50, 54.99, 'D',  1),
    (0,  49.99, 'F',  1);

-- ---------------------------------------------------------------------
-- 8. Seuil de validation d'UE (UV / UNV), configurable depuis
--    admin/settings.php. Valeur par défaut à confirmer.
-- ---------------------------------------------------------------------
ALTER TABLE settings
    ADD COLUMN ue_validation_threshold DECIMAL(5,2) NOT NULL DEFAULT 60.00 AFTER email;

-- ---------------------------------------------------------------------
-- 9. Notifications : généralisées pour pouvoir aussi notifier un
--    professeur (ou plus tard un chef), en plus des étudiants.
-- ---------------------------------------------------------------------
ALTER TABLE notifications
    MODIFY student_id INT UNSIGNED NULL,
    ADD COLUMN user_id INT UNSIGNED NULL AFTER student_id,
    ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    ADD INDEX idx_notifications_user (user_id, is_read);
