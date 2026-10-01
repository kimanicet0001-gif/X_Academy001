-- =====================================================================
-- X_Academy — Migration v2
-- À exécuter UNE FOIS sur une base déjà installée (ne touche à rien
-- d'existant, ajoute seulement la table des paramètres université).
-- Dans phpMyAdmin : onglet SQL de la base x_academy, coller et exécuter.
-- =====================================================================

CREATE TABLE IF NOT EXISTS settings (
    id               INT UNSIGNED PRIMARY KEY DEFAULT 1,
    university_name  VARCHAR(200) NOT NULL DEFAULT 'Université',
    po_box           VARCHAR(100) NULL,
    city             VARCHAR(150) NULL,
    email            VARCHAR(150) NULL,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (id, university_name, po_box, city, email)
VALUES (1, 'Université', NULL, NULL, NULL);
