<?php
// db.php - koneksi database
$host = '127.0.0.1';
$db   = 'x';
$user = 'x';
$pass = 'x';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/**
 * Table ini fokus ke:
 * Login
 * Role & verifikasi
 * Keamanan
 * Informasi penting yang sering dipakai query
**/
$pdo->query("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,                         -- ID user
    username VARCHAR(100) NOT NULL UNIQUE,                     -- Username unik
    email VARCHAR(150) NOT NULL UNIQUE,                        -- Email unik
    password VARCHAR(255) NOT NULL,                            -- Password hashed

    role ENUM('reader','creator','admin') 
        NOT NULL DEFAULT 'reader',                             -- Role user

    -- Keamanan & Verifikasi
    email_verified_at DATETIME NULL,                           -- Waktu verifikasi email
    signup_ip VARCHAR(45) NULL,                                -- IP saat mendaftar
    last_ip VARCHAR(45) NULL,                                  -- IP login terakhir
    is_active TINYINT(1) NOT NULL DEFAULT 1,                   -- 1=aktif, 0=nonaktif
    banned_until DATETIME NULL,                                -- Banned sementara hingga tanggal ini

    -- VIP
    vip_until DATETIME NULL DEFAULT NULL,                      -- VIP berlaku hingga

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,            -- Waktu akun dibuat
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP 
        ON UPDATE CURRENT_TIMESTAMP,                           -- Waktu update akun
    last_login DATETIME NULL DEFAULT NULL                     -- Aktivitas login terakhir
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");

$pdo->query("
CREATE TABLE IF NOT EXISTS user_tokens (
    user_id INT NOT NULL UNIQUE,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NULL DEFAULT NULL, -- UNIX timestamp = lebih cepat & simpel
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


");

/**
 * Table ini fokus ke:
 * Data estetik
 * Data opsional
 * Semua info yang jarang dipakai query berat
 * Dapat banyak berubah tanpa ganggu tabel users
**/

$pdo->query("
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT PRIMARY KEY,                                   -- Sama dengan users.id

    display_name VARCHAR(150) NULL,                            -- Nama tampilan
    avatar_url VARCHAR(255) NULL,                              -- Foto profil
    banner_url VARCHAR(255) NULL,                              -- Banner profil
    bio TEXT NULL,                                             -- Tentang user

    gender ENUM('male','female','other') NULL,                 -- Opsional
    birthday DATE NULL,                                        -- Opsional
    website VARCHAR(255) NULL,                                 -- Link website
    location VARCHAR(150) NULL,                                -- Lokasi opsional

    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,             -- Profil dibuat
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,                           -- Update profil

    CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE                 -- Hapus profil jika user hilang
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");

/**
 * Statistik yang sering dibaca sistem
 * Counter aktivitas
 * Data untuk leaderboard atau profil ringkas
**/

$pdo->query("
CREATE TABLE IF NOT EXISTS user_summaries (
    user_id INT PRIMARY KEY,                                   -- Relasi 1:1 ke users.id
    
    -- Gamifikasi
    level INT NOT NULL DEFAULT 1,                              -- Level user
    exp INT NOT NULL DEFAULT 0,                                -- EXP untuk naik level
    reputation INT NOT NULL DEFAULT 0,                         -- Reputasi dari komunitas

    -- Statistik aktivitas
    total_likes INT DEFAULT 0,                                -- Total like diterima (creator)
    total_series INT DEFAULT 0,                                -- Total seri dibuat (creator)
    total_chapters INT DEFAULT 0,                              -- Total chapter dibuat
    total_reviews INT DEFAULT 0,                               -- Berapa review ditulis
    total_comments INT DEFAULT 0,                              -- Komentar ditulis
    total_bookmarks INT DEFAULT 0,                             -- Bookmark seri
    total_following INT DEFAULT 0,                             -- User yang dia follow
    total_followers INT DEFAULT 0,                             -- Yang follow dia
    total_guestbook INT DEFAULT 0,                             -- Total pesan guestbook

    last_activity DATETIME NULL DEFAULT NULL,                 -- Aktivitas terakhir

    CONSTRAINT fk_user_summaries_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE                 -- Auto hapus bila user dihapus
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");


$pdo->query("
	CREATE TABLE IF NOT EXISTS entities (
		id INT AUTO_INCREMENT PRIMARY KEY,
		type VARCHAR(50) NOT NULL,
		name VARCHAR(255) NOT NULL,
		slug VARCHAR(255) NOT NULL,
		description TEXT NULL,
		extra_data JSON NULL,
		is_visible TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

		UNIQUE KEY uniq_type_slug_name (type, slug),
		KEY idx_type (type),
		FULLTEXT KEY ft_name (name)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");

/*
*/
$pdo->query("
CREATE TABLE IF NOT EXISTS entity_summary (
    entity_id INT PRIMARY KEY,               -- mirror ke entities.id
    type VARCHAR(50) NOT NULL,               -- cache dari entities
    slug VARCHAR(255) NOT NULL,              -- cache dari entities
    name VARCHAR(255) NOT NULL,              -- cache dari entities.name

    series_count INT DEFAULT 0,              -- jumlah series yang memakai entity
    popularity_score INT DEFAULT 0,          -- optional
    last_used_at DATETIME NULL,              -- optional: kapan entity terakhir dipakai

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_type_series_count (type, series_count),
    FULLTEXT KEY ft_name (name),
    FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");


$pdo->query("
CREATE TABLE IF NOT EXISTS series (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- is_published 0 = draft, 1 = published
    is_published TINYINT DEFAULT 0,

    -- URL unique identifier
    slug VARCHAR(255) NOT NULL UNIQUE,

    -- Judul / nama series
    name VARCHAR(255) NOT NULL,

    -- Deskripsi lengkap
    description TEXT,

    -- URL/path gambar cover
    cover_url VARCHAR(255),

    -- Rating konten: 0=normal, 1=mature, dll
    content_rating TINYINT DEFAULT 0,

    -- Apakah mengandung konten dewasa (filter)
    is_nsfw TINYINT(1) DEFAULT 0,

    -- Admin / user yang membuat
    created_by INT NULL,

    -- Admin / user yang terakhir mengupdate
    updated_by INT NULL,

    -- Tanggal pertama kali dipublish
    published_at DATETIME NULL,

    -- Timestamp creation / update otomatis
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Relasi FK
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");


$pdo->query("
CREATE TABLE IF NOT EXISTS series_summary (
    series_id INT PRIMARY KEY,

    is_published TINYINT DEFAULT 0,
    -- data untuk list/search
    name VARCHAR(255),
    slug VARCHAR(255),
    cover_url VARCHAR(255),
    description TEXT,
    entities_text MEDIUMBLOB,   -- genre, tag, author, dll (digabung)

    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- slug
    UNIQUE KEY ux_series_number (slug),
    -- fulltext search
    FULLTEXT KEY ft_series_search_1 (name),
    FULLTEXT KEY ft_series_search_2 (name, description),
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");

$pdo->query("
CREATE TABLE IF NOT EXISTS series_stats (

    -- counter dinamis
    series_id INT PRIMARY KEY,
    bookmark_count INT NOT NULL DEFAULT 0,
    views_count INT NOT NULL DEFAULT 0,
    comments_count INT NOT NULL DEFAULT 0,
    chapters_count INT NOT NULL DEFAULT 0,
    -- rating
    rating_count INT NOT NULL DEFAULT 0,
    rating_total INT NOT NULL DEFAULT 0,
    rating_average DECIMAL(4,2) GENERATED ALWAYS AS 
        (CASE WHEN rating_count > 0 THEN rating_total / rating_count ELSE 0 END) STORED,
    
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->query("
CREATE TABLE IF NOT EXISTS series_entities (
    series_id INT NOT NULL,
    entity_id INT NOT NULL,
    INDEX idx_entity_series (entity_id, series_id),
    PRIMARY KEY(series_id, entity_id),
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE,
    FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$pdo->query("
CREATE TABLE IF NOT EXISTS chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Series mana yang memiliki chapter ini
    series_id INT NOT NULL,

    -- is_published 0 = draft, 1 = published
    is_published TINYINT DEFAULT 0,

    lang_code CHAR(3) DEFAULT 'any',

    cover_url VARCHAR(255) NULL,
    
    -- Judul chapter (opsional, banyak komik tidak pakai)
    name VARCHAR(255) NOT NULL,

    -- URL ke file JSON yang berisi list gambar halaman
    content VARCHAR(255) NOT NULL COMMENT 'URL JSON: daftar halaman chapter',

    -- Nomor chapter (1, 2, 3, 10.5, 32.1)
    number DECIMAL(6,2) NOT NULL,

    -- User/admin yang membuat chapter
    created_by INT NULL,

    -- User/admin terakhir yang mengupdate
    updated_by INT NULL,

    -- Timestamp otomatis
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Pastikan setiap series tidak punya nomor chapter duplikat
    UNIQUE KEY ux_series_number (series_id, number),

    -- Index untuk pagination chapter per series
    INDEX idx_series_chapter (series_id, is_published, number DESC),

    -- Relasi
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// INDEX idx_series_published_number (series_id, is_published, number),

$pdo->query("
CREATE TABLE IF NOT EXISTS chapter_summary (
    -- Sama dengan id di tabel chapters
    chapter_id INT PRIMARY KEY,

    -- is_published 0 = draft, 1 = published
    is_published TINYINT DEFAULT 0,

    -- Total view chapter
    views_count INT NOT NULL DEFAULT 0,

    -- Total komentar di chapter
    comments_count INT NOT NULL DEFAULT 0,

    -- Total likes / upvote chapter
    likes_count INT NOT NULL DEFAULT 0,

    -- Jumlah bookmark chapter (kalau fitur ini ada)
    bookmarks_count INT NOT NULL DEFAULT 0,

    -- Relasi ke chapters
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
$pdo->query("
CREATE TABLE IF NOT EXISTS series_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Review untuk series mana
    series_id INT NOT NULL,

    -- User yang membuat review
    user_id INT NOT NULL,

    -- Rating wajib 1–5
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),

    -- Isi komentar (optional)
    comment TEXT NULL,

    -- Total suara dari review_votes (like - dislike)
    total_votes INT NOT NULL DEFAULT 0,

    -- Timestamp
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- User hanya boleh review 1x per series
    UNIQUE KEY uniq_series_user (series_id, user_id),

    -- Index umum
    INDEX idx_series (series_id),
    INDEX idx_user (user_id),
    INDEX idx_rating (rating),

    -- Foreign keys
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");


$pdo->query("
CREATE TABLE IF NOT EXISTS review_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,

    review_id INT NOT NULL,
    user_id INT NOT NULL,

    vote TINYINT NOT NULL CHECK (vote IN (1, -1)),

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- User cuma bisa vote 1x untuk review ini
    UNIQUE KEY uniq_user_review (user_id, review_id),

    INDEX idx_review (review_id),

    FOREIGN KEY (review_id) REFERENCES series_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");

$pdo->query("
CREATE TABLE IF NOT EXISTS chapter_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Chapter tempat comment berada
    chapter_id INT NOT NULL,

    -- User yang menulis comment
    user_id INT NOT NULL,

    -- Parent comment untuk reply (NULL = comment utama)
    parent_id INT DEFAULT NULL,

    -- Isi komentar
    comment TEXT NOT NULL,

    -- Total vote (like - dislike)
    total_votes INT NOT NULL DEFAULT 0,

    -- Timestamp
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Index untuk cepat query
    INDEX idx_chapter (chapter_id),
    INDEX idx_user (user_id),
    INDEX idx_parent (parent_id),

    -- Relasi
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES chapter_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");
$pdo->query("
CREATE TABLE IF NOT EXISTS comment_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Comment yang diberi vote
    comment_id INT NOT NULL,

    -- User yang vote
    user_id INT NOT NULL,

    -- 1 = like, -1 = dislike
    vote TINYINT NOT NULL CHECK (vote IN (1, -1)),

    -- Timestamp
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- User hanya bisa vote 1x per comment
    UNIQUE KEY uniq_user_comment (user_id, comment_id),

    -- Index untuk cepat menghitung vote per comment
    INDEX idx_comment (comment_id),

    -- Relasi
    FOREIGN KEY (comment_id) REFERENCES chapter_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");

$pdo->query("
CREATE TABLE IF NOT EXISTS site_summary (
    id INT PRIMARY KEY DEFAULT 1,  -- hanya 1 row global
     
    -- Total user terdaftar
    total_users INT NOT NULL DEFAULT 0,

    -- Total series
    total_series INT NOT NULL DEFAULT 0,

    -- Total chapter di semua series
    total_chapters INT NOT NULL DEFAULT 0,

    -- Total komentar di semua chapter
    total_comments INT NOT NULL DEFAULT 0,

    -- Total review di semua series
    total_reviews INT NOT NULL DEFAULT 0,

    -- Total vote review
    total_review_votes INT NOT NULL DEFAULT 0,

    -- Total bookmark (user menyimpan series)
    total_bookmarks INT NOT NULL DEFAULT 0,

    -- Total view (semua series + chapter)
    total_views BIGINT NOT NULL DEFAULT 0,

    -- Timestamp terakhir diupdate
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");
$pdo->query("
CREATE TABLE IF NOT EXISTS bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- User yang memberi bookmark
    user_id INT NOT NULL,

    -- Series yang dibookmark
    series_id INT NOT NULL,

    -- Chapter terakhir yang dibaca / terakhir dibuka
    last_chapter_id INT NULL,

    -- Timestamp saat bookmark dibuat / diupdate
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- User hanya bisa bookmark 1x per series
    UNIQUE KEY uniq_user_series (user_id, series_id),

    -- Index untuk query cepat
    INDEX idx_series (series_id),
    -- Relasi
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE,
    FOREIGN KEY (last_chapter_id) REFERENCES chapters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

");

/**
 * Kenapa?
 * Karena login, pencarian user, dan validasi akun pasti pakai email atau username.
 **/
$pdo->query("
	ALTER TABLE users ADD INDEX idx_email (email);
	ALTER TABLE users ADD INDEX idx_username (username);
");

/**
 * Kenapa?
 * Cek akun yang sedang dibanned
 * Cek akun nonaktif
 * Query admin dashboard jadi cepat
**/
$pdo->query("
	ALTER TABLE users ADD INDEX idx_is_active (is_active);
	ALTER TABLE users ADD INDEX idx_banned_until (banned_until);
");

/**
 * Kenapa?
 * Report "user aktif hari ini"
 * Deteksi spam berdasarkan IP
 * Menampilkan daftar user terbaru
**/
$pdo->query("
	ALTER TABLE users ADD INDEX idx_last_login (last_login);
	ALTER TABLE users ADD INDEX idx_signup_ip (signup_ip);
");

/**
 * Kenapa?
 * Untuk ngecek VIP yang akan expired / sedang aktif.
 **/
$pdo->query("
	ALTER TABLE users ADD INDEX idx_vip_until (vip_until);
");

/**
 * Kenapa?
 * Untuk fitur search user.
 * Sisanya cukup PK saja karena data profil dipanggil via JOIN ke user_id.
**/
$pdo->query("
	ALTER TABLE user_profiles ADD INDEX idx_display_name (display_name);
");

/**
 * Kenapa?
 * Menampilkan “recent active users”
 * Menentukan rekomendasi user aktif
*/
$pdo->query("
	ALTER TABLE user_summaries ADD INDEX idx_last_activity (last_activity);
");
