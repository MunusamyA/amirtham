-- Amirtham v16 UI compatibility migration
-- No new table is required.
-- Fix the old unsupported Lucide mail-cog menu icon stored by earlier builds.

UPDATE menus
SET icon = 'mail', updated_at = NOW()
WHERE menu_path = 'mail-settings.php'
  AND (icon = 'mail-cog' OR icon = 'mail-settings' OR icon IS NULL OR icon = '');
