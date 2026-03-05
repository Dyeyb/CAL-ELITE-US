-- Supabase settings schema

-- Create the SystemSettings table
-- This table acts as a simple key-value store for all site and user preferences.
CREATE TABLE "public"."SystemSettings" (
    "setting_key" text NOT NULL,
    "setting_value" text,
    "updated_at" timestamp with time zone DEFAULT now(),
    PRIMARY KEY ("setting_key")
);

-- Seed defaults for Profile
INSERT INTO "public"."SystemSettings" ("setting_key", "setting_value") VALUES
('profile_first_name', 'Admin'),
('profile_last_name', ''),
('profile_email', 'admin@aluminumlady.com'),
('profile_phone', '+86 757 8888 0000'),
('profile_role', 'Super Administrator'),
('profile_department', 'Management'),
('profile_bio', '');

-- Seed defaults for Site Information
INSERT INTO "public"."SystemSettings" ("setting_key", "setting_value") VALUES
('site_company_name', 'Aluminum Lady Building Materials Co., Ltd.'),
('site_tagline', 'Premium Aluminum Solutions for Modern Architecture'),
('site_contact_email', 'info@aluminumlady.com'),
('site_contact_phone', '+86 757 8283 3000'),
('site_company_address', 'No. 8 Aluminum Boulevard, Foshan, Guangdong, China 528000'),
('site_website_url', 'https://www.aluminumlady.com'),
('site_business_hours', 'Mon–Sat 8:00 AM – 6:00 PM (GMT+8)'),
('site_company_description', 'Aluminum Lady is a leading manufacturer of premium aluminum building systems, specializing in curtain walls, windows, doors, and architectural facades for commercial and residential projects across Asia.'),
('site_facebook_url', 'https://facebook.com/aluminumlady'),
('site_instagram_url', ''),
('site_wechat_id', 'AluminumLady_Official'),
('site_whatsapp_number', '+86 138 0000 0000');

-- Seed defaults for Appearance
INSERT INTO "public"."SystemSettings" ("setting_key", "setting_value") VALUES
('appearance_theme', 'dark'),
('appearance_accent_color', 'gold'),
('appearance_compact_mode', 'false'),
('appearance_grid_overlay', 'true'),
('appearance_sidebar_collapsed', 'false'),
('appearance_smooth_animations', 'true');

-- Seed defaults for Notifications
INSERT INTO "public"."SystemSettings" ("setting_key", "setting_value") VALUES
('notif_email', 'admin@aluminumlady.com'),
('notif_digest_frequency', 'Daily digest — 8:00 AM'),
('notif_alert_new_message', 'true'),
('notif_alert_low_stock', 'true'),
('notif_alert_product_updated', 'false'),
('notif_alert_gallery_published', 'true'),
('notif_email_new_inquiry', 'true'),
('notif_email_complaint', 'true'),
('notif_email_weekly_summary', 'false');

-- Seed defaults for Security
INSERT INTO "public"."SystemSettings" ("setting_key", "setting_value") VALUES
('security_2fa_authenticator', 'false'),
('security_login_email_otp', 'true');

-- Seed defaults for API
INSERT INTO "public"."SystemSettings" ("setting_key", "setting_value") VALUES
('api_key_prod', 'al_prod_xK9mN3pQr7vWzYbT2dFsHjLcEuAoIgMn'),
('api_key_test', 'al_test_pT4kM8nRs2wXyVqA6cGjHbOiLeDuNfPz'),
('api_smtp_email', 'true'),
('api_google_analytics', 'true'),
('api_facebook_pixel', 'false'),
('api_recaptcha_v3', 'true');

-- Seed defaults for Backup
INSERT INTO "public"."SystemSettings" ("setting_key", "setting_value") VALUES
('backup_frequency', 'Daily at 3:00 AM (Recommended)'),
('backup_retention_period', '30 days'),
('backup_storage', 'Local Server'),
('backup_format', 'SQL + Media Files (.zip)'),
('backup_email_report', 'true'),
('backup_on_change', 'false');
