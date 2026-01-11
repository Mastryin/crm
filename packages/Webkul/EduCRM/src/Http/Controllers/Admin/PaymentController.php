<?php

namespace Webkul\EduCRM\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Webkul\EduCRM\Models\StudentPayment;
use Webkul\EduCRM\Models\PaymentInstallment;
use Webkul\EduCRM\Models\PaymentTransaction;
use Webkul\EduCRM\Models\PaymentPlan;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = StudentPayment::with(['lead.person', 'cohort.program', 'paymentPlan', 'installments'])
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->input('cohort_id'), function ($query, $cohortId) {
                $query->where('cohort_id', $cohortId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($payments);
        }

        return view('educrm::admin.payments.index', compact('payments'));
    }

    public function show(string $id): JsonResponse
    {
        $payment = StudentPayment::with([
            'lead.person',
            'cohort.program',
            'paymentPlan',
            'installments',
            'transactions',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|integer',
            'cohort_id' => 'required|exists:cohorts,id',
            'payment_plan_id' => 'required|exists:payment_plans,id',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentPlan = PaymentPlan::findOrFail($request->input('payment_plan_id'));

        $payment = DB::transaction(function () use ($request, $paymentPlan) {
            $payment = StudentPayment::create([
                'lead_id' => $request->input('lead_id'),
                'cohort_id' => $request->input('cohort_id'),
                'payment_plan_id' => $request->input('payment_plan_id'),
                'total_amount' => $paymentPlan->total_amount,
                'discount_amount' => $request->input('discount_amount', 0),
                'discount_reason' => $request->input('discount_reason'),
                'currency' => $paymentPlan->currency,
                'status' => 'pending',
            ]);

            $payment->createInstallments();

            return $payment;
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment record created successfully',
            'data' => $payment->load('installments'),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payment = StudentPayment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'status' => 'in:pending,partial,completed,overdue,cancelled,refunded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => $payment,
        ]);
    }

    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $payment = StudentPayment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'installment_id' => 'nullable|exists:payment_installments,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:bank_transfer,upi,card,cash,cheque,other',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = DB::transaction(function () use ($request, $payment) {
            $transaction = PaymentTransaction::create([
                'student_payment_id' => $payment->id,
                'installment_id' => $request->input('installment_id'),
                'amount' => $request->input('amount'),
                'currency' => $payment->currency,
                'payment_method' => $request->input('payment_method'),
                'transaction_id' => $request->input('transaction_id'),
                'notes' => $request->input('notes'),
                'status' => 'pending',
                'created_at' => now(),
            ]);

            $transaction->markAsSuccess(auth()->id());

            return $transaction;
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'data' => $transaction,
        ]);
    }

    public function overdue(Request $request): JsonResponse
    {
        $installments = PaymentInstallment::overdue()
            ->with(['studentPayment.lead.person', 'studentPayment.cohort'])
            ->orderBy('due_date')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $installments,
        ]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);

        $installments = PaymentInstallment::upcoming($days)
            ->with(['studentPayment.lead.person', 'studentPayment.cohort'])
            ->orderBy('due_date')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $installments,
        ]);
    }
}
