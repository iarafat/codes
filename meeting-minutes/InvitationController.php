<?php

namespace App\Http\Controllers;

use App\Agenda;
use App\Mail\MeetingCancelOrDone;
use App\Mail\MeetingInvitation;
use App\Personnel;
use App\Project, App\Invitation;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvitationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $invitations = Invitation::Index();
        return view('invitations.index', compact('invitations'));
    }

    public function history()
    {
        $invitations = Invitation::History();
        return view('invitations.history', compact('invitations'));
    }

    public function create()
    {
        $projects = Project::ownerProjects();
        return view('invitations.create', compact('projects'));
    }

    public function store(Request $request, Invitation $invitation)
    {
        $request->validate([
            'project_id' => 'required',
            'location_id' => 'required',
            'start' => 'required',
            'end' => 'required',
        ]);

        try {
            $storeInvitation = $invitation->create($request->all());
            foreach ($request->attendees as $key => $attendee) {
                Personnel::create([
                    'personneltable_id' => $storeInvitation->id,
                    'personneltable_type' => 'invitations',
                    'user_id' => $attendee,
                    'user_type' => 'attendees',
                ]);
                $this->meetingInvitation($attendee, $request->project_id, $request->start, $request->end);
            }

            foreach ($request->callers as $key => $caller) {
                Personnel::create([
                    'personneltable_id' => $storeInvitation->id,
                    'personneltable_type' => 'invitations',
                    'user_id' => $caller,
                    'user_type' => 'callers',
                ]);
            }

            foreach ($request->agendas as $key => $agenda) {
                Agenda::create([
                    'agendatable_id' => $storeInvitation->id,
                    'agendatable_type' => 'invitations',
                    'details' => $agenda['details']
                ]);
            }
            return redirect()->route('invitations.index')->with('status', 'Stored successfully');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }

    private function meetingInvitation($user_id, $project_id, $start, $end)
    {
        $user_email = optional(User::find($user_id))->email;
        $project_title = optional(Project::find($project_id))->title;
        $start_date = Carbon::parse($start)->toDayDateTimeString();
        $end_date = Carbon::parse($end)->toDayDateTimeString();
        Mail::to($user_email)->send(new MeetingInvitation($project_title, $start_date, $end_date));
    }

    public function open(Invitation $invitation, Request $request)
    {
        try {
            $users = $invitation->attendees()->where('user_type', 'attendees')->get();
            $project = $invitation->projects()->first()->id;
            $invitation->update($request->all());
            foreach ($users as $user) {
                $this->meetingInvitation($user->user_id, $project, $request->start, $request->end);
            }
            return back()->with('status', 'Opened successfully');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }

    public function cancel(Invitation $invitation, Request $request)
    {
        try {
            $subject = 'Meeting Canceled';
            $users = $invitation->attendees()->where('user_type', 'attendees')->get();
            $project = $invitation->projects()->first()->id;
            $invitation->update($request->all());
            foreach ($users as $user) {
                $this->meetingCancelOrDone($user->user_id, $project, $request->details, $subject);
            }
            return back()->with('status', 'Canceled successfully');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }

    public function done(Invitation $invitation, Request $request)
    {
        try {
            $invitation->update($request->all());
            /*$subject = 'Meeting Done';
            $users = $invitation->attendees()->where('user_type', 'attendees')->get();
            $project = $invitation->projects()->first()->id;
            foreach ($users as $user) {
                $this->meetingCancelOrDone($user->user_id, $project, $request->details, $subject);
            }*/
            return redirect()->route('meetings.from.invitation', $invitation->id)->with('status', 'Invitation Done successfully, Now you could create Meeting');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }

    private function meetingCancelOrDone($user_id, $project_id, $body, $subject)
    {
        $user_email = optional(User::find($user_id))->email;
        $project_title = optional(Project::find($project_id))->title;
        Mail::to($user_email)->send(new MeetingCancelOrDone($project_title, $body, $subject));
    }

    public function edit($id)
    {
        $invitation = Invitation::InvitationWithAllRelations($id);
        $projects = Project::ownerProjects();
        return view('invitations.edit', compact('invitation', 'projects'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'project_id' => 'required',
                'location_id' => 'required',
                'start' => 'required',
                'end' => 'required',
            ]);
            $invitation = Invitation::find($id);
            $invitation->update($request->all());
            Personnel::where(['personneltable_id' => $id, 'personneltable_type' => 'invitations', 'user_type' => 'attendees'])->whereIn('user_id', $request->attendees)->delete();
            foreach ($request->attendees as $key => $attendee) {
                Personnel::create([
                    'personneltable_id' => $id,
                    'personneltable_type' => 'invitations',
                    'user_id' => $attendee,
                    'user_type' => 'attendees',
                ]);
                $this->meetingInvitation($attendee, $request->project_id, $request->start, $request->end);
            }

            Personnel::where(['personneltable_id' => $id, 'personneltable_type' => 'invitations', 'user_type' => 'callers'])->whereIn('user_id', $request->callers)->delete();
            foreach ($request->callers as $key => $caller) {
                Personnel::create([
                    'personneltable_id' => $id,
                    'personneltable_type' => 'invitations',
                    'user_id' => $caller,
                    'user_type' => 'callers',
                ]);
            }

            foreach ($request->agendas as $key => $agenda) {
                if (is_null($agenda['id'])) {
                    $invitation->agendas()->create(['details' => $agenda['details']]);
                } else {
                    $invitation->agendas()->where('id', $agenda['id'])->update([
                        'details' => $agenda['details']
                    ]);
                }
            }
            return back()->with('status', 'Updated successfully');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }

    public function copy($id)
    {
        $invitation = Invitation::InvitationWithAllRelations($id);
        $projects = Project::ownerProjects();
        return view('invitations.copy', compact('invitation', 'projects'));
    }

    public function delete(Invitation $invitation)
    {
        if ($invitation->delete()) {
            return back()->with('status', 'Deleted successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    public function trashIndex()
    {
        $trashInvitations = Invitation::TrashInvitationWithRelations();
        return view('invitations.index', compact('trashInvitations'));
    }

    public function restore($id)
    {
        if (Invitation::onlyTrashed()->find($id)->restore()) {
            return back()->with('status', 'Restored successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    public function deletePermanently($id)
    {
        if (Invitation::onlyTrashed()->find($id)->forceDelete()) {
            return back()->with('status', 'Permanently Deleted');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

}
