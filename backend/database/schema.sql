-- ============================================================
-- CineNova — posterg database schema
-- ============================================================
-- Mirrors the data currently held in backend/data/*.json, with
-- proper relational structure: movies.category_id and
-- movies.country_id are real foreign keys (the JSON store matched
-- these by free-text name); a movie's embed/upload/VidSrc sources
-- become their own child table (movie_sources) instead of a JSON
-- dict, so a title can have any number of sources with a real
-- primary key per row.
--
-- Run with:  mysql -u root posterg < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- users — unified account store (both roles: 'admin' and 'user')
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(20)  NOT NULL,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    plan            ENUM('Free', 'Standard', 'Premium') NOT NULL DEFAULT 'Free',
    status          ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    joined_at       DATE NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- categories — genres/categories, referenced by movies.category_id
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(120) NOT NULL,
    description     VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- countries — regions/availability list, referenced by movies.country_id
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS countries (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    code            CHAR(2) NOT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_countries_name (name),
    UNIQUE KEY uq_countries_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- movies — one row per title (curated TMDB match or fully custom
-- upload). A NULL tmdb_id + source='local' means CineNova has no
-- real TMDB match for this title; category_id/country_id are real
-- foreign keys instead of the JSON store's free-text name match.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movies (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(160) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    tmdb_id         BIGINT UNSIGNED NULL,
    source          ENUM('tmdb', 'local') NOT NULL DEFAULT 'tmdb',
    media_type      ENUM('movie', 'tv') NOT NULL DEFAULT 'movie',
    category_id     INT UNSIGNED NULL,
    country_id      INT UNSIGNED NULL,
    year            YEAR NULL,
    description     TEXT NULL,
    poster_url      VARCHAR(500) NULL,
    rating          DECIMAL(3,1) NOT NULL DEFAULT 0.0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_movies_slug (slug),
    KEY idx_movies_tmdb (tmdb_id, media_type),
    KEY idx_movies_category (category_id),
    KEY idx_movies_country (country_id),
    KEY idx_movies_title (title),
    CONSTRAINT fk_movies_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_movies_country  FOREIGN KEY (country_id)  REFERENCES countries (id)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- movie_sources — a movie's playable sources (what the JSON store
-- kept as a flat "episodes" dict). One row per part/episode/source,
-- each independently a custom embed link, a locally uploaded file,
-- or a VidSrc URL generated from the movie's tmdb_id.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movie_sources (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    movie_id        INT UNSIGNED NOT NULL,
    label           VARCHAR(100) NOT NULL,
    url             VARCHAR(500) NOT NULL,
    source_type     ENUM('embed', 'upload', 'vidsrc') NOT NULL DEFAULT 'embed',
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sources_movie (movie_id),
    CONSTRAINT fk_sources_movie FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- settings — single-row site configuration
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id                  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    site_name           VARCHAR(100) NOT NULL DEFAULT 'CineNova',
    site_tagline        VARCHAR(255) NULL,
    site_description    TEXT NULL,
    support_email       VARCHAR(190) NULL,
    maintenance_mode    TINYINT(1) NOT NULL DEFAULT 0,
    social_twitter      VARCHAR(255) NULL,
    social_facebook     VARCHAR(255) NULL,
    social_instagram    VARCHAR(255) NULL,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- visits — lightweight, privacy-conscious page-visit log
-- (matches backend/includes/analytics.php's existing fields)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visits (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page            VARCHAR(255) NOT NULL,
    query_string    VARCHAR(255) NULL,
    ip_hash         CHAR(16) NULL,
    user_agent      VARCHAR(255) NULL,
    referrer        VARCHAR(255) NULL,
    visited_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_visits_page (page(50)),
    KEY idx_visits_visited_at (visited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
