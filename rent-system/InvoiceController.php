<?php

namespace App\Http\Controllers;

use App\Account;
use App\BankList;
use App\Building;
use App\Floor;
use App\Invoice;
use App\InvoiceDetails;
use App\Item;
use App\RentableUnit;
use App\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $invoices = Invoice::Index();
        return view('dashboard.invoices.index', compact('invoices'));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function generate()
    {
        $tenants = Tenant::Index();
        return view('dashboard.invoices.tenants', compact('tenants'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create(Request $request)
    {
        $tenant = Tenant::find($request->tenant_id);
        $items = Item::ActiveItems();
        $amount = Item::TotalAmount();
        $invoiceDate = Invoice::tenantHaveInvoiceCheckingForDate($tenant);
        return view('dashboard.invoices.create', compact('tenant', 'items', 'amount', 'invoiceDate'));
    }

    /**
     * @param Request $request
     * @param Invoice $invoice
     * @param InvoiceDetails $details
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Invoice $invoice, InvoiceDetails $details)
    {
        $request->validate([
            'title' => 'required|max:191',
            'amount' => 'required',
        ]);

        $tenant = Tenant::SingleTenant($request->tenant_id);
        $titles = $request->item_title;
        $amounts = $request->item_amount;
        $codes = $request->item_code;

        try {
            $storedInvoice = $invoice->create($request->all());
            foreach ($request->item_id as $key => $item) {
                $storedInvoice->details()->create([
                    'item_id' => $item,
                    'start_date' => $storedInvoice->start_date,
                    'end_date' => $storedInvoice->end_date,
                    'amount' => $request->item_amount[$key],
                ]);
            }
            return redirect()->route('invoices.print')->with([
                'tenant' => $tenant,
                'titles' => $titles,
                'amounts' => $amounts,
                'codes' => $codes,
                'invoice' => $storedInvoice,
            ]);
        } catch (\Exception $exception) {
            return back()->withErrors([
                'error' => $exception->getMessage()
            ]);
        }
    }


    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function print()
    {
        $tenant = session()->get('tenant');
        $titles = session()->get('titles');
        $amounts = session()->get('amounts');
        $codes = session()->get('codes');
        $invoice = session()->get('invoice');
        $banklists = BankList::Index();

        if (!isset($tenant) && empty($tenant)) {
            return redirect()->route('invoices.generate');
        }

        return view('dashboard.invoices.invoice', compact('tenant', 'titles', 'amounts', 'codes', 'invoice', 'banklists'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function paid(Request $request)
    {
        $tenant = optional(Invoice::find($request->invoice_id)->tenant)->title;
        $lastAccount = Account::all()->last();

        if (is_null($lastAccount)) {
            Account::create([
                'description' => $tenant.', '.$request->bank,
                'credit' => $request->invoice_amount,
                'balance' => $request->invoice_amount,
            ]);
        } else {
            Account::create([
                'description' => $tenant.', '.$request->bank,
                'credit' => $request->invoice_amount,
                'balance' => $lastAccount->balance + $request->invoice_amount,
            ]);
        }

        Invoice::find($request->invoice_id)->update(['is_paid' => 1]);
        return response()->json(['is_true' => true]);
    }

    /**
     * @param Invoice $invoice
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($id)
    {
        $invoice = Invoice::find($id);
        $tenant = optional(Invoice::where('id', $id)->with('tenant')->first())->tenant;
        $details = optional(Invoice::where('id', $id)->with('details.item')->first())->details;
        $banklists = BankList::Index();
        return view('dashboard.invoices.invoice', compact('tenant', 'details', 'invoice', 'banklists'));
    }
}