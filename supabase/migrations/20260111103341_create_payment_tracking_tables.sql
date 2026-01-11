/*
  # Payment Tracking Schema

  1. New Tables
    - `payment_plans`
      - `id` (uuid, primary key)
      - `name` (text) - Plan name (e.g., "Full Payment", "3-Month Installment")
      - `program_id` (uuid) - Associated program
      - `total_amount` (decimal) - Total plan amount
      - `installment_count` (integer) - Number of installments
      - `is_active` (boolean)
    
    - `student_payments`
      - `id` (uuid, primary key)
      - `lead_id` (bigint) - Reference to lead/student
      - `cohort_id` (uuid) - Enrolled cohort
      - `payment_plan_id` (uuid) - Selected payment plan
      - `total_amount` (decimal) - Total to pay
      - `paid_amount` (decimal) - Amount paid so far
      - `status` (text) - pending/partial/completed/overdue
    
    - `payment_installments`
      - `id` (uuid, primary key)
      - `student_payment_id` (uuid) - Parent payment record
      - `installment_number` (integer) - Which installment
      - `amount` (decimal) - Installment amount
      - `due_date` (date) - When payment is due
      - `paid_date` (timestamp) - When actually paid
      - `status` (text) - pending/paid/overdue
      - `reminder_sent_at` (timestamp) - Last reminder sent
    
    - `payment_transactions`
      - `id` (uuid, primary key)
      - `installment_id` (uuid) - Which installment this pays
      - `amount` (decimal) - Transaction amount
      - `payment_method` (text) - How they paid
      - `transaction_id` (text) - External transaction ID
      - `status` (text) - success/failed/pending

  2. Security
    - Enable RLS on all tables
*/

CREATE TABLE IF NOT EXISTS payment_plans (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  description text,
  program_id uuid REFERENCES programs(id) ON DELETE CASCADE,
  total_amount decimal(10,2) NOT NULL,
  currency text DEFAULT 'INR',
  installment_count integer DEFAULT 1,
  installment_interval_days integer DEFAULT 30,
  is_active boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS student_payments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  lead_id bigint NOT NULL,
  person_id bigint,
  cohort_id uuid REFERENCES cohorts(id) ON DELETE SET NULL,
  payment_plan_id uuid REFERENCES payment_plans(id) ON DELETE SET NULL,
  total_amount decimal(10,2) NOT NULL,
  paid_amount decimal(10,2) DEFAULT 0,
  discount_amount decimal(10,2) DEFAULT 0,
  discount_reason text,
  currency text DEFAULT 'INR',
  status text DEFAULT 'pending' CHECK (status IN ('pending', 'partial', 'completed', 'overdue', 'cancelled', 'refunded')),
  notes text,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS payment_installments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  student_payment_id uuid REFERENCES student_payments(id) ON DELETE CASCADE,
  installment_number integer NOT NULL,
  amount decimal(10,2) NOT NULL,
  due_date date NOT NULL,
  paid_date timestamptz,
  status text DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'overdue', 'waived')),
  reminder_count integer DEFAULT 0,
  last_reminder_at timestamptz,
  notes text,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS payment_transactions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  student_payment_id uuid REFERENCES student_payments(id) ON DELETE CASCADE,
  installment_id uuid REFERENCES payment_installments(id) ON DELETE SET NULL,
  amount decimal(10,2) NOT NULL,
  currency text DEFAULT 'INR',
  payment_method text CHECK (payment_method IN ('bank_transfer', 'upi', 'card', 'cash', 'cheque', 'other')),
  transaction_id text,
  gateway_response jsonb DEFAULT '{}',
  status text DEFAULT 'pending' CHECK (status IN ('pending', 'success', 'failed', 'refunded')),
  processed_at timestamptz,
  processed_by bigint,
  notes text,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE payment_plans ENABLE ROW LEVEL SECURITY;
ALTER TABLE student_payments ENABLE ROW LEVEL SECURITY;
ALTER TABLE payment_installments ENABLE ROW LEVEL SECURITY;
ALTER TABLE payment_transactions ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can read payment_plans"
  ON payment_plans FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Admins can manage payment_plans"
  ON payment_plans FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin'));

CREATE POLICY "Finance and Admins can read student_payments"
  ON student_payments FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'finance', 'sales'));

CREATE POLICY "Finance and Admins can manage student_payments"
  ON student_payments FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'finance'));

CREATE POLICY "Finance and Admins can read payment_installments"
  ON payment_installments FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'finance', 'sales'));

CREATE POLICY "Finance and Admins can manage payment_installments"
  ON payment_installments FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'finance'));

CREATE POLICY "Finance and Admins can read payment_transactions"
  ON payment_transactions FOR SELECT
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'finance', 'sales'));

CREATE POLICY "Finance and Admins can manage payment_transactions"
  ON payment_transactions FOR ALL
  TO authenticated
  USING (auth.jwt() ->> 'role' IN ('superadmin', 'admin', 'finance'));

CREATE INDEX idx_student_payments_lead ON student_payments(lead_id);
CREATE INDEX idx_student_payments_cohort ON student_payments(cohort_id);
CREATE INDEX idx_student_payments_status ON student_payments(status);
CREATE INDEX idx_payment_installments_due ON payment_installments(due_date);
CREATE INDEX idx_payment_installments_status ON payment_installments(status);
CREATE INDEX idx_payment_transactions_student ON payment_transactions(student_payment_id);
