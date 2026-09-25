CREATE TABLE IF NOT EXISTS accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    minecraft_uuid CHAR(32) NOT NULL UNIQUE,
    minecraft_name VARCHAR(32) NOT NULL,
    display_name VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_accounts_name (minecraft_name),
    INDEX idx_accounts_last_synced (last_synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    profile_id VARCHAR(64) NOT NULL UNIQUE,
    profile_name VARCHAR(100) NOT NULL,
    game_mode VARCHAR(32) DEFAULT 'normal',
    is_selected TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    raw_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_profiles_account (account_id),
    INDEX idx_profiles_selected (is_selected),
    INDEX idx_profiles_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    networth DECIMAL(14,2) DEFAULT 0,
    magical_power DECIMAL(10,2) DEFAULT 0,
    skill_average DECIMAL(8,2) DEFAULT 0,
    combat_level DECIMAL(8,2) DEFAULT 0,
    mining_level DECIMAL(8,2) DEFAULT 0,
    farming_level DECIMAL(8,2) DEFAULT 0,
    fishing_level DECIMAL(8,2) DEFAULT 0,
    foraging_level DECIMAL(8,2) DEFAULT 0,
    enchanting_level DECIMAL(8,2) DEFAULT 0,
    alchemy_level DECIMAL(8,2) DEFAULT 0,
    taming_level DECIMAL(8,2) DEFAULT 0,
    carpentry_level DECIMAL(8,2) DEFAULT 0,
    runecrafting_level DECIMAL(8,2) DEFAULT 0,
    catacombs_level DECIMAL(8,2) DEFAULT 0,
    raw_stats JSON DEFAULT NULL,
    CONSTRAINT fk_snapshots_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    INDEX idx_snapshots_profile_time (profile_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    item_id VARCHAR(120) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'misc',
    quantity INT NOT NULL DEFAULT 1,
    rarity VARCHAR(32) DEFAULT NULL,
    extra_data JSON DEFAULT NULL,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    INDEX idx_items_profile_category (profile_id, category),
    INDEX idx_items_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_skills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    skill_name VARCHAR(64) NOT NULL,
    level DECIMAL(8,2) NOT NULL DEFAULT 0,
    xp DECIMAL(14,2) NOT NULL DEFAULT 0,
    xp_next_level DECIMAL(14,2) NOT NULL DEFAULT 0,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_skills_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_profile_skill (profile_id, skill_name),
    INDEX idx_skills_profile (profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_collections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    collection_key VARCHAR(120) NOT NULL,
    collection_name VARCHAR(120) NOT NULL,
    category VARCHAR(120) DEFAULT 'misc',
    amount BIGINT NOT NULL DEFAULT 0,
    tier INT NOT NULL DEFAULT 0,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_collections_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_profile_collection (profile_id, collection_key),
    INDEX idx_collections_category (profile_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_dungeons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    dungeon_type VARCHAR(64) NOT NULL,
    floor VARCHAR(32) NOT NULL,
    completions INT NOT NULL DEFAULT 0,
    best_score VARCHAR(10) DEFAULT NULL,
    fastest_time INT DEFAULT NULL,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dungeons_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_profile_dungeon_floor (profile_id, dungeon_type, floor),
    INDEX idx_dungeons_profile (profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    category VARCHAR(64) DEFAULT 'general',
    priority VARCHAR(32) DEFAULT 'medium',
    target_progress DECIMAL(12,2) NOT NULL DEFAULT 100,
    current_progress DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_goals_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    INDEX idx_goals_profile_status (profile_id, status),
    INDEX idx_goals_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    goal_id BIGINT UNSIGNED NOT NULL,
    requirement_type VARCHAR(64) NOT NULL,
    requirement_key VARCHAR(120) NOT NULL,
    requirement_value VARCHAR(255) NOT NULL,
    is_met TINYINT(1) NOT NULL DEFAULT 0,
    checked_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_goal_requirements_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
    INDEX idx_goal_requirements_goal (goal_id),
    INDEX idx_goal_requirements_type_key (requirement_type, requirement_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    minecraft_id VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'misc',
    description TEXT DEFAULT NULL,
    npc_price DECIMAL(12,2) DEFAULT NULL,
    is_craftable TINYINT(1) NOT NULL DEFAULT 0,
    dungeon_required TINYINT(1) NOT NULL DEFAULT 0,
    floor_required VARCHAR(32) DEFAULT NULL,
    INDEX idx_item_definitions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    requirement_type VARCHAR(64) NOT NULL,
    requirement_key VARCHAR(120) NOT NULL,
    requirement_value VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_item_requirements_item FOREIGN KEY (item_id) REFERENCES item_definitions(id) ON DELETE CASCADE,
    INDEX idx_item_requirements_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resource_key VARCHAR(120) NOT NULL UNIQUE,
    resource_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'misc',
    description TEXT DEFAULT NULL,
    INDEX idx_resource_definitions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(190) NOT NULL UNIQUE,
    response_json LONGTEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'running',
    message TEXT DEFAULT NULL,
    profiles_synced INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_sync_logs_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_sync_logs_account_started (account_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dungeon_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    dungeon_type VARCHAR(64) NOT NULL,
    floor VARCHAR(32) NOT NULL,
    completed_at TIMESTAMP NOT NULL,
    result VARCHAR(32) NOT NULL DEFAULT 'complete',
    drop_obtained VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_manual TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dungeon_runs_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
    INDEX idx_dungeon_runs_profile_date (profile_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progression_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    goal_key VARCHAR(120) NOT NULL,
    requirement_type VARCHAR(64) NOT NULL,
    requirement_key VARCHAR(120) NOT NULL,
    requirement_value VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    INDEX idx_progression_rules_goal (goal_key),
    INDEX idx_progression_rules_type_key (requirement_type, requirement_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
