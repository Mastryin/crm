/*
  # Programs and Cohorts Management Schema

  1. New Tables
    - `programs`
      - `id` (uuid, primary key)
      - `name` (text) - Program name
      - `description` (text) - Program description
      - `duration_weeks` (integer) - Program duration in weeks
      - `price` (decimal) - Program price
      - `is_active` (boolean) - Whether program is active
      - `created_at`, `updated_at` (timestamps)
    
    - `cohorts`
      - `id` (uuid, primary key)
      - `program_id` (uuid, foreign key) - Links to program
      - `name` (text) - Cohort name (e.g., "Batch 2024-Q1")
      - `start_date` (date) - Cohort start date
      - `end_date` (date) - Cohort end date
      - `application_deadline` (timestamp) - Last date to apply
      - `capacity` (integer) - Maximum students
      - `enrolled_count` (integer) - Current enrollment count
      - `status` (text) - upcoming/active/completed/cancelled
      - `created_at`, `updated_at` (timestamps)

  2. Security
    - Enable RLS on both tables
    - Add policies for authenticated users
*/

CREATE TABLE IF NOT EXISTS programs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  description text,
  duration_weeks integer DEFAULT 12,
  price decimal(10,2) DEFAULT 0,
  currency text DEFAULT 'INR',
  is_active boolean DEFAULT true,
  metadata jsonb DEFAULT '{}',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS cohorts (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  program_id uuid REFERENCES programs(id) ON DELETE CASCADE,
  name text NOT NULL,
  start_date date NOT NULL,
  end_date date,
  application_deadline timestamptz,
  capacity integer DEFAULT 50,
  enrolled_count integer DEFAULT 0,
  status text DEFAULT 'upcoming' CHECK (status IN ('upcoming', 'active', 'completed', 'cancelled')),
  metadata jsonb DEFAULT '{}',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

ALTER TABLE programs ENABLE ROW LEVEL SECURITY;
ALTER TABLE cohorts ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can read programs"
  ON programs FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Admins can manage programs"
  ON programs FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Authenticated users can read cohorts"
  ON cohorts FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Admins can manage cohorts"
  ON cohorts FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE INDEX idx_cohorts_program_id ON cohorts(program_id);
CREATE INDEX idx_cohorts_status ON cohorts(status);
CREATE INDEX idx_cohorts_application_deadline ON cohorts(application_deadline);
