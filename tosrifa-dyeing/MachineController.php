<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request, App\Machine;
use Excel;
use  Barryvdh\DomPDF\Facade as PDF;

class MachineController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // index method
    public function index()
    {
        $machines = Machine::orderBy('id', 'desc')->paginate(10);
        return view('dyeing.machines.index', compact('machines'));
    }

    // show programs method
    public function showPrograms()
    {
        $machines = Machine::with('programs')->orderBy('capacity', 'asc')->get();
        return view('dyeing.programs.programs', compact('machines'));
    }

    public function showProgramsList()
    {
        $machines = Machine::with(['programsBorderBy' => function ($query) {
            $query->orderBy('programs.delivery_date', 'desc');
        }])->orderBy('capacity', 'desc')->get();
//        dd($machines);
        return view('dyeing.programs.list-view', compact('machines'));
    }


    public function programsExcel()
    {
        $machines = Machine::with(['programsBorderBy' => function ($query) {
            $query->orderBy('programs.delivery_date', 'desc');
        }])->orderBy('capacity', 'desc')->get();

//        return view('excel.programs-list-view', compact('machines'));

        $excel =  Excel::create('Dyeing Programs', function($excel) use ($machines) {

            $excel->sheet('Dyeing Programs', function($sheet) use ($machines) {

                $sheet->loadView('excel.programs-list-view', array(
                    'machines' => $machines
                ));

            });

        })->download('xlsx');
        if (isset($excel)) {
            return back();
        }
    }

    public function programsPdf()
    {
        $machines = Machine::with(['programsBorderBy' => function ($query) {
            $query->orderBy('programs.delivery_date', 'desc');
        }])->orderBy('capacity', 'desc')->get();

//        return view('pdf.programs-list-view', compact('machines'));

        $pdf = PDF::loadView('pdf.programs-list-view', ['machines' => $machines]);
        return $pdf->download('DyeingPrograms.pdf');

    }

    // store method
    public function store(Request $request, Machine $machine)
    {
        $request->validate([
            'title' => 'required|max:191',
            'capacity' => 'required|integer',
        ]);
        if ($machine->create($request->all())){
            return back()->with('status', 'Store successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    // edit method
    public function edit(Machine $machine)
    {
       return view('dyeing.machines.edit', compact('machine'));
    }

    // update method
    public function update(Request $request, Machine $machine)
    {
        $request->validate([
            'title' => 'required|max:191',
            'capacity' => 'required|integer',
        ]);
        if ($machine->update($request->all())){
            return back()->with('status', 'Updated successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    // delete method
    public function delete(Machine $machine)
    {
        if ($machine->delete()){
            return back()->with('status', 'Deleted successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    // trash index method
    public function trashIndex()
    {
        $trashMachines = Machine::onlyTrashed()->orderBy('id', 'desc')->paginate(10);
        return view('dyeing.machines.index', compact('trashMachines'));
    }

    // restore method
    public function restore($id)
    {
        $machine = Machine::onlyTrashed()->find($id);
        if ($machine->restore()){
            return back()->with('status', 'Restored successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    // delete permanently method
    public function deletePermanently($id)
    {
        $machine = Machine::onlyTrashed()->find($id);
        if ($machine->forceDelete()){
            return back()->with('status', 'Permanently Deleted');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }
}
