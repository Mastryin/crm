/*
  # Lead Extensions and Automation Triggers Schema

  1. New Tables
    - `lead_extensions` - Extended lead data for education CRM
    - `status_automations` - Configure triggers for status changes
    - `form_qualification_rules` - Form-specific qualification logic
    - `lead_merge_log` - Track lead merges for audit
    - `trash_leads` - Soft delete with 60-day retention
    - `automation_action_types` - Available automation actions

  2. Security
    - Enable RLS on all tables
*/

CREATE TABLE IF NOT EXISTS lead_extensions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  lead_id bigint NOT NULL UNIQUE,
  source_channel text CHECK (source_channel IN ('meta_ads', 'deftform', 'csv_import', 'pabbly', 'manual', 'webform', 'api', 'referral')),
  source_campaign text,
  source_medium text,
  source_form_id text,
  original_source_data jsonb DEFAULT '{}',
  cohort_id uuid REFERENCES cohorts(id) ON DELETE SET NULL,
  program_id uuid REFERENCES programs(id) ON DELETE SET NULL,
  qualification_status text DEFAULT 'pending' CHECK (qualification_status IN ('pending', 'qualified', 'disqualified', 'manual_review')),
  qualification_score integer DEFAULT 0,
  phone_normalized text,
  email_normalized text,
  is_duplicate boolean DEFAULT false,
  duplicate_of_lead_id bigint,
  merge_history jsonb DEFAULT '[]',
  experience_years numeric,
  job_role text,
  company_name text,
  education_level text,
  linkedin_url text,
  utm_source text,
  utm_medium text,
  utm_campaign text,
  utm_term text,
  utm_content text,
  referrer_url text,
  landing_page text,
  device_type text,
  browser text,
  ip_address text,
  country text,
  city text,
  form_submitted_at timestamptz,
  first_response_at timestamptz,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS status_automations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  description text,
  entity_type text DEFAULT 'lead' CHECK (entity_type IN ('lead', 'person', 'payment')),
  from_status text,
  to_status text NOT NULL,
  pipeline_id bigint,
  from_stage_id bigint,
  to_stage_id bigint,
  conditions jsonb DEFAULT '[]',
  actions jsonb DEFAULT '[]',
  is_active boolean DEFAULT true,
  priority integer DEFAULT 0,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS form_qualification_rules (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  web_form_id bigint NOT NULL,
  name text NOT NULL,
  description text,
  rules jsonb NOT NULL DEFAULT '[]',
  qualification_threshold integer DEFAULT 70,
  auto_assign boolean DEFAULT true,
  assign_to_pipeline_id bigint,
  assign_to_stage_id bigint,
  send_confirmation_email boolean DEFAULT true,
  send_confirmation_whatsapp boolean DEFAULT true,
  is_active boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lead_merge_log (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  primary_lead_id bigint NOT NULL,
  merged_lead_id bigint NOT NULL,
  merge_reason text DEFAULT 'duplicate_phone',
  merge_data jsonb DEFAULT '{}',
  fields_updated jsonb DEFAULT '[]',
  merged_by bigint,
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS trash_leads (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  original_lead_id bigint NOT NULL,
  lead_data jsonb NOT NULL,
  person_data jsonb DEFAULT '{}',
  activities_data jsonb DEFAULT '[]',
  deleted_by bigint,
  deletion_reason text,
  deleted_at timestamptz DEFAULT now(),
  permanent_delete_at timestamptz DEFAULT (now() + interval '60 days'),
  restored boolean DEFAULT false,
  restored_at timestamptz,
  restored_by bigint
);

CREATE TABLE IF NOT EXISTS automation_action_types (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  code text NOT NULL UNIQUE,
  name text NOT NULL,
  description text,
  category text CHECK (category IN ('notification', 'assignment', 'update', 'integration', 'internal')),
  config_schema jsonb DEFAULT '{}',
  is_active boolean DEFAULT true,
  created_at timestamptz DEFAULT now()
);

INSERT INTO automation_action_types (code, name, category, config_schema) VALUES
  ('send_email', 'Send Email', 'notification', '{"required": ["template_id", "to"]}'),
  ('send_whatsapp', 'Send WhatsApp Message', 'notification', '{"required": ["template_id", "phone"]}'),
  ('send_sms', 'Send SMS', 'notification', '{"required": ["template_id", "phone"]}'),
  ('assign_user', 'Assign to User', 'assignment', '{"required": ["user_id"]}'),
  ('round_robin_assign', 'Round Robin Assignment', 'assignment', '{"required": ["team_id"]}'),
  ('update_field', 'Update Field', 'update', '{"required": ["field", "value"]}'),
  ('move_stage', 'Move to Stage', 'update', '{"required": ["stage_id"]}'),
  ('add_tag', 'Add Tag', 'update', '{"required": ["tag_id"]}'),
  ('trigger_webhook', 'Trigger Webhook', 'integration', '{"required": ["webhook_id"]}'),
  ('call_pabbly', 'Send to Pabbly', 'integration', '{"required": ["pabbly_webhook_url"]}'),
  ('schedule_call', 'Schedule Call', 'internal', '{"required": ["call_type", "delay_hours"]}'),
  ('create_task', 'Create Task', 'internal', '{"required": ["task_type", "title"]}'),
  ('add_note', 'Add Note', 'internal', '{"required": ["content"]}')
ON CONFLICT (code) DO NOTHING;

INSERT INTO lead_sources_config (source_name, display_name, field_mapping, is_active) VALUES
  ('meta_ads', 'Meta Ads (Facebook/Instagram)', '{"name": "full_name", "email": "email", "phone": "phone_number", "campaign": "campaign_name"}', true),
  ('deftform', 'Deftform', '{"name": "name", "email": "email", "phone": "phone", "experience": "work_experience"}', true),
  ('csv_import', 'CSV Import', '{}', true),
  ('pabbly', 'Pabbly Connect', '{}', true),
  ('manual', 'Manual Entry', '{}', true),
  ('webform', 'Website Form', '{}', true)
ON CONFLICT (source_name) DO NOTHING;

ALTER TABLE lead_extensions ENABLE ROW LEVEL SECURITY;
ALTER TABLE status_automations ENABLE ROW LEVEL SECURITY;
ALTER TABLE form_qualification_rules ENABLE ROW LEVEL SECURITY;
ALTER TABLE lead_merge_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE trash_leads ENABLE ROW LEVEL SECURITY;
ALTER TABLE automation_action_types ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can read lead_extensions"
  ON lead_extensions FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'sales', 'advisor'));

CREATE POLICY "Sales and Admins can manage lead_extensions"
  ON lead_extensions FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'sales'));

CREATE POLICY "Authenticated users can read status_automations"
  ON status_automations FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Admins can manage status_automations"
  ON status_automations FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Authenticated users can read form_qualification_rules"
  ON form_qualification_rules FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Admins can manage form_qualification_rules"
  ON form_qualification_rules FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Admins can read lead_merge_log"
  ON lead_merge_log FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "System can manage lead_merge_log"
  ON lead_merge_log FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Admins can read trash_leads"
  ON trash_leads FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Admins can manage trash_leads"
  ON trash_leads FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Authenticated users can read automation_action_types"
  ON automation_action_types FOR SELECT
  TO authenticated
  USING (true);

CREATE INDEX idx_lead_extensions_lead ON lead_extensions(lead_id);
CREATE INDEX idx_lead_extensions_phone ON lead_extensions(phone_normalized);
CREATE INDEX idx_lead_extensions_email ON lead_extensions(email_normalized);
CREATE INDEX idx_lead_extensions_qualification ON lead_extensions(qualification_status);
CREATE INDEX idx_lead_extensions_source ON lead_extensions(source_channel);
CREATE INDEX idx_lead_extensions_cohort ON lead_extensions(cohort_id);
CREATE INDEX idx_status_automations_trigger ON status_automations(entity_type, to_status);
CREATE INDEX idx_form_qualification_rules_form ON form_qualification_rules(web_form_id);
CREATE INDEX idx_trash_leads_permanent ON trash_leads(permanent_delete_at) WHERE NOT restored;
