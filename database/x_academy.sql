-- =====================================================================
-- X_Academy — Schéma de base de données
-- Journal académique numérique — suivi des enseignements et présences
-- =====================================================================

CREATE DATABASE IF NOT EXISTS x_academy
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE x_academy;

-- ---------------------------------------------------------------------
-- Départements
-- ---------------------------------------------------------------------
CREATE TABLE departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Utilisateurs (admin, chef de département, délégué)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(60) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    role            ENUM('admin','head','delegate') NOT NULL,
    department_id   INT UNSIGNED NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Filières (Département > Filière > Promotion > Groupe)
-- ---------------------------------------------------------------------
CREATE TABLE filieres (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id  INT UNSIGNED NOT NULL,
    name           VARCHAR(150) NOT NULL,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_filieres_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Promotions / groupes (créés par le Chef, assignés à un Délégué)
-- ---------------------------------------------------------------------
CREATE TABLE promotions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id   INT UNSIGNED NOT NULL,
    filiere_id      INT UNSIGNED NULL,
    delegate_id     INT UNSIGNED NULL,
    name            VARCHAR(100) NOT NULL,      -- ex: L3 SSR
    group_name      VARCHAR(50)  NULL,          -- ex: Groupe A
    academic_year   VARCHAR(20)  NOT NULL,      -- ex: 2026-2027
    semester        ENUM('S1','S2') NOT NULL DEFAULT 'S1',
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_promotions_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_promotions_filiere FOREIGN KEY (filiere_id)
        REFERENCES filieres(id) ON DELETE SET NULL,
    CONSTRAINT fk_promotions_delegate FOREIGN KEY (delegate_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Étudiants (avec leur propre compte de connexion)
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promotion_id          INT UNSIGNED NOT NULL,
    full_name             VARCHAR(150) NOT NULL,
    matricule             VARCHAR(50) NULL,
    username              VARCHAR(60) NULL,
    password_hash         VARCHAR(255) NULL,
    must_change_password  TINYINT(1) NOT NULL DEFAULT 1,
    profile_completed     TINYINT(1) NOT NULL DEFAULT 0,
    phone                 VARCHAR(30) NULL,
    email                 VARCHAR(150) NULL,
    status                ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_promotion FOREIGN KEY (promotion_id)
        REFERENCES promotions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_students_username (username),
    UNIQUE KEY uq_students_matricule (matricule)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Cours
-- ---------------------------------------------------------------------
CREATE TABLE courses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promotion_id    INT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    teacher_name    VARCHAR(150) NOT NULL,
    semester        ENUM('S1','S2') NOT NULL DEFAULT 'S1',
    planned_hours   DECIMAL(6,2) NOT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_promotion FOREIGN KEY (promotion_id)
        REFERENCES promotions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_course_unique (promotion_id, name, semester)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Séances — cycle de vie : draft (brouillon) -> published (publiée)
-- -> done (terminée, appel fait) -> cancelled (annulée)
-- Seules les séances "done" comptent dans les heures prestées.
-- ---------------------------------------------------------------------
CREATE TABLE sessions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       INT UNSIGNED NOT NULL,
    session_date    DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    duration_hours  DECIMAL(5,2) NOT NULL,
    topic           VARCHAR(255) NOT NULL,
    room            VARCHAR(100) NULL,
    status          ENUM('draft','published','done','cancelled') NOT NULL DEFAULT 'draft',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_course FOREIGN KEY (course_id)
        REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Présences (appel)
-- ---------------------------------------------------------------------
CREATE TABLE attendance (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id      INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    status          ENUM('present','absent') NOT NULL DEFAULT 'absent',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_session FOREIGN KEY (session_id)
        REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id)
        REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uq_attendance_session_student (session_id, student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Notifications internes (adressées aux étudiants)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
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

-- ---------------------------------------------------------------------
-- Paramètres de l'université (une seule ligne de configuration)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    id               INT UNSIGNED PRIMARY KEY DEFAULT 1,
    university_name  VARCHAR(200) NOT NULL DEFAULT 'Université',
    po_box           VARCHAR(100) NULL,
    city             VARCHAR(150) NULL,
    email            VARCHAR(150) NULL,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Journal d'audit — trace des actions sensibles
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    username    VARCHAR(60) NULL,
    role        VARCHAR(20) NULL,
    action      VARCHAR(100) NOT NULL,
    details     VARCHAR(500) NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_user (user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- Données de départ (seed)
-- =====================================================================

-- Compte administrateur par défaut
-- Identifiant : admin   /  Mot de passe : Admin@2026
-- (le hash ci-dessous correspond à ce mot de passe — à changer à la 1ère connexion)
INSERT INTO users (username, password_hash, full_name, role, department_id, status, must_change_password)
VALUES ('admin', '$2b$10$0xlZWDwlukx9nLwv44fRTuK/MqWG79X1TI/t.PolwdmwPTmQn9TpS', 'Administrateur Principal', 'admin', NULL, 'active', 1);

-- Un exemple de département
INSERT INTO departments (name, status) VALUES ('Télécommunications', 'active');

-- Configuration par défaut de l'université (modifiable depuis admin/settings.php)
INSERT INTO settings (id, university_name, po_box, city, email) VALUES (1, 'Université', NULL, NULL, NULL);
