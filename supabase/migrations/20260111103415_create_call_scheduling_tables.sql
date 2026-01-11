/*
  # Call Scheduling and Assignment Schema

  1. New Tables
    - `scheduled_calls`
      - `id` (uuid, primary key)
      - `lead_id` (bigint) - Reference to lead
      - `user_id` (bigint) - Assigned sales advisor
      - `call_type` (text) - sales/interview/followup
      - `scheduled_at` (timestamp) - When call is scheduled
      - `duration_minutes` (integer) - Expected duration
      - `trafft_booking_id` (text) - External Trafft ID
      - `status` (text) - scheduled/completed/cancelled/no_show
      - `notes` (text) - Call notes
      - `outcome` (text) - Call outcome
    
    - `user_assignments`
      - Tracks round-robin assignment state for sales advisors
      - `user_id` (bigint) - Sales advisor
      - `current_load` (integer) - Active leads assigned
      - `max_load` (integer) - Maximum leads they can handle
      - `last_assigned_at` (timestamp) - For round-robin fairness
      - `is_available` (boolean) - Can receive new leads
    
    - `lead_sources_config`
      - Configuration for multi-channel lead capture
      - `source_name` (text) - meta_ads/deftform/csv/pabbly/manual
      - `api_key` (text) - For webhook authentication
      - `webhook_url` (text) - Incoming webhook URL
      - `field_mapping` (jsonb) - Map external fields to internal
    
    - `notification_logs`
      - Track all notifications sent
      - `lead_id` (bigint)
      - `channel` (text) - email/whatsapp/sms
      - `template_id` (text)
      - `status` (text) - sent/delivered/failed
      - `provider_response` (jsonb)

  2. Security
    - Enable RLS on all tables
*/

CREATE TABLE IF NOT EXISTS scheduled_calls (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  lead_id bigint NOT NULL,
  person_id bigint,
  user_id bigint NOT NULL,
  call_type text DEFAULT 'sales' CHECK (call_type IN ('sales', 'interview', 'followup', 'support', 'demo')),
  scheduled_at timestamptz NOT NULL,
  duration_minutes integer DEFAULT 30,
  meeting_link text,
  trafft_booking_id text,
  trafft_service_id text,
  status text DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled')),
  notes text,
  outcome text,
  outcome_details jsonb DEFAULT '{}',
  reminder_sent boolean DEFAULT false,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS user_assignments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id bigint NOT NULL UNIQUE,
  current_load integer DEFAULT 0,
  max_load integer DEFAULT 50,
  daily_limit integer DEFAULT 10,
  today_assigned integer DEFAULT 0,
  last_assigned_at timestamptz,
  is_available boolean DEFAULT true,
  assignment_weight integer DEFAULT 1,
  specializations jsonb DEFAULT '[]',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lead_sources_config (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  source_name text NOT NULL UNIQUE,
  display_name text,
  description text,
  api_key text,
  webhook_secret text,
  webhook_url text,
  field_mapping jsonb DEFAULT '{}',
  default_pipeline_id bigint,
  default_stage_id bigint,
  auto_qualify boolean DEFAULT true,
  is_active boolean DEFAULT true,
  settings jsonb DEFAULT '{}',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS notification_logs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  lead_id bigint,
  person_id bigint,
  user_id bigint,
  channel text NOT NULL CHECK (channel IN ('email', 'whatsapp', 'sms', 'push', 'in_app')),
  template_name text,
  template_id text,
  recipient text NOT NULL,
  subject text,
  content text,
  variables jsonb DEFAULT '{}',
  provider text,
  provider_message_id text,
  provider_response jsonb DEFAULT '{}',
  status text DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'delivered', 'read', 'failed', 'bounced')),
  sent_at timestamptz,
  delivered_at timestamptz,
  read_at timestamptz,
  error_message text,
  retry_count integer DEFAULT 0,
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS notification_templates (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  code text NOT NULL UNIQUE,
  channel text NOT NULL CHECK (channel IN ('email', 'whatsapp', 'sms')),
  subject text,
  content text NOT NULL,
  variables jsonb DEFAULT '[]',
  trigger_event text,
  trigger_status text,
  is_active boolean DEFAULT true,
  provider_template_id text,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

ALTER TABLE scheduled_calls ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_assignments ENABLE ROW LEVEL SECURITY;
ALTER TABLE lead_sources_config ENABLE ROW LEVEL SECURITY;
ALTER TABLE notification_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE notification_templates ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Sales can view their own calls"
  ON scheduled_calls FOR SELECT
  TO authenticated
  USING (
    user_id::text = auth.uid()::text 
    OR auth.jwt() ->> 'role' IN ('superadmin', 'admin')
  );

CREATE POLICY "Admins can manage all calls"
  ON scheduled_calls FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'sales'));

CREATE POLICY "Admins can read user_assignments"
  ON user_assignments FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'sales'));

CREATE POLICY "Admins can manage user_assignments"
  ON user_assignments FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Admins can read lead_sources_config"
  ON lead_sources_config FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "SuperAdmins can manage lead_sources_config"
  ON lead_sources_config FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' = 'superadmin');

CREATE POLICY "Authenticated users can read notification_logs"
  ON notification_logs FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'sales', 'finance'));

CREATE POLICY "System can manage notification_logs"
  ON notification_logs FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Authenticated users can read notification_templates"
  ON notification_templates FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Admins can manage notification_templates"
  ON notification_templates FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE INDEX idx_scheduled_calls_lead ON scheduled_calls(lead_id);
CREATE INDEX idx_scheduled_calls_user ON scheduled_calls(user_id);
CREATE INDEX idx_scheduled_calls_scheduled ON scheduled_calls(scheduled_at);
CREATE INDEX idx_scheduled_calls_status ON scheduled_calls(status);
CREATE INDEX idx_user_assignments_available ON user_assignments(is_available, current_load);
CREATE INDEX idx_notification_logs_lead ON notification_logs(lead_id);
CREATE INDEX idx_notification_logs_status ON notification_logs(status);
CREATE INDEX idx_notification_templates_trigger ON notification_templates(trigger_event, trigger_status);
