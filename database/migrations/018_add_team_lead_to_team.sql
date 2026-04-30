-- =========================================================
-- MIGRATION 018: ADD FOREIGN KEY team_lead_id TO team TABLE
-- =========================================================

-- Karena kolom sudah ada di migration 000, kita cukup pasang constraint-nya saja
ALTER TABLE team 
ADD CONSTRAINT fk_team_lead_user FOREIGN KEY (team_lead_id) REFERENCES users(id) ON DELETE SET NULL;
