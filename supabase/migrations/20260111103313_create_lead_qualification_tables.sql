/*
  # Lead Qualification Engine Schema

  1. New Tables
    - `qualification_rules`
      - `id` (uuid, primary key)
      - `name` (text) - Rule name
      - `entity_type` (text) - Type of entity (lead, person)
      - `field_name` (text) - Field to check
      - `operator` (text) - Comparison operator
      - `value` (text) - Value to compare against
      - `action` (text) - Action to take (qualify, disqualify, flag)
      - `priority` (integer) - Rule evaluation priority
      - `is_active` (boolean)
    
    - `negative_keywords`
      - `id` (uuid, primary key)
      - `keyword` (text) - Keyword that disqualifies
      - `field_name` (text) - Field to check for keyword
      - `is_active` (boolean)
    
    - `lead_qualifications`
      - `id` (uuid, primary key)
      - `lead_id` (bigint) - Reference to lead
      - `is_qualified` (boolean)
      - `qualification_score` (integer)
      - `disqualification_reasons` (jsonb)
      - `qualified_at` (timestamp)
      - `assigned_to` (bigint) - Sales advisor assigned

  2. Security
    - Enable RLS on all tables
*/

CREATE TABLE IF NOT EXISTS qualification_rules (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  description text,
  entity_type text DEFAULT 'lead' CHECK (entity_type IN ('lead', 'person', 'form_response')),
  field_name text NOT NULL,
  operator text NOT NULL CHECK (operator IN ('equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'greater_equal', 'less_equal', 'in', 'not_in', 'is_empty', 'is_not_empty')),
  value text,
  action text DEFAULT 'disqualify' CHECK (action IN ('qualify', 'disqualify', 'flag', 'score_add', 'score_subtract')),
  score_value integer DEFAULT 0,
  priority integer DEFAULT 0,
  is_active boolean DEFAULT true,
  program_id uuid REFERENCES programs(id) ON DELETE SET NULL,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS negative_keywords (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  keyword text NOT NULL,
  field_name text DEFAULT 'experience',
  match_type text DEFAULT 'contains' CHECK (match_type IN ('exact', 'contains', 'starts_with', 'ends_with')),
  is_active boolean DEFAULT true,
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lead_qualifications (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  lead_id bigint NOT NULL,
  is_qualified boolean DEFAULT false,
  qualification_score integer DEFAULT 0,
  disqualification_reasons jsonb DEFAULT '[]',
  matched_rules jsonb DEFAULT '[]',
  qualified_at timestamptz,
  reviewed_by bigint,
  reviewed_at timestamptz,
  cohort_id uuid REFERENCES cohorts(id) ON DELETE SET NULL,
  program_id uuid REFERENCES programs(id) ON DELETE SET NULL,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

ALTER TABLE qualification_rules ENABLE ROW LEVEL SECURITY;
ALTER TABLE negative_keywords ENABLE ROW LEVEL SECURITY;
ALTER TABLE lead_qualifications ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can read qualification_rules"
  ON qualification_rules FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Admins can manage qualification_rules"
  ON qualification_rules FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Authenticated users can read negative_keywords"
  ON negative_keywords FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Admins can manage negative_keywords"
  ON negative_keywords FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Authenticated users can read lead_qualifications"
  ON lead_qualifications FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Sales and Admins can manage lead_qualifications"
  ON lead_qualifications FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'sales'));

CREATE INDEX idx_qualification_rules_active ON qualification_rules(is_active);
CREATE INDEX idx_qualification_rules_entity ON qualification_rules(entity_type);
CREATE INDEX idx_lead_qualifications_lead ON lead_qualifications(lead_id);
CREATE INDEX idx_lead_qualifications_qualified ON lead_qualifications(is_qualified);
