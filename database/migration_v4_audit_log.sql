-- =====================================================================
-- X_Academy — Migration v4
-- Ajoute la table du journal d'audit (traçabilité des actions sensibles).
-- À exécuter UNE FOIS sur une base déjà installée (onglet SQL de la base
-- x_academy dans phpMyAdmin).
-- =====================================================================

CREATE TABLE IF NOT EXISTS audit_logs (
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
