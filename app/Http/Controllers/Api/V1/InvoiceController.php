<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\InvoiceData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * POST /api/v1/invoices (§14). Requires the invoice:post scope.
 */
final class InvoiceController extends ApiController
{
    public function store(Request $request, PostInvoice $post): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'invoice_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
        ]);

        $this->authorizedCompany((int) $validated['company_id']);

        /** @var User $user */
        $user = Auth::user();
        $invoice = $post->handle(InvoiceData::from($request->all()), $user);

        return new JsonResponse([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'total' => $invoice->total->minor,
        ], 201);
    }
}
