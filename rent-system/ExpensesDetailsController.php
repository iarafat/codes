<?php

namespace App\Http\Controllers;

use App\Account;
use App\BankList;
use App\ExpensesDetails;
use App\ExpensesHeads;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ExpensesDetailsController extends Controller
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
        $details = ExpensesDetails::Index();
        return view('dashboard.expensesdetails.index', compact('details'));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        $heads = ExpensesHeads::Index();
        $banks = BankList::Index();
        return view('dashboard.expensesdetails.create', compact('heads', 'banks'));
    }

    /**
     * @param Request $request
     * @param Building $building
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, ExpensesDetails $detail)
    {
        $lastAccount = Account::all()->last();
        $headTitle = ExpensesHeads::find($request->head_id)->title;

        $request->validate([
            'head_id' => 'required',
            'from_bank' => 'required',
            'to_bank' => 'required',
        ]);
        try {
            $detail->create($request->all());

            if (is_null($lastAccount)) {
                Account::create([
                    'description' => $headTitle.', '.$request->from_bank.', '.$request->to_bank,
                    'debt' => $request->amount,
                    'balance' => '-'.$request->amount,
                ]);
            } else {
                Account::create([
                    'description' => $headTitle.', '.$request->from_bank.', '.$request->to_bank,
                    'debt' => $request->amount,
                    'balance' => $lastAccount->balance - $request->amount,
                ]);
            }

            return back()->with('status', 'Stored successfully');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }

    /**
     * @param Building $building
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(ExpensesDetails $detail)
    {
        $heads = ExpensesHeads::Index();
        $banks = BankList::Index();
        return view('dashboard.expensesdetails.edit', compact('detail', 'heads', 'banks'));
    }

    /**
     * @param Request $request
     * @param Building $building
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ExpensesDetails $detail)
    {
        $request->validate([
            'head_id' => 'required',
        ]);
        if ($detail->update($request->all())) {
            return back()->with('status', 'Updated successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    /**
     * @param Building $building
     * @return $this|\Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function delete(ExpensesDetails $detail)
    {
        if ($detail->delete()) {
            return back()->with('status', 'Deleted successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function trash()
    {
        $trash = ExpensesDetails::Trash();
        return view('dashboard.expensesdetails.index', compact('trash'));
    }

    /**
     * @param $id
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function restore($id)
    {
        if (ExpensesDetails::onlyTrashed()->find($id)->restore()) {
            return back()->with('status', 'Restored successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    /**
     * @param $id
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function deletePermanently($id)
    {
        if (ExpensesDetails::onlyTrashed()->find($id)->forceDelete()) {
            return back()->with('status', 'Permanently Deleted');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }
}
