<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request, App\Program, App\Shade;

class ProgramController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // index method
    public function index()
    {
        $programs = Program::orderBy('id', 'desc')->paginate(10);
        return view('dyeing.programs.index', compact('programs'));
    }

    // create method
    public function create()
    {
        $shades = Shade::all();
        return view('dyeing.programs.create', compact('shades'));
    }

    // store method
    public function store(Request $request)
    {
        $request->validate([
            'batch_no' => 'required|integer',
            'weight' => 'required',
            'length' => 'required',
            'shade_id' => 'required',
            'delivery_date' => 'required',
            'dyeing_hour' => 'required|integer',
        ]);
        $program = new Program;
        if ($program->store($request)){
            return back()->with('status', 'Store successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    // edit method
    public function edit(Program $program)
    {
        $shades = Shade::all();
        return view('dyeing.programs.edit', compact('program', 'shades'));
    }

    // update method
    public function update(Request $request)
    {
        $request->validate([
            'batch_no' => 'required|integer',
            'weight' => 'required',
            'length' => 'required',
            'shade_id' => 'required',
            'delivery_date' => 'required',
            'dyeing_hour' => 'required|integer',
        ]);
        $program = new Program;
        if ($program->store($request)){
            return back()->with('status', 'Store successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    // delete method
    public function delete(Program $program)
    {
        if ($program->delete()){
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
        $trashPrograms = Program::onlyTrashed()->orderBy('id', 'desc')->paginate(10);
        return view('dyeing.programs.index', compact('trashPrograms'));
    }

    // restore method
    public function restore($id)
    {
        $program = Program::onlyTrashed()->find($id);
        if ($program->restore()){
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
        $program = Program::onlyTrashed()->find($id);
        if ($program->forceDelete()){
            return back()->with('status', 'Permanently Deleted');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    public function sequence(Request $request)
    {
        $program = new Program;
        $program->updateSequenceId($request);
    }

}
