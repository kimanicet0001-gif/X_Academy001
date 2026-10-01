-- =====================================================================
-- X_Academy — Migration v5
-- Espace Étudiant, hiérarchie Filière, cycle de vie des séances
-- (Brouillon/Publié/Terminé/Annulé), notifications internes.
--
-- À exécuter UNE FOIS sur une base déjà installée, dans l'ordre indiqué
-- (onglet SQL de la base x_academy dans phpMyAdmin).
-- Ne supprime ni ne modifie aucune donnée existante.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Filières (nouveau niveau : Département > Filière > Promotion > Groupe)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS filieres (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id  INT UNSIGNED NOT NULL,
    name           VARCHAR(150) NOT NULL,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_filieres_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Rattache chaque promotion à une filière (nullable pour ne pas casser
-- les promotions déjà créées avant cette migration).
ALTER TABLE promotions
    ADD COLUMN IF NOT EXISTS filiere_id INT UNSIGNED NULL AFTER department_id;

ALTER TABLE promotions
    ADD CONSTRAINT fk_promotions_filiere FOREIGN KEY (filiere_id)
        REFERENCES filieres(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 2. Comptes étudiants (authentification directement sur la table students)
-- ---------------------------------------------------------------------
ALTER TABLE students
    ADD COLUMN IF NOT EXISTS username VARCHAR(60) NULL AFTER matricule,
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER username,
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash,
    ADD COLUMN IF NOT EXISTS profile_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER must_change_password,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER profile_completed,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER phone;

-- ATTENTION avant la ligne suivante : si des étudiants existants partagent
-- déjà le même matricule (ou plusieurs matricules vides "" au lieu de NULL),
-- cette contrainte va échouer. Vérifiez d'abord avec :
--   SELECT matricule, COUNT(*) FROM students
--   WHERE matricule IS NOT NULL AND matricule != ''
--   GROUP BY matricule HAVING COUNT(*) > 1;
-- Corrigez les doublons trouvés avant de continuer.
ALTER TABLE students
    ADD UNIQUE INDEX uq_students_username (username),
    ADD UNIQUE INDEX uq_students_matricule (matricule);

-- ---------------------------------------------------------------------
-- 3. Cycle de vie des séances (Brouillon / Publié / Terminé / Annulé)
-- ---------------------------------------------------------------------
ALTER TABLE sessions
    ADD COLUMN IF NOT EXISTS room VARCHAR(100) NULL AFTER topic,
    ADD COLUMN IF NOT EXISTS status ENUM('draft','published','done','cancelled') NOT NULL DEFAULT 'draft' AFTER room,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Les séances déjà existantes (créées avant cette migration, où l'appel
-- pouvait déjà avoir été fait) sont considérées "Terminées" pour ne pas
-- perdre les heures déjà comptabilisées dans les statistiques.
UPDATE sessions SET status = 'done' WHERE status = 'draft';

-- ---------------------------------------------------------------------
-- 4. Notifications internes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id   INT UNSIGNED NOT NULL,
    session_id   INT UNSIGNED NULL,
    type         VARCHAR(30) NOT NULL,
    message      VARCHAR(255) NOT NULL,
    link         VARCHAR(255) NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_student FOREIGN KEY (student_id)
        REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_session FOREIGN KEY (session_id)
        REFERENCES sessions(id) ON DELETE CASCADE,
    INDEX idx_notifications_student (student_id, is_read)
) ENGINE=InnoDB;
